# Contrato de negociación: estados, reglas y recursos

**Agrocom SRL · Vigente · documento de trabajo para implementar API, panel y apps**

Resume con precisión lo que `marketplace.md` define, para quien implementa. Si este documento y `docs/api/openapi.yaml` difieren, manda el OpenAPI una vez generado; si difieren de `marketplace.md`, se corrige este documento. Todos los parámetros viven en `config/negociacion.php` y `config/seguimiento.php` (ADR 0016) salvo que se indique otro archivo. Importes y hectáreas en decimal exacto (`BigDecimal` en el servidor, `decimal` en Dart); el redondeo de importes es a 2 decimales, mitad hacia arriba, solo al presentar o congelar el monto.

## 1. Máquina de estados de la solicitud

Toda transición pasa por `MaquinaEstadosSolicitud` (invariante 7), con fila de historial (actor, origen, motivo) y bloqueo de la fila de la solicitud.

| De | A | Actor / origen | Guarda | Efecto |
|---|---|---|---|---|
| `borrador` | `abierta` | cliente | Terrenos propios con `departamento_codigo` y `provincia_codigo`, todos en el mismo departamento; `hectareas_solicitadas > 0` y no mayores a las del terreno, `precio_ha_propuesto > 0` y no mayor al máximo de sanidad, fecha deseada, si la hay, no pasada y dentro de `anticipacion_maxima_dias`, `requisitos` válidos, cuenta con teléfono | Congela departamento y provincia; abre la oleada (paso 0); emite `SolicitudAbierta`. La solicitud no tiene vencimiento |
| `borrador` | `cancelada` | cliente | — | Sin registro |
| `abierta` | `con_ofertas` | sistema | Llega una oferta `vigente` (la primera oferta de un proveedor) | Empieza la etapa de negociación (§3.8) |
| `con_ofertas` | `abierta` | sistema | La última oferta vigente deja de estarlo (rechazada, retirada, vencida) y no queda otra | — |
| `abierta`, `con_ofertas` | `adjudicada` | cliente o proveedor | Se acepta la oferta vigente de la contraparte; equipo apto y libre (ver §3.6) | Fija `precio_ha_acordado`, `monto_estimado`, `proveedor_id`, `equipo_id`, `oferta_adjudicada_id`; cierra las demás ofertas vigentes; abre contacto y chat; emite `SolicitudAdjudicada` |
| `abierta`, `con_ofertas` | `cancelada` | cliente | Motivo opcional | Cierra ofertas vigentes y la oleada; sin registro de cancelación |
| `adjudicada` | `en_ejecucion` | proveedor (piloto del equipo) | Registro `inicio` válido | Emite `EjecucionIniciada` |
| `adjudicada` | `abierta` | proveedor | Motivo obligatorio | Libera proveedor y equipo; registra en `neg_cancelaciones` y `tasa_cancelacion`; el proveedor no puede volver a ofertar en esta solicitud; las demás ofertas siguen cerradas; chat de solo lectura |
| `adjudicada` | `cancelada` | cliente | Motivo obligatorio | Registra en `neg_cancelaciones` (parte `cliente`); sin penalidad en dinero |
| `en_ejecucion` | `finalizada` | proveedor (piloto del equipo) | Registro `cierre` válido y evidencias mínimas subidas (§4) | Congela `hectareas_ejecutadas` y `monto_final`; elimina la ubicación; emite `SolicitudFinalizada` |
| `finalizada` | `calificada` | sistema | El cliente registra su calificación | — |
| `finalizada`, `calificada` | `disputada` | cliente (plazo de reclamo) o proveedor (por motivo permitido) | Un reclamo por solicitud | Ver `disputas.md` |
| `disputada` | `finalizada` o `calificada` | sistema, al resolver | Resolución distinta de reembolso total | Aplica `monto_ajustado` como precio final vigente |
| `disputada` | `cancelada` | sistema, al resolver | Reembolso total a favor del cliente | — |

No existe cancelación desde `en_ejecucion`: lo no ejecutado se cierra con las hectáreas realmente ejecutadas y, si hay desacuerdo, con un reclamo.

La solicitud **no vence sola**: sigue `abierta` o `con_ofertas` hasta que se adjudica o el cliente la cancela (ADR 0045, punto 20); no hay estado `expirada` ni renovación.

Plazos (parámetros): vigencia de la oferta (24 horas por defecto); anticipación máxima de la fecha deseada 365 días; recordatorio al cliente de una solicitud sin trato cada 7 días; disputa del cliente 72 horas desde `finalizada`; calificación 7 días desde `finalizada`.

Procesos programados: `negociacion:procesar` cada minuto (vence ofertas, reevalúa `con_ofertas` y `abierta`, recuerda al cliente las solicitudes sin trato y avanza y relanza las oleadas de aviso, §7.1; idempotente, `withoutOverlapping`, `onOneServer`) y `seguimiento:avisar-inactividad` cada hora (avisa a las partes si una solicitud `en_ejecucion` no tiene avance en `seguimiento.inactividad_horas`, 72 por defecto).

## 2. Máquina de estados de la oferta

`MaquinaEstadosOferta` es la única clase que escribe `neg_ofertas.estado`.

| De | A | Actor | Condición |
|---|---|---|---|
| `vigente` | `aceptada` | contraparte del autor | Aceptación atómica; adjudica |
| `vigente` | `reemplazada` | contraparte del autor | Contraoferta: crea la oferta siguiente `vigente` |
| `vigente` | `rechazada` | contraparte del autor | Cierra la negociación; el proveedor no puede abrir otra en esa solicitud |
| `vigente` | `retirada` | autor | Solo mientras está `vigente` |
| `vigente` | `vencida` | sistema | `vence_en` pasó |
| `vigente` | `cerrada` | sistema | Solicitud adjudicada a otra oferta o cancelada |

