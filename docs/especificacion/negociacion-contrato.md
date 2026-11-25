# Contrato de negociación: estados, reglas y recursos

**Agrocom SRL · Vigente · documento de trabajo para implementar API, panel y apps**

Resume con precisión lo que `marketplace.md` define, para quien implementa. Si este documento y `docs/api/openapi.yaml` difieren, manda el OpenAPI una vez generado; si difieren de `marketplace.md`, se corrige este documento. Todos los parámetros viven en `config/negociacion.php` y `config/seguimiento.php` (ADR 0016) salvo que se indique otro archivo. Importes y hectáreas en decimal exacto (`BigDecimal` en el servidor, `decimal` en Dart); el redondeo de importes es a 2 decimales, mitad hacia arriba, solo al presentar o congelar el monto.

## 1. Máquina de estados de la solicitud

Toda transición pasa por `MaquinaEstadosSolicitud` (invariante 7), con fila de historial (actor, origen, motivo) y bloqueo de la fila de la solicitud.

| De | A | Actor / origen | Guarda | Efecto |
|---|---|---|---|---|
| `borrador` | `abierta` | cliente | Terrenos propios, `hectareas_solicitadas > 0` y no mayores a las del terreno, `precio_ha_propuesto > 0` y no mayor al máximo de sanidad, fecha deseada no pasada, cuenta con teléfono | Fija `vence_en`; emite `SolicitudAbierta` |
| `borrador` | `cancelada` | cliente | — | Sin registro |
| `abierta` | `con_ofertas` | sistema | Llega una oferta `vigente` | — |
| `con_ofertas` | `abierta` | sistema | La última oferta vigente deja de estarlo (rechazada, retirada, vencida) y no queda otra | — |
| `abierta`, `con_ofertas` | `adjudicada` | cliente o proveedor | Se acepta la oferta vigente de la contraparte; equipo apto y libre (ver §3.6) | Fija `precio_ha_acordado`, `monto_estimado`, `proveedor_id`, `equipo_id`, `oferta_adjudicada_id`; cierra las demás ofertas vigentes; abre contacto y chat; emite `SolicitudAdjudicada` |
| `abierta`, `con_ofertas` | `expirada` | sistema | `vence_en` pasó | Cierra ofertas vigentes; emite `SolicitudExpirada` |
| `abierta`, `con_ofertas` | `cancelada` | cliente | Motivo opcional | Cierra ofertas vigentes; sin registro de cancelación |
| `expirada` | `abierta` | cliente | Dentro de `negociacion.plazo_renovacion_dias` (30) desde que expiró | Nuevo `vence_en`; las ofertas anteriores siguen cerradas |
| `adjudicada` | `en_ejecucion` | proveedor (piloto del equipo) | Registro `inicio` válido | Emite `EjecucionIniciada` |
| `adjudicada` | `abierta` | proveedor | Motivo obligatorio | Libera proveedor y equipo; registra en `neg_cancelaciones` y `tasa_cancelacion`; el proveedor no puede volver a ofertar en esta solicitud; las demás ofertas siguen cerradas; chat de solo lectura |
| `adjudicada` | `cancelada` | cliente | Motivo obligatorio | Registra en `neg_cancelaciones` (parte `cliente`); sin penalidad en dinero |
| `en_ejecucion` | `finalizada` | proveedor (piloto del equipo) | Registro `cierre` válido y evidencias mínimas subidas (§4) | Congela `hectareas_ejecutadas` y `monto_final`; elimina la ubicación; emite `SolicitudFinalizada` |
| `finalizada` | `calificada` | sistema | El cliente registra su calificación | — |
| `finalizada`, `calificada` | `disputada` | cliente (plazo de disputa) o proveedor (por motivo permitido) | Una disputa por solicitud | Ver `disputas.md` |
| `disputada` | `finalizada` o `calificada` | sistema, al resolver | Resolución distinta de reembolso total | Aplica `monto_ajustado` como precio final vigente |
| `disputada` | `cancelada` | sistema, al resolver | Reembolso total a favor del cliente | — |

No existe cancelación desde `en_ejecucion`: lo no ejecutado se cierra con las hectáreas realmente ejecutadas y, si hay desacuerdo, con una disputa.

Plazos (parámetros): vigencia de la solicitud 7 días; renovación 30 días; disputa del cliente 72 horas desde `finalizada`; calificación 7 días desde `finalizada`.