Reglas:

1. La **primera oferta** de una negociación la crea el proveedor (`POST /solicitudes/{id}/ofertas`) con `numero = 1`. Tipo `acepta_propuesta`: el precio es exactamente `precio_ha_propuesto`. Tipo `contraoferta`: el precio es el que indica, distinto de la propuesta.
2. Cada oferta siguiente la crea la contraparte del autor de la vigente, con `numero + 1`. El autor alterna: impar proveedor, par cliente.
3. `numero = negociacion.max_ofertas_por_negociacion` (5) marca `es_definitiva = true`: no admite contraoferta, solo aceptar o rechazar.
4. Una sola oferta `vigente` por `(solicitud_id, proveedor_id)` y una sola `aceptada` por solicitud (índices únicos parciales).
5. `vence_en = ahora + vigencia_oferta_horas`; en solicitudes `abierta` o `con_ofertas`. `vigencia_oferta_horas` admite cualquier cantidad de horas (720 son 30 días). La solicitud no tiene vencimiento propio.
6. Un proveedor con una negociación cerrada por `vencida` o `retirada` puede abrir otra mientras la solicitud siga abierta; tras `rechazada` por el cliente no.
7. Si el proveedor `acepta_propuesta`, el cliente no queda obligado: elige entre las ofertas. Aceptarla es lo que adjudica.
8. Aceptar, rechazar y contraofertar una oferta que no es `vigente` o cuyo `vence_en` pasó responde 409 `oferta_no_vigente`. Aceptar cuando la solicitud ya está adjudicada responde 409 `solicitud_ya_adjudicada`.
9. Idempotencia por `uuid_cliente` en toda creación de oferta: reintentar el mismo uuid devuelve la misma oferta con código 200.
10. Bloqueo: toda decisión bloquea primero la fila de la solicitud y después las de sus ofertas, siempre en ese orden.

## 3. Reglas de negociación

### 3.1 Precios
- `precio_ha > 0` y `<= negociacion.precio_ha_maximo`; escala de 4 decimales.
- `monto_estimado = hectareas_solicitadas × precio_ha_acordado`; `monto_final = hectareas_ejecutadas × precio_ha_acordado`, congelado en `finalizada` (redondeo a 2 decimales).
- `hectareas_ejecutadas <= hectareas_solicitadas × (1 + seguimiento.tolerancia_hectareas_pct / 100)`; la tolerancia por defecto es 5 %. Pasarse responde 422.
- No hay precio de referencia: lo ponen el cliente y el proveedor.
- El cliente puede cambiar `precio_ha_propuesto` con `POST /solicitudes/{id}/precio` mientras la solicitud está `abierta` o `con_ofertas`. Las ofertas vigentes no cambian; se notifica a los proveedores con negociación vigente.
- Un equipo que no cumple un requisito del cliente (§3.7) puede ofertar.

### 3.2 Quién puede ofertar
Cuenta de proveedor (titular o piloto) con proveedor `aprobado`, al menos un equipo apto (piloto y dron aprobados, dron con fotos aprobadas, documentos vigentes), sin suspensión. El `equipo_id` de la oferta debe ser de su proveedor y apto. Un proveedor no oferta sobre solicitudes de su propia cuenta de cliente.

### 3.3 Alcance del mapa
No hay zonas, radio ni restricción geográfica. Un piloto habilitado ve una solicitud `abierta` o `con_ofertas` si tiene una negociación en ella o si no está en negociación y, o bien ya se abrió a todos (salió la súper ola, el último paso de la escalera; desde entonces también mientras la solicitud está `esperando`), o bien una oleada lo alcanzó. Los filtros del mapa son del usuario, no restricciones; el único filtro geográfico es el departamento. Ofertar sobre una solicitud que no ve responde 409 `solicitud_no_disponible`. Una cuenta de proveedor aún no habilitada (proveedor `pendiente` o sin equipo apto) no ve el mapa y recibe 403 `proveedor_no_habilitado`; sí puede pedir el contador del §8.

### 3.4 Qué ve cada parte en cada estado

| Dato | Piloto antes de ofertar | Proveedor con oferta (no adjudicada) | Cliente | Proveedor adjudicado | Otro proveedor tras adjudicar |
|---|---|---|---|---|---|
| Lotes (polígonos), hectáreas, cultivo, tipo de aplicación, producto, fecha, accesos, observaciones, estado del terreno, fotos del terreno | Sí | Sí | Sí | Sí | No (la solicitud sale del mapa) |
| Precio propuesto | Sí | Sí | Sí | Sí | No |
| Cantidad de ofertas vigentes | Sí | Sí | Sí | — | — |
| Precios de las ofertas de otros proveedores | No | No | Sí | No | No |
| Nombre, teléfono y documento del cliente, referencia textual de dirección | No | No | — | Sí | No |
| Nombre, foto, calificación y servicios del proveedor, modelo y fotos aprobadas del dron, requisitos que no cumple | — | — | Sí, por oferta | — | — |
| Teléfono y documento del piloto | — | — | Solo adjudicado | — | — |
| Fotos de avance, porcentaje y hectáreas ejecutadas | — | — | Sí | Sí | No |
| Ubicación del piloto | — | — | Solo en `en_ejecucion` | Sí (la propia) | No |
| Chat | — | — | Solo con el adjudicado | Sí | No |
| Calificación recibida del cliente | — | — | — | Sí (visible) | — |
| Calificación recibida del proveedor | — | — | No | — | — |