Procesos programados: `negociacion:procesar` cada minuto (vence ofertas, expira solicitudes, reevalúa `con_ofertas` y `abierta`; idempotente, `withoutOverlapping`, `onOneServer`) y `seguimiento:avisar-inactividad` cada hora (avisa a las partes si una solicitud `en_ejecucion` no tiene avance en `seguimiento.inactividad_horas`, 72 por defecto).

## 2. Máquina de estados de la oferta

`MaquinaEstadosOferta` es la única clase que escribe `neg_ofertas.estado`.

| De | A | Actor | Condición |
|---|---|---|---|
| `vigente` | `aceptada` | contraparte del autor | Aceptación atómica; adjudica |
| `vigente` | `reemplazada` | contraparte del autor | Contraoferta: crea la oferta siguiente `vigente` |
| `vigente` | `rechazada` | contraparte del autor | Cierra la negociación; el proveedor no puede abrir otra en esa solicitud |
| `vigente` | `retirada` | autor | Solo mientras está `vigente` |
| `vigente` | `vencida` | sistema | `vence_en` pasó |
| `vigente` | `cerrada` | sistema | Solicitud adjudicada a otra oferta, cancelada o expirada |

Reglas:

1. La **primera oferta** de una negociación la crea el proveedor (`POST /solicitudes/{id}/ofertas`) con `numero = 1`. Tipo `acepta_propuesta`: el precio es exactamente `precio_ha_propuesto`. Tipo `contraoferta`: el precio es el que indica, distinto de la propuesta.
2. Cada oferta siguiente la crea la contraparte del autor de la vigente, con `numero + 1`. El autor alterna: impar proveedor, par cliente.
3. `numero = negociacion.max_ofertas_por_negociacion` (5) marca `es_definitiva = true`: no admite contraoferta, solo aceptar o rechazar.
4. Una sola oferta `vigente` por `(solicitud_id, proveedor_id)` y una sola `aceptada` por solicitud (índices únicos parciales).
5. `vence_en = min(ahora + vigencia_oferta_horas, vence_en de la solicitud)`; en solicitudes `abierta` o `con_ofertas`.
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
- El cliente puede cambiar `precio_ha_propuesto` con `POST /solicitudes/{id}/precio` mientras la solicitud está `abierta` o `con_ofertas`. Las ofertas vigentes no cambian; se notifica a los proveedores con negociación vigente.
- Precio de referencia (`GET /referencias/precio?tipo_aplicacion=&zona_id=&macrorregion=`): guía, no límite. Busca de lo específico a lo general (zona, macrorregión, general del tipo) y responde `precio_ha` con `nivel` (`zona`, `macrorregion`, `general` o `respaldo`). Lo consultan el cliente al proponer y el piloto al ofertar; los valores son datos de `sol_precios_referencia` que el administrador ajusta.

### 3.2 Quién puede ofertar
Cuenta de proveedor (titular o piloto) con proveedor `aprobado`, al menos un equipo apto (piloto y dron aprobados, dron con fotos aprobadas, documentos vigentes), sin suspensión. El `equipo_id` de la oferta debe ser de su proveedor y apto. Un proveedor no oferta sobre solicitudes de su propia cuenta de cliente.

### 3.3 Alcance del mapa
Todo piloto habilitado ve todas las solicitudes `abierta` y `con_ofertas` de Santa Cruz; los filtros son del usuario, no restricciones. No hay radio fijo de operación.

### 3.4 Qué ve cada parte en cada estado

| Dato | Piloto antes de ofertar | Proveedor con oferta (no adjudicada) | Cliente | Proveedor adjudicado | Otro proveedor tras adjudicar |
|---|---|---|---|---|---|
| Lotes (polígonos), hectáreas, cultivo, tipo de aplicación, producto, fecha, accesos, observaciones, estado del terreno, fotos del terreno | Sí | Sí | Sí | Sí | No (la solicitud sale del mapa) |
| Precio propuesto | Sí | Sí | Sí | Sí | No |
| Cantidad de ofertas vigentes | Sí | Sí | Sí | — | — |
| Precios de las ofertas de otros proveedores | No | No | Sí | No | No |
| Nombre, teléfono y documento del cliente, referencia textual de dirección | No | No | — | Sí | No |
| Nombre, foto, calificación y servicios del proveedor, modelo y fotos aprobadas del dron, distancia | — | — | Sí, por oferta | — | — |
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