El alcance parte siempre de la cuenta (invariante 5): un id ajeno responde 404.

### 3.5 Cancelaciones y reputación
Sin penalidad en dinero ni restricción automática. `neg_cancelaciones` alimenta `tasa_cancelacion` (cancelaciones tras adjudicar sobre servicios adjudicados, últimos 90 días), visible al cliente en la oferta y al proveedor en `GET /reputacion`. Si la calificación o la tasa pasa un umbral (`calificaciones.umbral_alerta`, `negociacion.umbral_cancelacion_alerta`), se emite una alerta al panel; el administrador decide.

### 3.6 Elegibilidad al adjudicar
Antes de adjudicar se revalida bajo bloqueo: proveedor aprobado, equipo apto y sin otra solicitud `adjudicada` o `en_ejecucion` cuyo rango de fecha deseada se cruce con la de esta. Si falla, 409 `equipo_no_disponible` y la oferta sigue vigente.

### 3.7 Requisitos del cliente y equipo que no cumple
`requisitos` de la solicitud es un objeto por clave de criterio (`{"dron_capacidad_min_l": "20.00"}`; decimales como cadena). Cada clave la valida su criterio de alcance (ADR 0045); una clave desconocida se rechaza al crear con 422 `requisito_desconocido`. Al ofertar el servidor evalúa los requisitos contra el equipo y guarda en la oferta `incumple` (lista de claves que no cumple, vacía si cumple todo). No bloquea. El comparador y el aviso de oferta recibida muestran qué requisito no se cumple y, si todas las ofertas vigentes incumplen, lo dicen de forma explícita.

### 3.8 Etapa de negociación, búsqueda detenida y solicitud esperando piloto
- **Cuándo empieza.** Cuando un proveedor toma la solicitud para negociar: el **primer registro de una oferta** (`POST /solicitudes/{id}/ofertas`, de tipo `acepta_propuesta` o `contraoferta`). Entrar a la pantalla de precios de la app sin enviar nada no cambia el servidor. La solicitud está **en negociación** cuando el número de negociaciones con una oferta `vigente` alcanza `negociacion.etapa.cupo_negociaciones` (1 por defecto; con 1 coincide con `con_ofertas`). Se calcula de las ofertas, no se guarda. Mientras dura, no aparece en el mapa de los demás proveedores, la oleada queda `en_pausa` y ofertar sobre ella responde 409 `solicitud_no_disponible` (la decisión se toma bajo el bloqueo de la solicitud: si dos pilotos envían a la vez, gana el primero).
- **Cuándo termina.** Al adjudicarse, o cuando la oferta vigente deja de estarlo sin acuerdo: el cliente la rechaza, el proveedor la retira o vence. Sin acuerdo, la solicitud vuelve a ofertarse: reaparece en el mapa de quienes la veían y la oleada continúa desde donde estaba, con el reloj del siguiente paso reiniciado (si estaba `esperando`, vuelve a `esperando`). A quien el cliente rechazó no se le vuelve a avisar en esa solicitud (criterio `sin_rechazo_previo`).
- `POST /solicitudes/{id}/busqueda/detener` (solo el cliente, solicitud `abierta` o `con_ofertas`) detiene las oleadas y los relanzamientos automáticos y congela el alcance: no se abre a más proveedores y sigue visible para quienes ya la ven. `POST /solicitudes/{id}/busqueda/reanudar` la reactiva y la oleada vuelve al estado que tenía. Ambos son idempotentes y devuelven el estado de la búsqueda; en otro estado, 409 `estado_invalido`. Si la negociación termina sin acuerdo y la búsqueda está detenida, la solicitud no vuelve a ofertarse hasta que el cliente la reanude.
- **Esperando piloto.** Cuando sale la súper ola y nadie ofertó, la oleada queda `esperando` y la solicitud sigue `abierta`, visible para todo proveedor habilitado; el sistema no la desactiva ni la vence. El cliente puede ajustar el precio (`POST /solicitudes/{id}/precio`) y los requisitos y la fecha deseada (`PUT /solicitudes/{id}/ajustes`, solo sin negociación vigente) y **relanzar** (`POST /solicitudes/{id}/busqueda/relanzar`: abre un ciclo nuevo desde el paso 0; solo con la oleada `esperando`, si no 409 `busqueda_no_esperando`). Como máximo un ciclo nuevo por día calendario, manual o automático (409 `relanzamiento_demasiado_pronto`).
- **Relanzamiento automático.** `PUT /solicitudes/{id}/busqueda/programacion` con `dias` (días de la semana, ISO 1 a 7: mínimo 2 distintos, si no 422 `relanzamiento_dias_insuficientes`) y `hora` (`HH:MM`, hora de Bolivia, una por día, así que a lo más un relanzamiento diario). Si el cliente no la fijó rige `negociacion.oleadas.relanzamiento` (martes y viernes a las 07:00). Si el cliente no relanza a mano, la pasada del reloj relanza cuando `proximo_relanzamiento_en` vence y la oleada está `esperando`; si en ese momento hay un ciclo en marcha, una negociación o la búsqueda está detenida, no hace nada y no se acumula.
- La solicitud del cliente trae `etapa` (`buscando`, `en_negociacion`, `busqueda_detenida` o `esperando_piloto`; `en_negociacion` manda si coinciden) y `busqueda` (`estado` de la oleada, `ciclo`, `paso`, `avisados`: cantidad, nunca quiénes; `programacion` y `proximo_relanzamiento_en`).
- **Recordatorio.** Cada `negociacion.pendiente.recordatorio_dias` (7) sin trato se avisa al cliente («tu solicitud sigue esperando piloto: ajusta el precio o los requisitos, o cancélala»). Si pasa `ventana_hasta` sin trato se le avisa y no se relanza sola hasta que actualice la fecha; la solicitud no se cancela.

### 3.9 Orden de las ofertas
`GET /solicitudes/{id}/ofertas?orden=` acepta `recomendado` (por defecto: proveedores `propio` primero; luego mayor calificación, con quien tiene menos de `negociacion.oleadas.calificaciones_minimas_para_contar` calificaciones al final de su grupo; luego menor `precio_ha`; luego la más antigua), `precio`, `calificacion` y `reciente`. No hay orden por distancia: no se calcula.

## 4. Seguimiento y cierre

Registros que el piloto envía (un solo rol escritor; el servidor rechaza si la cuenta no es del equipo adjudicado). Cada uno tiene su ruta REST (`POST /solicitudes/{id}/inicio`, `/avance`, `/cierre` y `/mensajes`), idempotente por `uuid_cliente`, que la app reintenta desde su outbox; la tabla muestra el tipo de registro:

| Tipo | Campos | Efecto |
|---|---|---|
| `inicio` | `uuid_cliente`, `secuencia`, `solicitud_id`, `registrado_en` | `adjudicada` → `en_ejecucion`; una sola vez |
| `avance` | idem, `hectareas_ejecutadas` o `porcentaje` (uno de los dos), `nota`, `motivo_correccion` | Guarda ambos valores (el faltante se deriva de las hectáreas solicitadas); un valor menor al último exige motivo |
| `cierre` | idem, `hectareas_ejecutadas`, `nota` | Marca `cierre_solicitado_en`; con las evidencias mínimas subidas pasa a `finalizada` |
| `mensaje` | idem, `solicitud_id`, `texto` | Crea el mensaje; entrega al recuperar red |
| `incidencia` | como hoy: tipo, severidad, descripción, ubicación | Abre incidencia |

Evidencias: la ruta es `POST /solicitudes/{id}/evidencias` (multipart, cola separada, menos de 300 KB) con `uuid_cliente`, `tipo` (`foto_avance`, `foto_final`, `foto_incidencia`) y `avance_uuid_cliente` opcional. El piloto del equipo puede subir. La solicitud pasa a `finalizada` cuando hay `cierre` y al menos `seguimiento.evidencias_minimas_cierre` (1) evidencias `foto_avance` o `foto_final` subidas, en el orden que lleguen (el procesamiento de la evidencia revisa el cierre pendiente).

Pago confirmado: el pago ocurre fuera de la aplicación (ADR 0034). `POST /solicitudes/{id}/pago-confirmado` (`uuid_cliente`, `metodo` opcional, `nota` opcional) lo escribe un solo rol, el titular del proveedor adjudicado (quien cobra); el piloto que no es titular responde 403 `pago_solo_titular` y el cliente 403 `pago_solo_proveedor`. Solo con la solicitud `finalizada`, `calificada` o `disputada` (si no, 409 `estado_invalido`). El monto lo fija el servidor con el monto final congelado. Es único por solicitud e idempotente por `uuid_cliente` (201 la primera vez, 200 el reintento); otro `uuid_cliente` después responde 409 `pago_ya_confirmado` y lo confirmado no se reescribe. No cambia el estado. Ambas partes lo leen con `GET /solicitudes/{id}/pago-confirmado` (`pago: null` si aún no se informó); una solicitud ajena responde 404.

Precio final confirmado por el cliente: el cliente paga, así que tiene la última palabra sobre el precio final (`hectareas_ejecutadas` × `precio_ha_acordado`, congelado en `monto_final`): lo confirma o abre un reclamo. `POST /solicitudes/{id}/precio-final/confirmar` (sin cuerpo) lo escribe un solo rol, el cliente de la solicitud; el proveedor responde 403 `precio_final_solo_cliente` y una solicitud ajena 404. Solo con la solicitud `finalizada` o `calificada` (el piloto ya cerró con las hectáreas ejecutadas; si no, o con un reclamo abierto, 409 `estado_invalido`). El servidor copia hectáreas, precio y monto del cierre en `neg_precios_finales_confirmados`; es único por solicitud e idempotente (201 la primera vez, 200 el reintento con el mismo registro) y lo confirmado no se reescribe. No cambia el estado, no mueve dinero y no cierra el plazo de reclamo. La bitácora registra al actor y se avisa a las cuentas del proveedor adjudicado (`precio_final_confirmado`). Ambas partes lo leen con `GET /solicitudes/{id}/precio-final` (`precio_final: null` mientras el piloto no cierre; con `confirmado` y `confirmado_en`).

Respuesta por registro: 201 si se aplicó, 200 con el mismo registro si el `uuid_cliente` ya existía (duplicado), o 404, 409 o 422 con el `codigo` del motivo (`solicitud_ajena`, `estado_invalido`, `hectareas_exceden_tolerancia`, `correccion_sin_motivo`).

Finalizar no exige ningún otro tipo de registro operativo: solo `cierre` y las evidencias mínimas.

## 5. Mensajes