Precio final confirmado por el cliente: el cliente paga, así que tiene la última palabra sobre el precio final (`hectareas_ejecutadas` × `precio_ha_acordado`, congelado en `monto_final`): lo confirma o abre una disputa. `POST /solicitudes/{id}/precio-final/confirmar` (sin cuerpo) lo escribe un solo rol, el cliente de la solicitud; el proveedor responde 403 `precio_final_solo_cliente` y una solicitud ajena 404. Solo con la solicitud `finalizada` o `calificada` (el piloto ya cerró con las hectáreas ejecutadas; si no, o con una disputa abierta, 409 `estado_invalido`). El servidor copia hectáreas, precio y monto del cierre en `neg_precios_finales_confirmados`; es único por solicitud e idempotente (201 la primera vez, 200 el reintento con el mismo registro) y lo confirmado no se reescribe. No cambia el estado, no mueve dinero y no cierra el plazo de disputa. La bitácora registra al actor y se avisa a las cuentas del proveedor adjudicado (`precio_final_confirmado`). Ambas partes lo leen con `GET /solicitudes/{id}/precio-final` (`precio_final: null` mientras el piloto no cierre; con `confirmado` y `confirmado_en`).

Respuesta por registro: 201 si se aplicó, 200 con el mismo registro si el `uuid_cliente` ya existía (duplicado), o 404, 409 o 422 con el `codigo` del motivo (`solicitud_ajena`, `estado_invalido`, `hectareas_exceden_tolerancia`, `correccion_sin_motivo`).

Finalizar no exige ningún otro tipo de registro operativo: solo `cierre` y las evidencias mínimas.

## 5. Mensajes