- Solo entre el cliente y la cuenta de proveedor adjudicada (titular y piloto del equipo), desde `adjudicada`. Lectura y escritura hasta el cierre del plazo de calificación (7 días tras `finalizada`) o mientras haya un reclamo abierto; después, solo lectura.
- Cada mensaje tiene un `tipo`: `texto` (`texto` obligatorio, hasta 1000 caracteres), `foto` o `sistema`.
- Foto: `POST /solicitudes/{id}/mensajes/fotos` (multipart: `uuid_cliente`, `archivo` jpg, png o webp, `texto` opcional como pie de hasta 280 caracteres). Reglas de las evidencias: menos de `seguimiento.evidencia_max_kb` (300; la app la comprime antes, si no 422 `evidencia_muy_grande`), disco privado, hash y peso guardados, idempotente por `uuid_cliente`. El mensaje trae `url` (`GET /mensajes/{id}/archivo`, solo las dos partes; otra cuenta 404). Escriben el cliente y la cuenta del proveedor adjudicado, y avisan a la otra parte como un mensaje de texto.
- Sistema: líneas que escribe el servidor cuando la negociación avisa de un hecho de la solicitud, sin autor (`autor_rol` `sistema`, `propio` falso, nunca se marcan leídas): `solicitud_adjudicada`, `servicio_iniciado` («El piloto inició el servicio.»), `servicio_finalizado` y `precio_final_confirmado`. `evento` dice cuál y `texto` ya viene en español. Una por hecho (idempotente por la clave del aviso) y se escriben aunque el chat esté en solo lectura.
- `GET /solicitudes/{id}/mensajes?despues={cursor}&limite=50` devuelve en orden de creación con cursor opaco; `POST` es idempotente por `uuid_cliente`; `POST /solicitudes/{id}/mensajes/leidos` marca hasta un cursor.
- Entrega «instantánea»: el cliente consulta con sondeo corto (cada 5 segundos con el chat abierto) y recibe aviso push al llegar un mensaje. El contrato por cursor permite pasar a un canal en tiempo real sin cambiar los recursos (ADR 0040).

## 6. Ubicación del piloto

- `PUT /ubicacion` con `solicitud_id`, `latitud`, `longitud`, `precision_m`, `capturado_en`. Solo acepta si la solicitud está `en_ejecucion` y la cuenta es del equipo; en otro estado responde 409 `ubicacion_no_permitida`. Reemplaza la fila única de la solicitud.
- La app envía cada `seguimiento.intervalo_ubicacion_s` (30) con la pantalla del servicio abierta o el servicio en primer plano, y deja de enviar al finalizar o cuando el piloto la apaga. No va por el outbox: si no hay red, se omite.
- `GET /solicitudes/{id}/ubicacion-piloto` devuelve `latitud`, `longitud`, `precision_m`, `capturado_en`, `antiguedad_s` y `vigente` (falso si pasaron `seguimiento.ubicacion_caduca_s`, 300). Sin ubicación responde 200 con `ubicacion: null`. Sin ruta ni tiempo estimado de llegada.
- Al finalizar o cancelar se elimina la fila. Nada de ubicación se guarda en historial, bitácora ni logs.

## 7. Notificaciones y preferencias

`GET /notificaciones/preferencias` y `PUT /notificaciones/preferencias`:

```json
{
  "nuevas_solicitudes": {
    "activo": true,
    "hectareas_minimas": "50.0000",
    "departamentos_excluidos": ["04", "09"],
    "tipos_aplicacion_excluidos": ["desecante"]
  },
  "ofertas": true,
  "mensajes": true,
  "avance": true,
  "calificaciones": true
}
```

- `nuevas_solicitudes` aplica solo a cuentas de proveedor. Los filtros (`hectareas_minimas`, `departamentos_excluidos` con el código del catálogo, `tipos_aplicacion_excluidos`) son preferencias del proveedor que gobiernan solo los avisos; vacíos o ausentes no restringen. Se guardan en `neg_preferencias.filtros` y una clave desconocida se rechaza al guardar con 422. La respuesta incluye `filtros_disponibles` (lista de claves que el servidor conoce, con su tipo de valor) para que la app pinte los que sabe. Por defecto activo y sin filtros.
- Las categorías obligatorias (`SolicitudAdjudicada`, cierre, precio final confirmado, cuenta y aprobación, disputas, emergencias) no se pueden desactivar y no aparecen en las preferencias.
- `POST /dispositivos` registra `{ token, plataforma: android|ios }` del push (201 la primera vez, 200 si ya estaba; si el token estaba a nombre de otra cuenta pasa a la actual); `DELETE /dispositivos/{token}` lo retira al cerrar sesión (404 si no existe o es de otra cuenta). El token nunca se devuelve ni se escribe en logs ni en la bitácora. El envío al sistema operativo requiere configurar el proveedor de push (credencial fuera del repositorio); mientras no esté, el aviso vive en la lista `GET /notificaciones` y se consulta por sondeo.
- El aviso de una nueva solicitud sale por oleadas (§7.1): una solicitud avisa a una cuenta una sola vez por ciclo. Mientras no haya push, el piloto lo ve en la lista de la app (`GET /notificaciones`), que consulta al abrir, al volver a primer plano y al refrescar.

### 7.1 Oleadas de aviso («sistema solar»)
- Al publicar, el servidor arma la lista de proveedores que cumplen **todos** los criterios de alcance en el paso 0 (primera ola) y los avisa en el acto. Cada `negociacion.oleadas.intervalo_minutos` (T; 60 mientras no haya push, 2 a 5 con push), si la solicitud sigue sin ofertas, avanza un paso de la escalera (`negociacion.oleadas.escalera`: cada paso relaja criterios y cambia parámetros, de forma acumulativa) y avisa solo a quienes ahora cumplen y no habían sido avisados (la ola salta a los ya avisados). Un paso sin proveedores nuevos no espera: pasa al siguiente en la misma pasada.
- Criterios iniciales: `aprobado` (proveedor aprobado con equipo apto), `disponible` (la cuenta y, si es piloto de una empresa, su empresa están en `disponible`; punto 8 del ADR 0045), `sin_rechazo_previo`, `calificacion` (mínimo del paso; los propios siempre cumplen y entran desde la primera ola; quien tiene menos de `calificaciones_minimas_para_contar` calificaciones cuenta como sin calificación), `dron_capacidad` (requisito del cliente) y los filtros del proveedor (§7). Solo `calificacion` y los requisitos del cliente son relajables; `aprobado`, `disponible` y los filtros del proveedor nunca se relajan, ni en la súper ola.
- Escalera inicial (revisable): paso 0 calificación mínima 4,50; paso 1, 4,00; paso 2 sin mínimo; paso 3 sin el requisito de dron del cliente. El último paso es la **súper ola**: todos los elegibles, y la solicitud se abre a todos en el mapa (`abierta_a_todos_en`).
- Al salir la súper ola sin ofertas la oleada queda `esperando` («Esperando piloto»). No hay tope de ciclos ni vencimiento: el ciclo siguiente lo abre el cliente a mano o el horario que eligió (§3.8) y vuelve a avisar desde el paso 0.
- Registro de avisos `neg_oleada_avisos` con `UNIQUE (solicitud_id, proveedor_id, ciclo)`; la clave del aviso es `nueva_solicitud_abierta:{solicitud}:{ciclo}`. Correr la pasada dos, tres o diez veces deja las mismas filas y los mismos avisos; un aviso insertado y no entregado se reintenta. Un proveedor que vuelve a `disponible` no recibe avisos de pasos ya ocurridos.
- Estados de `neg_oleadas.estado`: `activa`, `en_pausa` (en negociación), `detenida` (el cliente dejó de buscar), `esperando` (súper ola enviada, espera el relanzamiento) y `terminada` (adjudicada o cancelada). Una cuenta con `nuevas_solicitudes` apagado no recibe estos avisos.

## 8. Mapa de solicitudes abiertas

`GET /solicitudes-abiertas` (rol proveedor, solo cuentas habilitadas; si no, 403 `proveedor_no_habilitado`):

| Parámetro | Significado |
|---|---|
| `bbox` | `oeste,sur,este,norte` del encuadre del mapa |
| `departamento` | Código del departamento del catálogo |
| `hectareas_min`, `hectareas_max` | Rango de hectáreas solicitadas |
| `precio_ha_min`, `precio_ha_max` | Rango del precio propuesto |
| `fecha_hasta` | Ventana deseada que empieza antes de esa fecha |
| `cultivo`, `tipo_aplicacion` | Filtros de contenido |
| `orden` | `reciente` (por defecto), `precio_ha`, `hectareas`, `fecha_deseada` |
| `cursor`, `limite` | Paginación por cursor (máximo 100) |

Cada elemento: `id`, `centro` (`latitud`, `longitud` del centroide de los lotes), `hectareas_solicitadas`, `precio_ha_propuesto`, `departamento`, `provincia`, `municipio`, `comunidad`, `cultivo`, `tipo_aplicacion`, `requisitos`, `ventana_desde`, `ventana_hasta`, `ofertas_vigentes`, `mi_negociacion` (estado de su oferta o `null`), `publicada_en`. Solo trae lo visible para ese proveedor (§3.3).

No hay resumen por zona ni cálculo de distancia: el mapa no recibe la posición del piloto.

`GET /solicitudes-abiertas/contador` (cuenta de proveedor cuyo proveedor está `pendiente` o aprobado sin equipo apto; rechazado, suspendido o de baja, 403): devuelve `total`, la cantidad de solicitudes `abierta` o `con_ofertas`, con el parámetro opcional `departamento`. No trae lotes, clientes, precios, etapa ni ubicación más fina que el departamento.

`GET /solicitudes-abiertas/{id}`: detalle previo a ofertar con los datos de la primera columna de §3.4 (polígonos GeoJSON de cada lote, estado del terreno, fotos con URL firmada de corta vida). No incluye datos del cliente.

## 9. Registro del proveedor, datos de la persona y dron con fotos

- `POST /proveedores/registro`: cuenta, `tipo` (`independiente` | `tercero`), datos de la persona (`nombres`, `apellidos`, `documento_identidad`, `telefono`, `fecha_nacimiento`), datos del piloto (`licencia_numero`, `licencia_vence`) y datos del dron (`marca`, `modelo`, `anio`, `numero_serie`, `capacidad_tanque_l`, `ancho_pasada_m`). Idempotente por `uuid_cliente`. Queda `pendiente`.
- `PUT /perfil/foto` y `DELETE /perfil/foto` (foto de perfil de la cuenta del token: cliente, titular o piloto; menos de 300 KB). La imagen se entrega por una URL firmada de corta vida (`GET /personas/{usuario}/foto`, sin token): `foto_url` viaja en `GET /auth/sesion` para la propia cuenta y en la tarjeta del proveedor (`proveedor.foto_url`, la del piloto del equipo o, si no tiene, la del titular) que ve el cliente al comparar ofertas.
- `PUT /proveedores/mi/disponibilidad` (`disponibilidad`: `disponible`, `ocupado` o `en_trabajo`) cambia el interruptor manual de la propia cuenta (el titular el del proveedor; el piloto de una empresa el suyo); `PUT /proveedores/pilotos/{id}/disponibilidad` lo cambia el encargado (titular, `proveedores.cuenta.gestionar`) para un piloto suyo. `GET /proveedores/mi` lo devuelve con quién y cuándo lo cambió. `ocupado` y `en_trabajo` dejan de recibir avisos de nuevas solicitudes (con el interruptor de la empresa en `ocupado` no recibe ningún piloto suyo); la cuenta sigue viendo el mapa y puede ofertar.
- `GET /proveedores/mi` y `GET /proveedores/drones`: el titular (`proveedores.cuenta.gestionar`) gestiona su proveedor y sus drones; el piloto de un proveedor, incluido el piloto propio de Agrocom (`proveedores.cuenta.ver`), los ve en solo lectura: el proveedor al que pertenece (sin documento fiscal) y los drones de sus equipos vigentes. Un rol sin ninguno de los dos permisos responde 403, y nadie ve el proveedor ni los drones de otro.
- `POST /proveedores/drones/{id}/fotos` (multipart; campos `tipo`, `archivo`); `GET` lista con estado; `DELETE /proveedores/drones/{id}/fotos/{foto}` solo mientras `en_revision` o `rechazada`.
- Fotos obligatorias para aprobar el dron: las del parámetro `flota.fotos_obligatorias` (`frontal`, `lateral`, `placa_serie`). El administrador aprueba o rechaza cada foto con motivo en `panel/drones-flota`.
- La oferta y el comparador del cliente muestran del dron: marca, modelo, año, capacidad y fotos `aprobada`.
- Fotos del terreno: `POST /terrenos/{id}/fotos` (multipart `archivo` y `descripcion` opcional, menos de 300 KB, hasta 8 por terreno) y `DELETE /terrenos/{id}/fotos/{foto}`, solo del cliente dueño. Las fotos viajan en `terreno.fotos` del terreno y de la solicitud del cliente, y en `terreno.fotos` del detalle `GET /solicitudes-abiertas/{id}`, cada una con una URL firmada de corta vida (`GET /terrenos/fotos/{foto}/archivo`, sin token).