- Solo entre el cliente y la cuenta de proveedor adjudicada (titular y piloto del equipo), desde `adjudicada`. Lectura y escritura hasta el cierre del plazo de calificación (7 días tras `finalizada`) o mientras haya una disputa abierta; después, solo lectura.
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
  "nuevas_solicitudes": { "activo": true, "zonas": [3, 7], "macrorregiones": ["chiquitania"], "hectareas_minimas": "50.0000" },
  "ofertas": true,
  "mensajes": true,
  "avance": true,
  "calificaciones": true
}
```

- `nuevas_solicitudes` aplica solo a cuentas de proveedor. `zonas` y `macrorregiones` vacías significan todas. Por defecto activo, todas las zonas, sin mínimo de hectáreas.
- Las categorías obligatorias (`SolicitudAdjudicada`, cierre, precio final confirmado, expiración, cuenta y aprobación, disputas, emergencias) no se pueden desactivar y no aparecen en las preferencias.
- `POST /dispositivos` registra `{ token, plataforma: android|ios }` del push (201 la primera vez, 200 si ya estaba; si el token estaba a nombre de otra cuenta pasa a la actual); `DELETE /dispositivos/{token}` lo retira al cerrar sesión (404 si no existe o es de otra cuenta). El token nunca se devuelve ni se escribe en logs ni en la bitácora. El envío al sistema operativo requiere configurar el proveedor de push (credencial fuera del repositorio); mientras no esté, el aviso vive en la lista `GET /notificaciones` y se consulta por sondeo.
- Al abrir una solicitud se envía un aviso a cada cuenta de proveedor aprobada cuyas preferencias coincidan con la zona y las hectáreas. Una solicitud avisa a una cuenta una sola vez.

## 8. Mapa de solicitudes abiertas

`GET /solicitudes-abiertas` (rol proveedor, solo cuentas habilitadas; si no, 403 `proveedor_no_habilitado`):

| Parámetro | Significado |
|---|---|
| `bbox` | `oeste,sur,este,norte` del encuadre del mapa |
| `zona_id`, `macrorregion` | Filtro de zona o de región |
| `hectareas_min`, `hectareas_max` | Rango de hectáreas solicitadas |
| `precio_ha_min`, `precio_ha_max` | Rango del precio propuesto |
| `fecha_hasta` | Ventana deseada que empieza antes de esa fecha |
| `cultivo`, `tipo_aplicacion` | Filtros de contenido |
| `orden` | `distancia` (usa `lat` y `lon` del piloto, que no se guardan), `precio_ha`, `hectareas`, `reciente` |
| `cursor`, `limite` | Paginación por cursor (máximo 100) |

Cada elemento: `id`, `centro` (`latitud`, `longitud` del centroide de los lotes), `hectareas_solicitadas`, `precio_ha_propuesto`, `zona`, `macrorregion`, `cultivo`, `tipo_aplicacion`, `ventana_desde`, `ventana_hasta`, `ofertas_vigentes`, `mi_negociacion` (estado de su oferta o `null`), `distancia_km`, `publicada_en`, `vence_en`.

`GET /solicitudes-abiertas/resumen-zonas` (mismos filtros): por zona y macrorregión, `solicitudes`, `hectareas_total`, `precio_ha_promedio`, `centro`. Responde la pregunta «cuántos clientes y cuántas hectáreas hay en los valles frente a la Chiquitania».

`GET /solicitudes-abiertas/{id}`: detalle previo a ofertar con los datos de la primera columna de §3.4 (polígonos GeoJSON de cada lote, estado del terreno, fotos con URL firmada de corta vida). No incluye datos del cliente.

## 9. Registro del proveedor, datos de la persona y dron con fotos

- `POST /proveedores/registro`: cuenta, `tipo` (`independiente` | `tercero`), datos de la persona (`nombres`, `apellidos`, `documento_identidad`, `telefono`, `fecha_nacimiento`), `zona_base_id`, datos del piloto (`licencia_numero`, `licencia_vence`) y datos del dron (`marca`, `modelo`, `anio`, `numero_serie`, `capacidad_tanque_l`, `ancho_pasada_m`). Idempotente por `uuid_cliente`. Queda `pendiente`.
- `PUT /perfil/foto` y `DELETE /perfil/foto` (foto de perfil de la cuenta del token: cliente, titular o piloto; menos de 300 KB). La imagen se entrega por una URL firmada de corta vida (`GET /personas/{usuario}/foto`, sin token): `foto_url` viaja en `GET /auth/sesion` para la propia cuenta y en la tarjeta del proveedor (`proveedor.foto_url`, la del piloto del equipo o, si no tiene, la del titular) que ve el cliente al comparar ofertas.
- `GET /proveedores/mi` y `GET /proveedores/drones`: el titular (`proveedores.cuenta.gestionar`) gestiona su proveedor y sus drones; el piloto de un proveedor, incluido el piloto propio de Agrocom (`proveedores.cuenta.ver`), los ve en solo lectura: el proveedor al que pertenece (sin documento fiscal) y los drones de sus equipos vigentes. Un rol sin ninguno de los dos permisos responde 403, y nadie ve el proveedor ni los drones de otro.
- `POST /proveedores/drones/{id}/fotos` (multipart; campos `tipo`, `archivo`); `GET` lista con estado; `DELETE /proveedores/drones/{id}/fotos/{foto}` solo mientras `en_revision` o `rechazada`.
- Fotos obligatorias para aprobar el dron: las del parámetro `flota.fotos_obligatorias` (`frontal`, `lateral`, `placa_serie`). El administrador aprueba o rechaza cada foto con motivo en `panel/drones-flota`.
- La oferta y el comparador del cliente muestran del dron: marca, modelo, año, capacidad y fotos `aprobada`.
- Fotos del terreno: `POST /terrenos/{id}/fotos` (multipart `archivo` y `descripcion` opcional, menos de 300 KB, hasta 8 por terreno) y `DELETE /terrenos/{id}/fotos/{foto}`, solo del cliente dueño. Las fotos viajan en `terreno.fotos` del terreno y de la solicitud del cliente, y en `terreno.fotos` del detalle `GET /solicitudes-abiertas/{id}`, cada una con una URL firmada de corta vida (`GET /terrenos/fotos/{foto}/archivo`, sin token).

## 10. Recursos nuevos, cambiados y retirados

### Nuevos
`GET /solicitudes-abiertas`, `GET /solicitudes-abiertas/resumen-zonas`, `GET /solicitudes-abiertas/{id}`, `POST /solicitudes/{id}/ofertas`, `GET /solicitudes/{id}/ofertas`, `GET /ofertas/{id}`, `POST /ofertas/{id}/contraofertar`, `POST /ofertas/{id}/retirar`, `POST /solicitudes/{id}/precio`, `POST /solicitudes/{id}/renovar`, `GET /solicitudes/{id}/avance`, `POST /solicitudes/{id}/{inicio|avance|cierre|evidencias}`, `GET /solicitudes/{id}/ubicacion-piloto`, `GET /solicitudes/{id}/precio-final`, `POST /solicitudes/{id}/precio-final/confirmar`, `PUT /ubicacion`, `GET|POST /solicitudes/{id}/mensajes`, `POST /solicitudes/{id}/mensajes/leidos`, `POST /solicitudes/{id}/pago-confirmado`, `GET|PUT /notificaciones/preferencias`, `POST /dispositivos`, `DELETE /dispositivos/{token}`, `POST /terrenos/{id}/fotos`, `DELETE /terrenos/{id}/fotos/{foto}`, `GET /terrenos/fotos/{foto}/archivo`, `GET /referencias/precio`, `POST|GET|DELETE /proveedores/drones/{id}/fotos`, `PUT|DELETE /perfil/foto`, `GET /personas/{usuario}/foto`, `GET /solicitudes/{id}/pago-confirmado`; registros `inicio`, `avance`, `cierre` y `mensaje`.

### Cambiados
- `POST /solicitudes`, `PUT /solicitudes/{id}`: reciben `precio_ha_propuesto`, varios terrenos, `observaciones`; ya no calculan tarifa ni recargos. `GET /solicitudes/{id}/estimacion` pasa a devolver el monto estimado con el precio propuesto.
- `POST /solicitudes/{id}/publicar`: deja la solicitud `abierta` y dispara el aviso a pilotos.
- `GET /solicitudes`, `GET /solicitudes/{id}`: estados nuevos, `precio_ha_acordado`, `monto_estimado`, `hectareas_ejecutadas`, `porcentaje_avance`, `monto_final`, `oferta_adjudicada`, `proveedor` (con contacto solo adjudicado).
- `GET /ofertas`, `GET /ofertas/{id}`, `POST /ofertas/{id}/aceptar`, `POST /ofertas/{id}/rechazar`: pasan a ser de la negociación y sirven a ambos roles; el lado lo decide el rol del token.
- `POST /solicitudes/{id}/cancelar`: sin penalidad; registra en `neg_cancelaciones` si la solicitud estaba adjudicada. `POST /solicitudes/{id}/cancelar-proveedor` reabre la solicitud (§1).
- `GET /reputacion`: calificación, tasa de cancelación y alertas; sin sanciones ni tasa de aceptación.
- `GET /sync/catalogo`: `servicios_asignados` pasa a ser las solicitudes `adjudicada` y `en_ejecucion` del equipo con lotes, datos de contacto y precio acordado; suma `mensajes`.
- Disputas: motivos §8.3 de `marketplace.md`; el resultado ajusta `monto_ajustado`, sin comisión.
- `POST /proveedores/registro`, `/proveedores/drones`: datos de persona y dron (§9).

### Retirados
`PUT|GET /disponibilidad` y la noción de equipo en línea; `POST /solicitudes/{id}/conformidad` y `POST /solicitudes/{id}/acta/firmar` (el cierre lo marca el piloto y el cliente puede disputar); la penalidad de cancelación, la comisión (`comision_porcentaje`, `comision_monto`, estado de cuenta, bloqueo por comisión vencida) y las sanciones automáticas (`asg_sanciones`, tasa de aceptación).

## 11. Datos existentes

Ninguna migración borra datos; toda tabla o columna que sale de `public` se mueve a un esquema `respaldo_*` (`ALTER TABLE … SET SCHEMA` o copia previa a la columna) con `down()` que la restaura, y se verifica contra PostgreSQL. Los esquemas `respaldo_*` los elimina la migración `2026_12_04_100100_elimina_esquemas_de_respaldo` (el sistema no conserva datos retirados).

| Origen | Destino |
|---|---|
| Estados de la solicitud: `publicada`, `en_asignacion`, `sin_proveedor`, `asignada`, `en_camino`, `suspendida_por_clima`, `ejecutada`, `completada`, `en_disputa` | `abierta`, `abierta`, `expirada`, `adjudicada`, `adjudicada`, `en_ejecucion`, `finalizada`, `finalizada` (o `calificada` si el cliente ya calificó), `disputada` |
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
- **Replay**: `inicio`, `avance`, `cierre` y `mensaje` aplicados dos, tres o diez veces, en orden y en desorden parcial, dejan la base idéntica.
- **Decimales**: `monto_estimado` y `monto_final` exactos con casos de redondeo.
- **Contrato**: cada ruta nueva está en `docs/api/openapi.yaml` con sus ejemplos.