## 10. Recursos nuevos, cambiados y retirados

### Nuevos
`GET /solicitudes-abiertas`, `GET /solicitudes-abiertas/{id}`, `GET /catalogos/ubicaciones`, `POST /solicitudes/{id}/busqueda/detener`, `POST /solicitudes/{id}/busqueda/reanudar`, `POST /solicitudes/{id}/busqueda/relanzar`, `PUT /solicitudes/{id}/busqueda/programacion`, `PUT /solicitudes/{id}/ajustes`, `GET /solicitudes-abiertas/contador`, `PUT /proveedores/mi/disponibilidad`, `PUT /proveedores/pilotos/{id}/disponibilidad`, `POST /solicitudes/{id}/ofertas`, `GET /solicitudes/{id}/ofertas`, `GET /ofertas/{id}`, `POST /ofertas/{id}/contraofertar`, `POST /ofertas/{id}/retirar`, `POST /solicitudes/{id}/precio`, `GET /solicitudes/{id}/avance`, `POST /solicitudes/{id}/{inicio|avance|cierre|evidencias}`, `GET /solicitudes/{id}/ubicacion-piloto`, `GET /solicitudes/{id}/precio-final`, `POST /solicitudes/{id}/precio-final/confirmar`, `PUT /ubicacion`, `GET|POST /solicitudes/{id}/mensajes`, `POST /solicitudes/{id}/mensajes/leidos`, `POST /solicitudes/{id}/pago-confirmado`, `GET|PUT /notificaciones/preferencias`, `POST /dispositivos`, `DELETE /dispositivos/{token}`, `POST /terrenos/{id}/fotos`, `DELETE /terrenos/{id}/fotos/{foto}`, `GET /terrenos/fotos/{foto}/archivo`, `POST|GET|DELETE /proveedores/drones/{id}/fotos`, `PUT|DELETE /perfil/foto`, `GET /personas/{usuario}/foto`, `GET /solicitudes/{id}/pago-confirmado`; registros `inicio`, `avance`, `cierre` y `mensaje`.

### Cambiados
- `POST /solicitudes`, `PUT /solicitudes/{id}`: reciben `precio_ha_propuesto`, varios terrenos, `observaciones`; ya no calculan tarifa ni recargos. `GET /solicitudes/{id}/estimacion` pasa a devolver el monto estimado con el precio propuesto.
- `POST /solicitudes/{id}/publicar`: deja la solicitud `abierta` y dispara el aviso a pilotos.
- `GET /solicitudes`, `GET /solicitudes/{id}`: estados nuevos, `precio_ha_acordado`, `monto_estimado`, `hectareas_ejecutadas`, `porcentaje_avance`, `monto_final`, `oferta_adjudicada`, `proveedor` (con contacto solo adjudicado).
- `GET /ofertas`, `GET /ofertas/{id}`, `POST /ofertas/{id}/aceptar`, `POST /ofertas/{id}/rechazar`: pasan a ser de la negociación y sirven a ambos roles; el lado lo decide el rol del token.
- `POST /solicitudes/{id}/cancelar`: sin penalidad; registra en `neg_cancelaciones` si la solicitud estaba adjudicada. `POST /solicitudes/{id}/cancelar-proveedor` reabre la solicitud (§1).
- `GET /reputacion`: calificación, tasa de cancelación y alertas; sin sanciones ni tasa de aceptación.
- `GET /sync/catalogo`: `servicios_asignados` pasa a ser las solicitudes `adjudicada` y `en_ejecucion` del equipo con lotes, datos de contacto y precio acordado; suma `mensajes`.
- Disputas: motivos §8.3 de `marketplace.md`; el resultado ajusta `monto_ajustado`, sin comisión.
- `POST /proveedores/registro`, `/proveedores/drones`: datos de persona y dron (§9); `zona_base_id` y `radio_operacion_km` ya no existen.
- `POST/PUT /terrenos`: `departamento_codigo` y `provincia_codigo` (del catálogo), `municipio` y `comunidad`; `zona_id` ya no existe.
- `POST/PUT /solicitudes`: `requisitos`, fecha deseada hasta la anticipación máxima; `GET /solicitudes` y `/{id}` suman `departamento`, `provincia`, `etapa` y `busqueda`; `GET /solicitudes/{id}/estimacion` ya no trae `precio_referencia_ha`.
- `GET /solicitudes-abiertas`: filtro `departamento`, solo lo visible para el proveedor (§3.3), sin `zona`, `macrorregion`, `distancia_km` ni `lat`/`lon`.
- `GET /solicitudes/{id}/ofertas`: `orden` por defecto `recomendado`, `incumple` por oferta, sin `distancia_km`.
- `GET/PUT /notificaciones/preferencias`: filtros del proveedor (§7) en lugar de zonas y macrorregiones.

### Retirados
`POST /solicitudes/{id}/renovar`, la vigencia de la solicitud (`vence_en`), el estado `expirada` y el reinicio de la escalera tras 24 horas (ADR 0045, revisión); `GET /zonas`, `GET /solicitudes-abiertas/resumen-zonas`, `GET /referencias/precio`, el cálculo de distancia (`distancia_km`, `orden=distancia`, posición en la consulta del mapa), las zonas y macrorregiones, el precio de referencia y la base y el radio de operación del proveedor (ADR 0045); `PUT|GET /disponibilidad` y la noción de equipo en línea (la disponibilidad actual es solo el estado manual del proveedor, `PUT /proveedores/mi/disponibilidad`); `POST /solicitudes/{id}/conformidad` y `POST /solicitudes/{id}/acta/firmar` (el cierre lo marca el piloto y el cliente puede disputar); la penalidad de cancelación, la comisión (`comision_porcentaje`, `comision_monto`, estado de cuenta, bloqueo por comisión vencida) y las sanciones automáticas (`asg_sanciones`, tasa de aceptación).

## 11. Datos existentes

Salvo la excepción de abajo, ninguna migración borra datos; toda tabla o columna que sale de `public` se mueve a un esquema `respaldo_*` (`ALTER TABLE … SET SCHEMA` o copia previa a la columna) con `down()` que la restaura, y se verifica contra PostgreSQL. Los esquemas `respaldo_*` los elimina la migración `2026_12_04_100100_elimina_esquemas_de_respaldo` (el sistema no conserva datos retirados).

Excepción del ADR 0045 (punto 26): el retiro de las zonas (`sol_zonas`, `zona_id`, `zona_base_id`, `radio_operacion_km`, zonas de las preferencias), del precio de referencia (`sol_precios_referencia`), de la distancia (`neg_ofertas.distancia_km`) y de la vigencia de la solicitud (`vence_en` y el estado `expirada`, cuyas filas pasan a `cancelada`) no pasa por `respaldo_*`, porque en beta solo hay datos de prueba y los catálogos son semillas. Las migraciones de retiro son nuevas, con `down()` verificado, y después se reconsolidan (ADR 0024). La excepción caduca si existe un dato real al integrarlas.

| Origen | Destino |
|---|---|
| Estados de la solicitud: `publicada`, `en_asignacion`, `sin_proveedor`, `asignada`, `en_camino`, `suspendida_por_clima`, `ejecutada`, `completada`, `en_disputa` | `abierta`, `abierta`, `cancelada`, `adjudicada`, `adjudicada`, `en_ejecucion`, `finalizada`, `finalizada` (o `calificada` si el cliente ya calificó), `disputada` |
| `tarifa_hectarea` | `precio_ha_propuesto`; para las solicitudes ya asignadas, también `precio_ha_acordado` |
| `comision_porcentaje`, `comision_monto` | Copia en `respaldo_comision.sol_solicitudes_comision`; luego se quitan las columnas |
| `asg_rondas`, `asg_ofertas`, `asg_cancelaciones_proveedor`, `asg_sanciones` | Esquema `respaldo_asignacion`; las ofertas aceptadas generan su fila equivalente en `neg_ofertas` con `numero = 1` |
| `flo_disponibilidad` | Esquema `respaldo_disponibilidad` |
| `ope_actas` | Esquema `respaldo_actas` |
| `ope_sesiones_vuelo`, `ope_mezclas` y afines | Se conservan sin cambios; salen del flujo y su destino se decide aparte |

## 12. Pruebas obligatorias

- **Máquinas de estado** (`tests/Unit`): tabla completa de transiciones permitidas y prohibidas de solicitud y oferta.
- **Aceptación concurrente**: dos aceptaciones simultáneas de ofertas distintas de la misma solicitud dejan una `aceptada` y la otra con 409.
- **Aislamiento (no funcional de seguridad)**: un piloto no ve ofertas de otro; un cliente no ve solicitudes ni mensajes ajenos; el contacto del cliente no aparece antes de adjudicar; la ubicación no se entrega fuera de `en_ejecucion`.
- **Oleadas** (`tests/Unit`, reloj inyectado): la pasada del reloj aplicada una, dos o diez veces deja las mismas filas y los mismos avisos; nadie recibe dos veces el aviso en un ciclo; `ocupado` y `en_trabajo` no reciben; los filtros del proveedor nunca se relajan; un relanzamiento por día como máximo y nunca mientras hay negociación, búsqueda detenida o ciclo en marcha.
- **Replay**: `inicio`, `avance`, `cierre` y `mensaje` aplicados dos, tres o diez veces, en orden y en desorden parcial, dejan la base idéntica.
- **Decimales**: `monto_estimado` y `monto_final` exactos con casos de redondeo.
- **Contrato**: cada ruta nueva está en `docs/api/openapi.yaml` con sus ejemplos.
