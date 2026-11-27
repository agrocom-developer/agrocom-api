# Contrato de negociación: estados, reglas y recursos

**Agrocom SRL · Vigente · documento de trabajo para implementar API, panel y apps**

Resume con precisión lo que `marketplace.md` define, para quien implementa. Si este documento y `docs/api/openapi.yaml` difieren, manda el OpenAPI una vez generado; si difieren de `marketplace.md`, se corrige este documento. Todos los parámetros viven en `config/negociacion.php` y `config/seguimiento.php` (ADR 0016) salvo que se indique otro archivo. Importes y hectáreas en decimal exacto (`BigDecimal` en el servidor, `decimal` en Dart); el redondeo de importes es a 2 decimales, mitad hacia arriba, solo al presentar o congelar el monto.

## 1. Máquina de estados de la solicitud

Toda transición pasa por `MaquinaEstadosSolicitud` (invariante 7), con fila de historial (actor, origen, motivo) y bloqueo de la fila de la solicitud.

| De | A | Actor / origen | Guarda | Efecto |
|---|---|---|---|---|
| `borrador` | `abierta` | cliente | Terrenos propios con `departamento_codigo` y `provincia_codigo` (la provincia, del departamento), todos en el mismo departamento; `hectareas_solicitadas > 0` y no mayores a las del terreno, `precio_ha_propuesto > 0` y no mayor al máximo de sanidad, fecha deseada, si la hay, no pasada y dentro de `anticipacion_maxima_dias`, `requisitos` válidos, `alcance` válido (`departamento` o `ampliado`, §3.11), cuenta con teléfono | Congela departamento, provincia, municipio y comunidad; abre la oleada (paso 0); emite `SolicitudAbierta`. La solicitud no tiene vencimiento |
| `borrador` | `cancelada` | cliente | — | Sin registro |
| `abierta` | `con_ofertas` | sistema | Una aceptación pasa de la cola a la bandeja (`LiberarCola`, §3.8): hay al menos una negociación `en_bandeja` o `en_conversacion` | Ocupa una casilla del cupo; el cliente la ve en su bandeja. No oculta la solicitud ni pausa la oleada |
| `con_ofertas` | `abierta` | sistema | La última negociación que ocupaba casilla deja de ocuparla (rechazada, cerrada, retirada, vencida) y la cola no tiene a quién liberar | — |
| `abierta`, `con_ofertas` | `en_acuerdo` | cliente | Al menos una negociación `en_conversacion` | Marca la solicitud firme: la oleada queda `detenida`, no se avisa a más proveedores, la cola se congela y la solicitud sale del mapa salvo para quienes tienen una negociación en ella (§3.8) |
| `en_acuerdo` | `abierta` o `con_ofertas` | cliente (`acuerdo/reabrir`) o sistema | El cliente vuelve a buscar, o cae la última conversación sin acuerdo (aviso `acuerdo_deshecho`) | La oleada vuelve al estado que tenía y se libera la cola; `con_ofertas` si queda alguna negociación en bandeja o conversación, si no `abierta` |
| `abierta`, `con_ofertas`, `en_acuerdo` | `adjudicada` | cliente o proveedor | Se acepta la oferta vigente de la contraparte; equipo apto y libre (ver §3.6) | Fija `precio_ha_acordado`, `monto_estimado`, `proveedor_id`, `equipo_id`, `oferta_adjudicada_id`; la negociación pasa a `adjudicada` y las demás (también las de la cola) a `cerrada` con sus ofertas vigentes; abre contacto y chat; emite `SolicitudAdjudicada` |
| `abierta`, `con_ofertas`, `en_acuerdo` | `cancelada` | cliente | Motivo opcional | Cierra negociaciones, ofertas vigentes y la oleada; sin registro de cancelación |
| `adjudicada` | `en_ejecucion` | proveedor (piloto del equipo) | Registro `inicio` válido | Emite `EjecucionIniciada` |
| `adjudicada` | `abierta` | proveedor | Motivo obligatorio | Libera proveedor y equipo; registra en `neg_cancelaciones` y `tasa_cancelacion`; el proveedor no puede volver a ofertar en esta solicitud; las demás ofertas siguen cerradas; chat de solo lectura |
| `adjudicada` | `cancelada` | cliente | Motivo obligatorio | Registra en `neg_cancelaciones` (parte `cliente`); sin penalidad en dinero |
| `en_ejecucion` | `finalizada` | proveedor (piloto del equipo) | Registro `cierre` válido y evidencias mínimas subidas (§4) | Congela `hectareas_ejecutadas` y `monto_final`; elimina la ubicación; emite `SolicitudFinalizada` |
| `finalizada` | `calificada` | sistema | El cliente registra su calificación | — |
| `finalizada`, `calificada` | `en_reclamo` | cliente (plazo de reclamo) o proveedor (por motivo permitido) | Un reclamo por solicitud | Ver `reclamos.md` |
| `en_reclamo` | `finalizada` o `calificada` | sistema, al resolver | Resolución distinta de reembolso total | Aplica `monto_ajustado` como precio final vigente |
| `en_reclamo` | `cancelada` | sistema, al resolver | Reembolso total a favor del cliente | — |

No existe cancelación desde `en_ejecucion`: lo no ejecutado se cierra con las hectáreas realmente ejecutadas y, si hay desacuerdo, con un reclamo.

La solicitud **no vence sola**: sigue `abierta`, `con_ofertas` o `en_acuerdo` hasta que se adjudica o el cliente la cancela (ADR 0045, punto 20); no hay estado `expirada` ni renovación. «En acuerdo» es una decisión explícita del cliente y no un cálculo; no se usa la palabra «tomada».

Plazos (parámetros): vigencia de la oferta (72 horas por defecto, contadas desde que llega a la bandeja); cupo de negociaciones del cliente 3; anticipación máxima de la fecha deseada 365 días; tope de la fecha límite de una solicitud urgente 14 días; aviso al cliente de una solicitud sin respuesta cada 7 días; reclamo del cliente 72 horas desde `finalizada`; calificación 7 días desde `finalizada`.

Procesos programados: `negociacion:procesar` cada minuto (vence ofertas, reevalúa `con_ofertas` y `abierta`, barre la cola y libera lo que corresponda (§3.8), avisa al cliente cuánto lleva sin respuesta una solicitud y avanza y relanza las oleadas de aviso, §7.1; idempotente, `withoutOverlapping`, `onOneServer`) y `seguimiento:avisar-inactividad` cada hora (avisa a las partes si una solicitud `en_ejecucion` no tiene avance en `seguimiento.inactividad_horas`, 72 por defecto).

## 2. Máquina de estados de la oferta

`MaquinaEstadosOferta` es la única clase que escribe `neg_ofertas.estado`.

| De | A | Actor | Condición |
|---|---|---|---|
| `vigente` | `aceptada` | contraparte del autor | Aceptación atómica; adjudica |
| `vigente` | `reemplazada` | contraparte del autor | Contraoferta: crea la oferta siguiente `vigente` |
| `vigente` | `rechazada` | contraparte del autor | Cierra la negociación (el cliente «rechaza» una aceptación o «cierra la conversación»: es la misma transición, y también la que provoca bloquear a la otra parte, ADR 0047); el proveedor no puede abrir otra en esa solicitud |
| `vigente` | `retirada` | autor | Solo mientras está `vigente`; también si la negociación está `en_cola` |
| `vigente` | `vencida` | sistema | `vence_en` pasó (no corre mientras la negociación está `en_cola`) |
| `vigente` | `cerrada` | sistema | Solicitud adjudicada a otra oferta o cancelada (incluye las de la cola) |

Reglas:

1. La **primera oferta** de una negociación (la aceptación del proveedor) la crea el proveedor (`POST /solicitudes/{id}/ofertas`) con `numero = 1`. Crea la negociación `en_cola` y `LiberarCola` la pasa a `en_bandeja` en la misma transacción si hay casilla y cumple las verificaciones (§3.8); la respuesta trae `negociacion.estado` (`en_cola` o `en_bandeja`) y, si espera por su propia disponibilidad, `espera_por_disponibilidad: true`. Tipo `acepta_propuesta`: el precio es exactamente `precio_ha_propuesto`. Tipo `contraoferta`: el precio es el que indica, distinto de la propuesta.
2. Cada oferta siguiente la crea la contraparte del autor de la vigente, con `numero + 1`. El autor alterna: impar proveedor, par cliente.
3. `numero = negociacion.max_ofertas_por_negociacion` (5) marca `es_definitiva = true`: no admite contraoferta, solo aceptar o rechazar.
4. Una sola oferta `vigente` por `(solicitud_id, proveedor_id)` y una sola `aceptada` por solicitud (índices únicos parciales).
5. `vence_en = ahora + vigencia_oferta_horas` desde que la negociación llega a la bandeja (nulo mientras está `en_cola`); en solicitudes `abierta`, `con_ofertas` o `en_acuerdo`. `vigencia_oferta_horas` admite cualquier cantidad de horas (720 son 30 días). La solicitud no tiene vencimiento propio. Cada oferta siguiente (contraoferta) vuelve a fijar su `vence_en`.
6. Un proveedor con una negociación cerrada por `vencida` o `retirada` puede abrir otra (una fila de negociación nueva, con su propio orden de llegada) mientras la solicitud siga ofertándose; tras `rechazada` por el cliente no. Con una negociación activa (`en_cola`, `en_bandeja` o `en_conversacion`) no puede abrir otra: 409 `negociacion_ya_existe`.
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
- **Total de solo lectura.** `monto_estimado` (hectáreas × precio por hectárea, decimal exacto, 2 decimales) lo calcula el servidor en cada oferta y en la solicitud y viaja en sus respuestas; ninguna petición lo recibe (si llega, se ignora). Es lo que la app presenta como «Total estimado».
- **Justificación del precio** (ADR 0049). `justificacion_precio`, texto opcional de hasta 500 caracteres, saneado (sin etiquetas, sin caracteres de control, espacios colapsados; vacío es nulo) y visible para la contraparte. El cliente la escribe en la solicitud (`POST /solicitudes`, `POST /solicitudes/{id}/precio`); el proveedor y el cliente, en cada oferta o contraoferta (`POST /solicitudes/{id}/ofertas`, `POST /ofertas/{id}/contraofertar`). Cada parte escribe solo su fila: la oferta no se edita. Más de 500 caracteres responde 422.
- El cliente puede cambiar `precio_ha_propuesto` con `POST /solicitudes/{id}/precio` mientras la solicitud no está adjudicada (`abierta`, `con_ofertas` o `en_acuerdo`). Las ofertas vigentes, también las de la cola, no cambian; se notifica a los proveedores con negociación vigente.
- Un equipo que no cumple un requisito del cliente (§3.7) puede ofertar.

### 3.2 Quién puede ofertar
Cuenta de proveedor (titular o piloto) en estado `aprobado`, al menos un equipo apto (piloto y dron aprobados, dron con fotos aprobadas, documentos vigentes), sin suspensión. El `equipo_id` de la oferta debe ser de su proveedor y apto. Un proveedor no oferta sobre solicitudes de su propia cuenta de cliente ni de otra cuenta de la misma persona. En un proveedor con pilotos vinculados oferta el **encargado** (titular, `proveedores.cuenta.gestionar`); un piloto solo si el encargado le delegó la oferta (`prv_pilotos.puede_ofertar`, apagada por defecto, `PUT /proveedores/pilotos/{id}/delegacion-oferta`); si no, 403 `oferta_no_delegada`. Un proveedor independiente oferta por sí mismo. La solicitud debe estar dentro de su alcance (§3.3 y §3.11): si no la ve, 409 `solicitud_no_disponible`. Si la cuenta no está `aprobada`, 403 `cuenta_no_aprobada` (distinto de `proveedor_no_habilitado`, que es no tener un equipo apto).

### 3.3 Alcance del mapa
No hay zonas, radio ni distancia. El único filtro geográfico es el departamento (§3.11). Un piloto habilitado ve una solicitud `abierta` o `con_ofertas` si tiene una negociación en ella (en cola, en la bandeja o en conversación) o si el cliente no lo rechazó, la solicitud está **dentro de su alcance** (trabaja en el departamento de la solicitud, o el cliente la amplió a otros departamentos) y, o bien ya se abrió a todos los elegibles del alcance (salió la súper ola, el último paso de la escalera; desde entonces también mientras la solicitud está `esperando`), o bien una oleada lo alcanzó. Que otro proveedor haya aceptado no cambia lo que ve el resto. Una solicitud `en_acuerdo` solo la ven quienes tienen una negociación en ella. Los filtros del mapa son del usuario, no restricciones; el filtro `departamento` solo elige entre los que ya ve. Ofertar sobre una solicitud que no ve, o que está `en_acuerdo`, responde 409 `solicitud_no_disponible`. Una cuenta de proveedor aún no habilitada (proveedor `pendiente` o sin equipo apto) no ve el mapa y recibe 403 `proveedor_no_habilitado`; sí puede pedir el contador del §8.

### 3.4 Qué ve cada parte en cada estado

| Dato | Piloto antes de ofertar | Proveedor con oferta (no adjudicada) | Cliente | Proveedor adjudicado | Otro proveedor tras adjudicar |
|---|---|---|---|---|---|
| Lotes (polígonos), hectáreas, cultivo, tipo de aplicación, producto, fecha, accesos, observaciones, estado del terreno, fotos del terreno | Sí | Sí | Sí | Sí | No (la solicitud sale del mapa) |
| Precio propuesto | Sí | Sí | Sí | Sí | No |
| Cantidad de negociaciones en bandeja o en conversación (como mucho el cupo) | Sí | Sí | Sí | — | — |
| Posición en la cola, cuántas aceptaciones esperan y quiénes son | No | No | No | — | — |
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
`requisitos` de la solicitud es un objeto por clave de criterio (`{"dron_capacidad_min_l": "20.00"}`; decimales como cadena). Cada clave la valida su criterio de alcance (ADR 0045); una clave desconocida se rechaza al crear con 422 `requisito_desconocido`. Al ofertar el servidor evalúa los requisitos contra el equipo y guarda en la oferta `incumple` (se recalcula al llegar a la bandeja, por si el cliente ajustó los requisitos mientras esperaba en la cola) (lista de claves que no cumple, vacía si cumple todo). No bloquea. El comparador y el aviso de oferta recibida muestran qué requisito no se cumple y, si todas las ofertas vigentes incumplen, lo dicen de forma explícita.

### 3.8 Negociación asíncrona: bandeja, cupo, cola, «En acuerdo» y «Esperando piloto»

La negociación no es un chat en vivo: la solicitud se envía, los proveedores responden cuando pueden y cada aceptación espera en la bandeja del cliente (ADR 0045, puntos 15 a 17 y 30 a 36).

**Negociación y estados.** Una negociación es la de un proveedor con el cliente sobre una solicitud (`neg_negociaciones`; una activa por `(solicitud, proveedor)`, y dentro de ella una sola oferta `vigente`). Su estado:

| Estado | Qué es | Ocupa casilla | Lo ve el cliente | Sale a |
|---|---|---|---|---|
| `en_cola` | Aceptó y espera casilla o disponibilidad | No | No | `en_bandeja`, `retirada`, `cerrada` |
| `en_bandeja` | Llegó a la bandeja; el cliente aún no respondió | Sí | Sí (`nueva` hasta que fija `vista_en`) | `en_conversacion`, `adjudicada`, `rechazada`, `retirada`, `vencida`, `cerrada` |
| `en_conversacion` | El cliente respondió; hay rondas | Sí | Sí | `adjudicada`, `rechazada`, `retirada`, `vencida`, `cerrada` |
| `adjudicada` | Una parte aceptó la oferta vigente de la otra | — | Sí | Terminal |
| `rechazada` | El cliente rechazó o cerró; el proveedor no puede volver a ofertar | No | Historial | Terminal |
| `retirada`, `vencida` | El proveedor retiró; la oferta venció | No | Historial | Terminal (puede aceptar de nuevo: negociación nueva) |
| `cerrada` | Se adjudicó a otro proveedor, se canceló la solicitud o la cuenta quedó en baja | No | Historial | Terminal |

**Cupo.** El cliente ve y conversa con hasta `negociacion.cupo_negociaciones` (3) negociaciones a la vez: las `en_bandeja` y `en_conversacion`. Cada una ocupa una casilla (`neg_negociaciones.casilla`, `UNIQUE (solicitud_id, casilla) WHERE casilla IS NOT NULL`). Al cliente nunca le llegan más aceptaciones que el cupo; las que sobran esperan `en_cola`, sin avisarle ni mostrarle cuántas son.

**`LiberarCola(solicitud)`.** Se ejecuta, dentro de la transacción del hecho y con la solicitud bloqueada, cuando (a) llega una aceptación (toda aceptación nace `en_cola` y se libera en el acto si hay casilla), (b) se cierra una negociación que ocupaba casilla (rechazo o cierre del cliente, retiro, vencimiento), (c) un proveedor con una aceptación en la cola vuelve a `disponible`, (d) la solicitud sale de `en_acuerdo`, y (e) en la pasada del reloj, como red de seguridad sobre las solicitudes con casillas libres y la cola no vacía. Procedimiento:
1. Bloquea la solicitud (primero), luego sus negociaciones y ofertas. Si la solicitud no está `abierta` ni `con_ofertas` no libera (`en_acuerdo`: cola congelada; `adjudicada` o `cancelada`: la cola pasa a `cerrada`).
2. `libres = cupo − (en_bandeja + en_conversacion)`; si es 0 termina.
3. Recorre la cola por prioridad: `propio` primero; luego mayor calificación (con menos de `calificaciones_minimas_para_contar` va al final de su grupo); luego `aceptada_en` y `id`. El precio no cuenta.
4. Verifica de cada una: proveedor aprobado y equipo de la oferta apto; el interruptor de la cuenta que ofreció y el de su empresa en `disponible`; la oferta sigue `vigente`; el cliente no lo rechazó; la solicitud sigue vigente; la solicitud sigue dentro de su alcance (si el cliente retiró la ampliación y el proveedor es de otro departamento, §3.11). Si falla la disponibilidad, el equipo o el alcance, **se salta sin descartarla** (queda en cola para el siguiente evento).
5. Las que cumplen, hasta `libres`: `UPDATE … SET estado = 'en_bandeja' WHERE id = ? AND estado = 'en_cola'`, con casilla libre, `liberada_en`, `vence_en` de la oferta, `incumple` recalculado contra los requisitos vigentes; si la solicitud estaba `abierta`, pasa a `con_ofertas` (historial, origen `sistema`).
6. Tras confirmar, avisos con `UNIQUE (usuario_id, clave_evento)`: al cliente `aceptacion_en_bandeja:{negociacion}` («Tienes N aceptaciones nuevas») y al proveedor `oferta_en_revision:{negociacion}` («El cliente ya está viendo tu oferta»). Un aviso no entregado queda con `aviso_entregado_en` nulo y la siguiente pasada lo reintenta.
Idempotencia: correr `LiberarCola` una, dos o diez veces, o recibir los eventos de cierre en cualquier orden, deja las mismas filas; solo las filas realmente actualizadas se avisan; la casilla única hace que dos rechazos simultáneos nunca superen el cupo.

**Recursos del cliente.** `GET /bandeja` (aceptaciones en bandeja o en conversación de todas sus solicitudes, con `nuevas`; nunca la cola), `GET /solicitudes/{id}/ofertas` (las de esa solicitud, con `negociacion {estado, nueva}` y `bandeja {cupo, ocupadas, nuevas}`), `POST /ofertas/{id}/vista` (fija `vista_en`; idempotente) y las respuestas de siempre (`aceptar`, `contraofertar`, `rechazar`). Contraofertar pasa la negociación a `en_conversacion`. Cuando `ocupadas = cupo` la respuesta lo indica para que la app explique que conversa con el máximo a la vez.

**Lo que ve el proveedor.** Solo su negociación: `negociacion.estado` ∈ `en_cola` («Tu oferta está en cola»), `en_bandeja` («El cliente la está viendo»), `en_conversacion` y los cierres; `espera_por_disponibilidad` si espera por su propio interruptor. Ninguna respuesta al proveedor incluye posición, casilla, tamaño de la cola ni datos de otra negociación (prueba de seguridad obligatoria). El `ofertas_vigentes` del mapa cuenta solo bandeja y conversación.

**«En acuerdo».** `POST /solicitudes/{id}/acuerdo` (solo el cliente; solicitud `abierta` o `con_ofertas` con al menos una negociación `en_conversacion`; si no, 409 `estado_invalido` o `sin_conversacion`): la solicitud pasa a `en_acuerdo`, la oleada a `detenida` y los relanzamientos automáticos no corren; ya no se avisa a más proveedores; la solicitud sale del mapa salvo para quienes tienen una negociación; ofertar responde 409 `solicitud_no_disponible`; la cola se congela. Es idempotente. `POST /solicitudes/{id}/acuerdo/reabrir` («Volver a buscar») la devuelve a `abierta` o `con_ofertas`, reanuda la oleada en el estado que tenía y libera la cola. Si cae la última conversación (rechazo, retiro o vencimiento) la solicitud vuelve sola a `abierta`, se libera la cola y se avisa al cliente (`acuerdo_deshecho`). A los proveedores con una negociación activa (también `en_cola`) y a los rechazados no se les vuelve a avisar esa solicitud (criterios `sin_negociacion_activa` y `sin_rechazo_previo`); un proveedor que reaparece disponible días después y ve la solicitud puede aceptar mientras no esté en acuerdo.

**Esperando piloto.** Cuando sale la súper ola la oleada queda `esperando`, haya o no aceptaciones, y la solicitud sigue `abierta` o `con_ofertas`, visible para todo proveedor habilitado de su alcance (salvo los rechazados); el sistema no la desactiva ni la vence. Se muestra como «Esperando piloto» mientras no hay aceptaciones en bandeja ni en conversación. El cliente puede ajustar el precio (`POST /solicitudes/{id}/precio`) y los requisitos y la fecha deseada (`PUT /solicitudes/{id}/ajustes`, solo sin aceptaciones en bandeja ni en conversación), **buscar también en otros departamentos** (`PUT /solicitudes/{id}/alcance`, §3.11) y **relanzar** (`POST /solicitudes/{id}/busqueda/relanzar`: abre un ciclo nuevo desde el paso 0; solo con la oleada `esperando`, si no 409 `busqueda_no_esperando`). Como máximo un ciclo nuevo por día calendario, manual o automático (409 `relanzamiento_demasiado_pronto`).

**Relanzamiento automático.** `PUT /solicitudes/{id}/busqueda/programacion` con `dias` (días de la semana, ISO 1 a 7: mínimo 2 distintos, si no 422 `relanzamiento_dias_insuficientes`) y `hora` (`HH:MM`, hora de Bolivia, una por día, así que a lo más un relanzamiento diario). Si el cliente no la fijó rige `negociacion.oleadas.relanzamiento` (martes y viernes a las 07:00), o el diario de `negociacion.urgencia.relanzamiento` si la solicitud es urgente y vigente (§3.10). Si el cliente no relanza a mano, la pasada del reloj relanza cuando `proximo_relanzamiento_en` vence y la oleada está `esperando`; si en ese momento hay un ciclo en marcha o la solicitud está `en_acuerdo`, no hace nada y no se acumula. Tener conversaciones abiertas no lo impide.

**Etapa y búsqueda que ve el cliente.** La solicitud trae `etapa` (`buscando`, `con_aceptaciones`, `esperando_piloto` o `en_acuerdo`; se deduce del estado y de la oleada), `busqueda` (`estado` de la oleada, `ciclo`, `paso`, `avisados`: cantidad, nunca quiénes; `programacion` y `proximo_relanzamiento_en`) y `bandeja` (`cupo`, `ocupadas`, `nuevas`; nunca el tamaño de la cola). Trae también `alcance` y `alcance_ampliado_en` (§3.11) y, en `busqueda.sugerencias`, las claves que el servidor sugiere al cliente (`ampliar_alcance`, `subir_precio`, `ampliar_fecha`, `quitar_requisito_dron`; §3.11).

**Aviso de solicitud sin respuesta.** Cada `negociacion.pendiente.recordatorio_dias` (7), y solo mientras ningún proveedor aceptó, se avisa al cliente cuánto lleva sin respuesta («tu solicitud lleva N días sin respuesta: modifícala, búscala también en otros departamentos o retírala»; sin la segunda opción si ya está ampliada); no se avisa a los pilotos y nada se cancela ni vence solo. Si pasa `ventana_hasta` sin trato se le avisa y no se relanza sola hasta que actualice la fecha; la solicitud no se cancela. En una solicitud urgente vigente esto lo reemplaza la fecha límite (§3.10).

### 3.9 Orden de las ofertas
Las ofertas vigentes van siempre primero y, dentro de cada grupo, rige el orden pedido. `GET /solicitudes/{id}/ofertas?orden=` acepta `recomendado` (por defecto: proveedores `propio` primero; luego mayor calificación, con quien tiene menos de `negociacion.oleadas.calificaciones_minimas_para_contar` calificaciones al final de su grupo; luego menor `precio_ha`; luego la más antigua), `precio`, `calificacion` y `reciente`. No hay orden por distancia: no se calcula.

**Filtros del comparador** (ADR 0049), todos opcionales y combinables: `precio_ha_min`, `precio_ha_max`, `monto_max` (total estimado), `calificacion_min` (0 a 5), `tipo_proveedor` (`propio`, `independiente`, `tercero`) y `capacidad_tanque_min` (litros del dron de la oferta). Un valor inválido responde 422. Solo reducen la lista: no cambian lo que se ve de cada oferta ni agregan datos de contacto. La disponibilidad del proveedor no es un filtro del cliente (ADR 0045, punto 30).

### 3.10 Solicitudes urgentes
Decisión en el ADR 0045, punto K. Primer modelo; todo lo fino es revisable.

**Campos.** `urgente` (booleano), `fecha_limite` (fecha, día de Bolivia) y `motivo_urgencia` (`plaga`, `siembra`, `cosecha`, `clima`, `otro`; lista en `negociacion.urgencia.motivos`). Los reciben `POST /solicitudes`, `PUT /solicitudes/{id}` y `PUT /solicitudes/{id}/ajustes`. El servidor no deduce la urgencia: solo existe si el cliente la declara.

**Validación.**

| Caso | Respuesta |
|---|---|
| `urgente` sin `fecha_limite` | 422 `fecha_limite_requerida` |
| `fecha_limite` anterior a hoy | 422 `fecha_limite_pasada` |
| `fecha_limite` posterior a hoy más `negociacion.urgencia.dias_maximos` (14) | 422 `fecha_limite_fuera_de_rango`, con el máximo permitido |
| `ventana_desde` o `ventana_hasta` posterior a `fecha_limite` | 422 `ventana_posterior_a_fecha_limite` |
| `motivo_urgencia` fuera del catálogo | 422 `motivo_urgencia_desconocido` |
| Sin `urgente`, con `fecha_limite` o `motivo_urgencia` | Se ignoran y quedan nulos (CHECK en base de datos) |

La urgencia se puede declarar, cambiar o quitar mientras la solicitud no está `adjudicada` ni cancelada, también con aceptaciones en la bandeja (no cambia lo que los pilotos aceptaron). Cada cambio queda en la bitácora y se avisa a los proveedores con una negociación en curso, como con el precio.

**Vigencia.** La urgencia está vigente mientras `urgente` y hoy (en `America/La_Paz`) no supere `fecha_limite`; `dias_restantes` = `fecha_limite` − hoy (0 es «hoy es el último día»). La solicitud trae `urgencia {activa, fecha_limite, dias_restantes, vencida, motivo}`.

**Perfil de la oleada.** Cada ciclo guarda `neg_oleadas.perfil` (`normal` o `urgente`), fijado al abrirlo. El perfil `urgente` usa `negociacion.urgencia.intervalo_minutos` (15), la escalera `negociacion.urgencia.escalera` (paso 0 calificación 4,00; paso 1 sin mínimo; paso 2 súper ola sin requisito de dron) y, por defecto, el relanzamiento diario de `negociacion.urgencia.relanzamiento` (todos los días a las 07:00) si el cliente no fijó su programación. Se mantienen el máximo de un ciclo nuevo por día, la unicidad de avisos por ciclo y el no relajar aprobación, disponibilidad ni filtros del proveedor. Cada paso guarda `origen` y `parametros`.

**Cambio de la urgencia con un ciclo en marcha.** Al declararla, el ciclo no se reinicia: sigue con el perfil `urgente` desde el primer paso de la escalera acelerada que relaje más que lo ya ejecutado y `proxima_en = min(proxima_en, ahora + T urgente)`; con la oleada `esperando`, el relanzamiento sigue el horario y el tope diario. Al quitarla o vencer, sigue con el perfil `normal` en el primer paso que relaje más que lo ya ejecutado.

**Filtro del proveedor.** `recibir_urgentes` (booleano, activo por defecto) en `neg_preferencias.filtros`; criterio de alcance de origen `proveedor`, no relajable (clase `RecibirUrgentes`). Con `false` no se avisa de solicitudes urgentes a esa cuenta; el mapa no se oculta.

**Mapa.** `GET /solicitudes-abiertas` acepta `urgentes=1` y `orden=relevancia` (por defecto: urgentes vigentes primero por menos `dias_restantes`, luego `reciente`). Cada elemento y el detalle traen `urgente` y `urgencia {fecha_limite, dias_restantes, motivo}`.

**Sugerencias al cliente.** Cuando un ciclo de una urgente vigente llega a la súper ola sin aceptaciones (`negociacion.urgencia.sugerencia_ciclos`, 1), `busqueda.sugerencias` trae `ampliar_alcance` (primera; solo si la solicitud no está ampliada y hay proveedores en otros departamentos, §3.11), `subir_precio`, `ampliar_fecha` y, si hay requisito de dron, `quitar_requisito_dron`, y se avisa una vez por ciclo (`urgente_sin_aceptacion:{solicitud}:{ciclo}`). Sin recargo: ninguna sugerencia cambia el precio por sí sola.

**Fecha límite pasada.** Terminado el día sin adjudicar: la urgencia pasa a vencida, el ciclo en marcha se interrumpe (la oleada queda `esperando` sin `proximo_relanzamiento_en`), no se relanza y se avisa una vez al cliente (`fecha_limite_pasada`: «Pasó tu fecha límite»; no desactivable). La solicitud no se cancela ni vence y queda «Esperando piloto». Actualizar `fecha_limite` o quitar `urgente` recalcula `proximo_relanzamiento_en` y la reactiva. Adjudicada, `urgente` y `fecha_limite` solo informan (`GET /sync/catalogo`, `servicios_asignados`).

**Aviso forzado por el administrador.** `POST panel/solicitudes/{id}/avisar-a-todos` (permiso `solicitudes.solicitud.avisar_a_todos`; no es endpoint de la API de las apps): `motivo` obligatorio (mínimo 10 caracteres). Solo con la solicitud `abierta` o `con_ofertas` (`en_acuerdo`: 409 `solicitud_en_acuerdo`; otros estados: 409 `estado_invalido`). Abre `ciclo + 1` con `origen = admin` y un solo paso, la súper ola, para todos los elegibles de ese momento (incluidos los avisados en ciclos anteriores); nunca a `ocupado` o `en_trabajo` ni a quien excluyó la solicitud con sus filtros, rechazó el cliente o ya tiene una negociación activa, y solo dentro del alcance autorizado: el departamento de la solicitud, o todos si el cliente la amplió (el administrador no amplía el alcance). Supera el tope diario pero cuenta como `ultimo_relanzamiento_en`; entre dos acciones pasan al menos `negociacion.oleadas.aviso_manual.min_minutos` (60; 409 `aviso_manual_demasiado_pronto`). Bitácora: quién, cuándo, motivo, ciclo y cuántos se avisaron.

### 3.11 Alcance por departamento

ADR 0045, punto L. El mercado no tiene zonas: el único filtro geográfico es el departamento, y solo el cliente puede ampliar el alcance a otros departamentos. El departamento es el catálogo oficial `sol_departamentos`, de solo lectura; no es una zona administrable.

**Alcance base.** Una solicitud llega solo a proveedores habilitados que trabajan en su departamento. El criterio `departamento` (origen `sistema`, no relajable; clase `Departamento`) es verdadero si el proveedor declaró el departamento de la solicitud o si `alcance = ampliado`. Rige para los avisos de cada oleada, para el mapa (§3.3) y para ofertar. La escalera y la súper ola nunca lo relajan.

**Declaración del proveedor.** `PUT /proveedores/mi/departamentos` con `departamentos` (lista de códigos del catálogo, al menos uno; 422 `departamentos_requeridos` si viene vacía y 422 `departamento_desconocido` si un código no es del catálogo). Lo cambia el titular (`proveedores.cuenta.gestionar`); en una empresa es el encargado y rige para todos sus pilotos, que lo ven en `GET /proveedores/mi` sin poder cambiarlo (403). `POST /proveedores/registro` y el alta manual del panel lo piden. Se guarda en `prv_proveedor_departamentos`. Agregar un departamento no genera avisos de olas que ya pasaron; quitarlo saca del mapa lo que el proveedor no tiene en negociación. Cada cambio queda en la bitácora.

**Ampliación del cliente.** `PUT /solicitudes/{id}/alcance` con `alcance` = `ampliado` («Buscar también en otros departamentos») o `departamento`; idempotente y solo del cliente dueño (otro usuario, 404). También se envía al crear o editar la solicitud (`POST` y `PUT /solicitudes`). Estados: `borrador`, `abierta` y `con_ofertas` (también con aceptaciones en la bandeja); `en_acuerdo`, 409 `solicitud_en_acuerdo`; adjudicada o cancelada, 409 `estado_invalido`; otro valor, 422 `alcance_desconocido`. Fija o anula `alcance_ampliado_en` y deja la bitácora y el evento (`AlcanceAmpliado` o `AlcanceRestringido`).

| Qué ocurre | Efecto |
|---|---|
| Ampliar con la oleada `activa` | Pasada de ampliación en el acto: avisa a los proveedores de otros departamentos que cumplen los criterios del paso vigente y no recibieron aviso en el ciclo; los pasos siguientes ya los incluyen |
| Ampliar con la oleada `esperando` | Súper ola acotada a los otros departamentos, en el acto, dentro del ciclo vigente; no cambia el horario de relanzamiento ni cuenta contra el tope de un ciclo por día |
| Ampliar con la oleada `detenida` | No aplica: con la solicitud `en_acuerdo` responde 409; al volver a buscar, el cliente puede ampliar |
| Relanzamiento (ciclo nuevo) | Usa el alcance vigente desde el paso 0 |
| Restringir (`departamento`) | Deja de avisar a otros departamentos y saca la solicitud de su mapa, salvo de quienes tienen una negociación en ella; las negociaciones abiertas no se cierran y las de la cola quedan quietas (§3.8, `LiberarCola`) |
| Volver a ampliar | No avisa otra vez a quien ya recibió el aviso en ese ciclo |

La pasada de ampliación guarda su paso con `origen = ampliacion` y obedece las mismas barreras de idempotencia que las demás (`UNIQUE (solicitud_id, proveedor_id, ciclo)` de `neg_oleada_avisos`, clave `nueva_solicitud_abierta:{solicitud}:{ciclo}`, bloqueo en el orden solicitud, negociaciones, ofertas y oleada). Ampliar una, dos o diez veces, o ampliar, restringir y volver a ampliar, deja las mismas filas y los mismos avisos.

**Súper ola y `abierta_a_todos_en`.** Cubren a todos los elegibles dentro del alcance autorizado y no abren por sí solas a otros departamentos. Lo mismo vale para el relanzamiento, la urgencia (§3.10) y el aviso forzado del administrador.

**Qué ve el proveedor de otro departamento.** `en_otro_departamento` (booleano que calcula el servidor) en cada elemento y en el detalle de `GET /solicitudes-abiertas` y `departamento` con código y nombre; el aviso `nueva_solicitud_abierta` trae `datos.otro_departamento` y el departamento. Las apps muestran «En otro departamento: <nombre>» y no comparan departamentos. Antes de adjudicar ve lo mismo que cualquier proveedor (primera columna de §3.4) y nada del cliente (invariante 13). No necesita estar en ese departamento: el viaje y el costo se negocian y la oferta no lleva distancia.

**Sugerencias.** `ampliar_alcance` solo si `alcance = departamento` y existe al menos un proveedor habilitado que trabaje en otro departamento; nunca se informa cuántos ni quiénes. Solicitud normal: aparece cuando la oleada queda `esperando` sin aceptaciones, con `subir_precio` y, si corresponde, `ampliar_fecha` y `quitar_requisito_dron`. Solicitud urgente vigente: aparece primera cuando el primer ciclo llega a la súper ola sin aceptaciones (a los 30 minutos con la escalera acelerada, §3.10). La urgencia no amplía el alcance.

**Panel.** Ve el departamento, el alcance y desde cuándo está ampliada, filtra por departamento y por alcance, y las métricas se agrupan por departamento y cuentan las solicitudes ampliadas y las aceptaciones de otro departamento. No hay acción de panel para ampliar (el administrador puede sugerirlo al cliente por teléfono).

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

Pago confirmado: el pago ocurre fuera de la aplicación (ADR 0034). `POST /solicitudes/{id}/pago-confirmado` (`uuid_cliente`, `metodo` opcional, `nota` opcional) lo escribe un solo rol, el titular del proveedor adjudicado (quien cobra); el piloto que no es titular responde 403 `pago_solo_titular` y el cliente 403 `pago_solo_proveedor`. Solo con la solicitud `finalizada`, `calificada` o `en_reclamo` (si no, 409 `estado_invalido`). El monto lo fija el servidor con el monto final congelado. Es único por solicitud e idempotente por `uuid_cliente` (201 la primera vez, 200 el reintento); otro `uuid_cliente` después responde 409 `pago_ya_confirmado` y lo confirmado no se reescribe. No cambia el estado. Ambas partes lo leen con `GET /solicitudes/{id}/pago-confirmado` (`pago: null` si aún no se informó); una solicitud ajena responde 404.

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
    "tipos_aplicacion_excluidos": ["desecante"],
    "recibir_urgentes": true
  },
  "ofertas": true,
  "mensajes": true,
  "avance": true,
  "calificaciones": true
}
```

- `nuevas_solicitudes` aplica solo a cuentas de proveedor. Los filtros (`hectareas_minimas`, `departamentos_excluidos` con el código del catálogo, `tipos_aplicacion_excluidos`) son preferencias del proveedor que gobiernan solo los avisos; vacíos o ausentes no restringen. Se guardan en `neg_preferencias.filtros` y una clave desconocida se rechaza al guardar con 422. La respuesta incluye `filtros_disponibles` (lista de `{clave, tipo}` con las claves que el servidor conoce y el tipo de su valor: `decimal`, `departamentos` o `tipos_aplicacion`) para que la app pinte los que sabe. Enviar un filtro como `null` o vacío lo quita y lo no enviado se conserva. Un mínimo de hectáreas guardado antes del mecanismo sigue rigiendo hasta que el proveedor vuelva a guardar. El departamento y el tipo de aplicación de la solicitud los mira el aviso; mientras la solicitud no traiga departamento, un filtro de departamento no excluye. Por defecto activo y sin filtros. `departamentos_excluidos` es provisional: el ADR 0045 lo reemplaza por los departamentos donde trabaja el proveedor, que son su alcance y no un filtro de aviso (§3.11).
- Las categorías obligatorias (`SolicitudAdjudicada`, cierre, precio final confirmado, cuenta y aprobación, reclamos, emergencias) no se pueden desactivar y no aparecen en las preferencias.
- `POST /dispositivos` registra `{ token, plataforma: android|ios }` del push (201 la primera vez, 200 si ya estaba; si el token estaba a nombre de otra cuenta pasa a la actual); `DELETE /dispositivos/{token}` lo retira al cerrar sesión (404 si no existe o es de otra cuenta). El token nunca se devuelve ni se escribe en logs ni en la bitácora. El envío al sistema operativo requiere configurar el proveedor de push (credencial fuera del repositorio); mientras no esté, el aviso vive en la lista `GET /notificaciones` y se consulta por sondeo.
- El aviso de una nueva solicitud sale por oleadas (§7.1): una solicitud avisa a una cuenta una sola vez por ciclo. Llega al encargado del proveedor y a los pilotos con delegación de oferta (§3.2), no al resto de la empresa. Mientras no haya push, el piloto lo ve en la lista de la app (`GET /notificaciones`), que consulta al abrir, al volver a primer plano y al refrescar.

### 7.1 Oleadas de aviso («sistema solar»)
- Al publicar, el servidor arma la lista de proveedores que cumplen **todos** los criterios de alcance en el paso 0 (primera ola) y los avisa en el acto. Cada `negociacion.oleadas.intervalo_minutos` (T; 60 mientras no haya push, 2 a 5 con push; las urgentes usan `negociacion.urgencia.intervalo_minutos`, 15), haya o no aceptaciones (una aceptación no detiene las oleadas; solo las detiene el cliente con «En acuerdo»), avanza un paso de la escalera (`negociacion.oleadas.escalera`: cada paso relaja criterios y cambia parámetros, de forma acumulativa) y avisa solo a quienes ahora cumplen y no habían sido avisados (la ola salta a los ya avisados). Un paso sin proveedores nuevos no espera: pasa al siguiente en la misma pasada.
- Criterios iniciales: `aprobado` (proveedor aprobado con equipo apto), `disponible` (la cuenta y, si es piloto de una empresa, su empresa están en `disponible`; punto 8 del ADR 0045), `sin_rechazo_previo`, `sin_bloqueo` (ninguna de las dos partes bloqueó a la otra, ADR 0047), `sin_negociacion_activa` (el proveedor no tiene una negociación en cola, bandeja o conversación sobre la solicitud), `calificacion` (mínimo del paso; los propios siempre cumplen y entran desde la primera ola; quien tiene menos de `calificaciones_minimas_para_contar` calificaciones cuenta como sin calificación), `dron_capacidad` (requisito del cliente) `departamento` (el proveedor trabaja en el departamento de la solicitud, o el cliente amplió el alcance; §3.11) y los filtros del proveedor (§7). Solo `calificacion` y los requisitos del cliente son relajables; `aprobado`, `disponible`, `departamento` y los filtros del proveedor nunca se relajan, ni en la súper ola (`departamento` solo lo levanta la autorización del cliente).
- Escalera inicial (revisable; las solicitudes urgentes usan una acelerada, §3.10): paso 0 calificación mínima 4,50; paso 1, 4,00; paso 2 sin mínimo; paso 3 sin el requisito de dron del cliente. El último paso es la **súper ola**: todos los elegibles del alcance autorizado (el departamento de la solicitud o, si el cliente amplió, todos), y la solicitud se abre en el mapa a todos ellos (`abierta_a_todos_en`).
- Al salir la súper ola la oleada queda `esperando` («Esperando piloto» mientras no hay aceptaciones). No hay tope de ciclos ni vencimiento: el ciclo siguiente lo abre el cliente a mano o el horario que eligió (§3.8) y vuelve a avisar desde el paso 0.
- Registro de avisos `neg_oleada_avisos` con `UNIQUE (solicitud_id, proveedor_id, ciclo)`; la clave del aviso es `nueva_solicitud_abierta:{solicitud}:{ciclo}`. Correr la pasada dos, tres o diez veces deja las mismas filas y los mismos avisos; un aviso insertado y no entregado se reintenta. Un proveedor que vuelve a `disponible` no recibe avisos de pasos ya ocurridos.
- Estados de `neg_oleadas.estado`: `activa`, `detenida` (la solicitud está `en_acuerdo`), `esperando` (súper ola enviada, espera el relanzamiento) y `terminada` (adjudicada o cancelada). Una cuenta con `nuevas_solicitudes` apagado no recibe estos avisos. Un ciclo abierto por un administrador (`origen = admin`) tiene un solo paso, la súper ola (§3.10), y respeta el alcance autorizado; la pasada de ampliación (`origen = ampliacion`) ocurre dentro del ciclo vigente (§3.11).

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
| `orden` | `relevancia` (por defecto: urgentes vigentes primero, luego `reciente`), `reciente`, `precio_ha`, `hectareas`, `fecha_deseada` |
| `urgentes` | `1` deja solo las solicitudes urgentes vigentes |
| `cursor`, `limite` | Paginación por cursor (máximo 100) |

Cada elemento: `id`, `centro` (`latitud`, `longitud` del centroide de los lotes), `hectareas_solicitadas`, `precio_ha_propuesto`, `departamento`, `provincia`, `municipio`, `comunidad`, `cultivo`, `tipo_aplicacion`, `requisitos`, `ventana_desde`, `ventana_hasta`, `urgente`, `urgencia` (`fecha_limite`, `dias_restantes`, `motivo`), `en_otro_departamento` (verdadero si la solicitud, ampliada por el cliente, es de un departamento donde el proveedor no trabaja), `ofertas_vigentes` (negociaciones en bandeja o conversación; nunca la cola), `mi_negociacion` (`estado` de su negociación —`en_cola`, `en_bandeja`, `en_conversacion` o un cierre— o `null`), `publicada_en`. Solo trae lo visible para ese proveedor (§3.3).

No hay resumen por zona ni cálculo de distancia: el mapa no recibe la posición del piloto.

`GET /solicitudes-abiertas/contador` (cuenta de proveedor cuyo proveedor está `pendiente` o aprobado sin equipo apto; rechazado, suspendido o de baja, 403): devuelve `total`, la cantidad de solicitudes `abierta` o `con_ofertas` que le corresponderían por su alcance (las de los departamentos donde dice trabajar y las ampliadas por el cliente, §3.11), con el parámetro opcional `departamento`. No trae lotes, clientes, precios, etapa ni ubicación más fina que el departamento.

`GET /solicitudes-abiertas/{id}`: detalle previo a ofertar con los datos de la primera columna de §3.4 (polígonos GeoJSON de cada lote, estado del terreno, fotos con URL firmada de corta vida). No incluye datos del cliente.

## 9. Registro del proveedor, datos de la persona y dron con fotos

- `POST /proveedores/registro`: cuenta, `tipo` (`independiente` | `tercero`), datos de la persona (`nombres`, `apellidos`, `documento_identidad`, `telefono`, `fecha_nacimiento`), datos del piloto (`licencia_numero`, `licencia_vence`), datos del dron (`marca`, `modelo`, `anio`, `numero_serie`, `capacidad_tanque_l`, `ancho_pasada_m`) y `departamentos` (los departamentos donde trabaja, al menos uno; §3.11). Idempotente por `uuid_cliente`. Queda `pendiente`.
- `PUT /perfil/foto` y `DELETE /perfil/foto` (foto de perfil de la cuenta del token: cliente, titular o piloto; menos de 300 KB). La imagen se entrega por una URL firmada de corta vida (`GET /personas/{usuario}/foto`, sin token): `foto_url` viaja en `GET /auth/sesion` para la propia cuenta y en la tarjeta del proveedor (`proveedor.foto_url`, la del piloto del equipo o, si no tiene, la del titular) que ve el cliente al comparar ofertas.
- `PUT /proveedores/mi/disponibilidad` (`disponibilidad`: `disponible`, `ocupado` o `en_trabajo`) cambia el interruptor manual de la propia cuenta (el titular el del proveedor; el piloto de una empresa el suyo); `PUT /proveedores/pilotos/{id}/disponibilidad` lo cambia el encargado (titular, `proveedores.cuenta.gestionar`) para un piloto suyo. `GET /proveedores/mi` lo devuelve con quién y cuándo lo cambió. `ocupado` y `en_trabajo` dejan de recibir avisos de nuevas solicitudes (con el interruptor de la empresa en `ocupado` no recibe ningún piloto suyo); la cuenta sigue viendo el mapa y puede enviar una oferta, que espera en la cola hasta que vuelva a `disponible` (§3.8). `PUT /proveedores/pilotos/{id}/delegacion-oferta` (`puede_ofertar`, solo el encargado) delega o retira la oferta de un piloto suyo (§3.2); `GET /proveedores/mi` lo devuelve por piloto. `PUT /proveedores/mi/departamentos` (`departamentos`) cambia los departamentos donde trabaja el proveedor (§3.11); `GET /proveedores/mi` los devuelve.
- `GET /proveedores/mi` y `GET /proveedores/drones`: el titular (`proveedores.cuenta.gestionar`) gestiona su proveedor y sus drones; el piloto de un proveedor, incluido el piloto propio de Agrocom (`proveedores.cuenta.ver`), los ve en solo lectura: el proveedor al que pertenece (sin documento fiscal) y los drones de sus equipos vigentes. Un rol sin ninguno de los dos permisos responde 403, y nadie ve el proveedor ni los drones de otro.
- `POST /proveedores/drones/{id}/fotos` (multipart; campos `tipo`, `archivo`); `GET` lista con estado; `DELETE /proveedores/drones/{id}/fotos/{foto}` solo mientras `en_revision` o `rechazada`.
- Fotos obligatorias para aprobar el dron: las del parámetro `flota.fotos_obligatorias` (`frontal`, `lateral`, `placa_serie`). El administrador aprueba o rechaza cada foto con motivo en `panel/drones-flota`.
- La oferta y el comparador del cliente muestran del dron: marca, modelo, año, capacidad y fotos `aprobada`.
- Fotos del terreno: `POST /terrenos/{id}/fotos` (multipart `archivo` y `descripcion` opcional, menos de 300 KB, hasta 8 por terreno) y `DELETE /terrenos/{id}/fotos/{foto}`, solo del cliente dueño. Las fotos viajan en `terreno.fotos` del terreno y de la solicitud del cliente, y en `terreno.fotos` del detalle `GET /solicitudes-abiertas/{id}`, cada una con una URL firmada de corta vida (`GET /terrenos/fotos/{foto}/archivo`, sin token).

## 10. Recursos nuevos, cambiados y retirados

### Nuevos
`GET /solicitudes-abiertas`, `GET /solicitudes-abiertas/{id}`, `GET /catalogos/ubicaciones`, `POST /solicitudes/{id}/acuerdo`, `POST /solicitudes/{id}/acuerdo/reabrir`, `GET /bandeja`, `POST /ofertas/{id}/vista`, `PUT /proveedores/pilotos/{id}/delegacion-oferta`, `POST /solicitudes/{id}/busqueda/relanzar`, `PUT /solicitudes/{id}/busqueda/programacion`, `PUT /solicitudes/{id}/ajustes`, `GET /solicitudes-abiertas/contador`, `GET /catalogos/urgencia`, `PUT /solicitudes/{id}/alcance`, `PUT /proveedores/mi/departamentos`, `PUT /proveedores/mi/disponibilidad`, `PUT /proveedores/pilotos/{id}/disponibilidad`, `POST /solicitudes/{id}/ofertas`, `GET /solicitudes/{id}/ofertas`, `GET /ofertas/{id}`, `POST /ofertas/{id}/contraofertar`, `POST /ofertas/{id}/retirar`, `POST /solicitudes/{id}/precio`, `GET /solicitudes/{id}/avance`, `POST /solicitudes/{id}/{inicio|avance|cierre|evidencias}`, `GET /solicitudes/{id}/ubicacion-piloto`, `GET /solicitudes/{id}/precio-final`, `POST /solicitudes/{id}/precio-final/confirmar`, `PUT /ubicacion`, `GET|POST /solicitudes/{id}/mensajes`, `POST /solicitudes/{id}/mensajes/leidos`, `POST /solicitudes/{id}/pago-confirmado`, `GET|PUT /notificaciones/preferencias`, `POST /dispositivos`, `DELETE /dispositivos/{token}`, `POST /terrenos/{id}/fotos`, `DELETE /terrenos/{id}/fotos/{foto}`, `GET /terrenos/fotos/{foto}/archivo`, `POST|GET|DELETE /proveedores/drones/{id}/fotos`, `PUT|DELETE /perfil/foto`, `GET /personas/{usuario}/foto`, `GET /solicitudes/{id}/pago-confirmado`; registros `inicio`, `avance`, `cierre` y `mensaje`.

### Cambiados
- `POST /solicitudes`, `PUT /solicitudes/{id}`: reciben `precio_ha_propuesto`, varios terrenos, `observaciones`; ya no calculan tarifa ni recargos. `GET /solicitudes/{id}/estimacion` pasa a devolver el monto estimado con el precio propuesto.
- `POST /solicitudes/{id}/publicar`: deja la solicitud `abierta` y dispara el aviso a pilotos. Exige la cuenta de cliente aprobada: si no, 403 `cuenta_no_aprobada`; lo mismo vale para aceptar y contraofertar (ADR 0046).
- `GET /solicitudes`, `GET /solicitudes/{id}`: estados nuevos, `precio_ha_acordado`, `monto_estimado`, `hectareas_ejecutadas`, `porcentaje_avance`, `monto_final`, `oferta_adjudicada`, `proveedor` (con contacto solo adjudicado).
- `GET /ofertas`, `GET /ofertas/{id}`, `POST /ofertas/{id}/aceptar`, `POST /ofertas/{id}/rechazar`: pasan a ser de la negociación y sirven a ambos lados; el lado lo decide el tipo de la cuenta (ADR 0046).
- `POST /solicitudes/{id}/cancelar`: sin penalidad; registra en `neg_cancelaciones` si la solicitud estaba adjudicada. `POST /solicitudes/{id}/cancelar-proveedor` reabre la solicitud (§1).
- `GET /reputacion`: calificación, tasa de cancelación y alertas; sin sanciones ni tasa de aceptación.
- `GET /sync/catalogo`: `servicios_asignados` pasa a ser las solicitudes `adjudicada` y `en_ejecucion` del equipo con lotes, datos de contacto y precio acordado; suma `mensajes`.
- Reclamos: motivos §8.3 de `marketplace.md`; el resultado ajusta `monto_ajustado`, sin comisión.
- `POST /proveedores/registro`, `/proveedores/drones`: datos de persona y dron (§9); `zona_base_id` y `radio_operacion_km` ya no existen.
- `GET /catalogos/ubicaciones` (sin token): `{data: [{codigo, nombre, provincias: [{codigo, nombre}]}]}` con los 9 departamentos y 112 provincias del INE, por código.
- `POST/PUT /terrenos`: `departamento_codigo` y `provincia_codigo` (del catálogo; la provincia debe ser del departamento y no se envía sin él), `municipio` y `comunidad`; `zona_id` ya no existe. Se pueden registrar sin ubicación, pero `POST /solicitudes/{id}/publicar` responde 422 (`departamento_codigo` o `provincia_codigo`) si el terreno no las tiene o no son coherentes.
- `POST/PUT /solicitudes`: `requisitos`, fecha deseada hasta la anticipación máxima; `GET /solicitudes` y `/{id}` suman `departamento_codigo`, `provincia_codigo`, `municipio` y `comunidad` (la ubicación congelada al publicar; los nombres salen del catálogo) y, en fases posteriores, `etapa` y `busqueda`; `GET /solicitudes/{id}/estimacion` ya no trae `precio_referencia_ha`.
- `GET /solicitudes-abiertas`: filtro `departamento`, solo lo visible para el proveedor (§3.3), sin `zona`, `macrorregion`, `distancia_km` ni `lat`/`lon`.
- `GET /solicitudes/{id}/ofertas`: solo negociaciones en bandeja o conversación (nunca la cola), con `negociacion {estado, nueva}` y `bandeja {cupo, ocupadas, nuevas}`; `orden` por defecto `recomendado`, `incumple` por oferta, sin `distancia_km`.
- `POST /solicitudes/{id}/ofertas`: crea la negociación `en_cola` y la libera a la bandeja si hay casilla; la respuesta trae `negociacion.estado` y `espera_por_disponibilidad`; 403 `oferta_no_delegada`; 409 `negociacion_ya_existe` y `solicitud_no_disponible` (solicitud no visible o `en_acuerdo`).
- `GET /ofertas` y `GET /ofertas/{id}` (lado proveedor): `negociacion.estado` propio (`en_cola`, `en_bandeja`, `en_conversacion`, cierres); nunca posición ni datos de otras negociaciones.
- `GET/PUT /notificaciones/preferencias`: filtros del proveedor (§7) en lugar de zonas y macrorregiones; suman `recibir_urgentes`.
- `POST/PUT /solicitudes` y `PUT /solicitudes/{id}/ajustes`: `urgente`, `fecha_limite` y `motivo_urgencia` (§3.10); `GET /solicitudes` y `/{id}` traen `urgencia`, y `busqueda.sugerencias`.
- `GET /solicitudes-abiertas` y `/{id}`: `urgente` y `urgencia` también en la lista; filtro `urgentes` y `orden=relevancia`.
- `GET /catalogos/urgencia` (sin token): `{dias_maximos, dias_sugerencia, motivos: [{clave, nombre}]}`.
- `GET /sync/catalogo`: `servicios_asignados` suma `fecha_limite`.
- `POST/PUT /solicitudes`: reciben `alcance` (`departamento` por defecto o `ampliado`); `GET /solicitudes` y `/{id}` traen `alcance`, `alcance_ampliado_en` y la sugerencia `ampliar_alcance` en `busqueda.sugerencias` (§3.11).
- `GET /solicitudes-abiertas` y `/{id}`: `en_otro_departamento`; solo lo visible por el alcance del proveedor (§3.3). `GET /solicitudes-abiertas/contador` cuenta según el alcance.
- `POST /proveedores/registro`: recibe `departamentos`; `GET /proveedores/mi` los devuelve.

### Retirados
`POST /solicitudes/{id}/busqueda/detener` y `/busqueda/reanudar` (los reemplaza «En acuerdo»), las etapas `en_negociacion` y `busqueda_detenida`, la pausa de las oleadas (`en_pausa`), el parámetro `etapa.cupo_negociaciones` (ahora `cupo_negociaciones`, 3), `POST /solicitudes/{id}/renovar`, la vigencia de la solicitud (`vence_en`), el estado `expirada` y el reinicio de la escalera tras 24 horas (ADR 0045, revisión); `GET /zonas`, `GET /solicitudes-abiertas/resumen-zonas`, `GET /referencias/precio`, el cálculo de distancia (`distancia_km`, `orden=distancia`, posición en la consulta del mapa), las zonas y macrorregiones, el precio de referencia y la base y el radio de operación del proveedor (ADR 0045); `departamentos_excluidos` de `neg_preferencias.filtros` y de `filtros_disponibles` (lo reemplazan los departamentos donde trabaja el proveedor, §3.11); `PUT|GET /disponibilidad` y la noción de equipo en línea (la disponibilidad actual es solo el estado manual del proveedor, `PUT /proveedores/mi/disponibilidad`); `POST /solicitudes/{id}/conformidad` y `POST /solicitudes/{id}/acta/firmar` (el cierre lo marca el piloto y el cliente puede reclamar); la penalidad de cancelación, la comisión (`comision_porcentaje`, `comision_monto`, estado de cuenta, bloqueo por comisión vencida) y las sanciones automáticas (`asg_sanciones`, tasa de aceptación).

## 11. Datos existentes

Salvo la excepción de abajo, ninguna migración borra datos; toda tabla o columna que sale de `public` se mueve a un esquema `respaldo_*` (`ALTER TABLE … SET SCHEMA` o copia previa a la columna) con `down()` que la restaura, y se verifica contra PostgreSQL. Los esquemas `respaldo_*` los elimina la migración `2026_12_04_100100_elimina_esquemas_de_respaldo` (el sistema no conserva datos retirados).

Excepción del ADR 0045 (punto 26): el retiro de las zonas (`sol_zonas`, `zona_id`, `zona_base_id`, `radio_operacion_km`, zonas de las preferencias), del precio de referencia (`sol_precios_referencia`), de la distancia (`neg_ofertas.distancia_km`) y de la vigencia de la solicitud (`vence_en` y el estado `expirada`, cuyas filas pasan a `cancelada`) y de la clave `departamentos_excluidos` de las preferencias (que reemplazan los departamentos donde trabaja el proveedor, con `07` para los de beta) no pasa por `respaldo_*`, porque en beta solo hay datos de prueba y los catálogos son semillas. Las migraciones de retiro son nuevas, con `down()` verificado, y después se reconsolidan (ADR 0024). La excepción caduca si existe un dato real al integrarlas.

| Origen | Destino |
|---|---|
| Estados de la solicitud: `publicada`, `en_asignacion`, `sin_proveedor`, `asignada`, `en_camino`, `suspendida_por_clima`, `ejecutada`, `completada` | `abierta`, `abierta`, `cancelada`, `adjudicada`, `adjudicada`, `en_ejecucion`, `finalizada`, `finalizada` (o `calificada` si el cliente ya calificó) |
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
- **Bandeja, cupo y cola** (`tests/Unit`, reloj inyectado): con cupo 3 la cuarta aceptación queda `en_cola` y el cliente no la ve ni la cuenta; rechazar libera una casilla y pasa la primera de la cola por prioridad (propio, calificación, llegada); se salta (sin descartar) a quien está `ocupado` o `en_trabajo` y entra cuando vuelve a `disponible`; con la solicitud `en_acuerdo`, adjudicada o cancelada no libera; `LiberarCola` una, dos y diez veces, y los eventos de cierre en desorden, dejan las mismas filas y los mismos avisos; dos rechazos simultáneos nunca superan el cupo (casilla única); la oferta en cola no vence; el reloj de la oferta empieza en la bandeja; una oferta no cambia la visibilidad de la solicitud ni pausa la oleada; «En acuerdo» exige una conversación, congela la cola y se deshace solo al caer la última; el mismo uuid reintentado no crea otra negociación.
- **Solicitudes urgentes** (`tests/Unit`, reloj inyectado): la urgencia no se deduce nunca de la fecha; `fecha_limite` obligatoria, dentro del tope y no anterior a la fecha deseada (los cinco 422 de §3.10); el perfil `urgente` usa T y escalera propios y la súper ola sale a los 30 minutos; correr la pasada una, dos o diez veces deja las mismas filas y avisos también con el perfil urgente; `recibir_urgentes` apagado no recibe avisos de urgentes pero sí ve el mapa y nunca se relaja; `ocupado` y `en_trabajo` no reciben; un solo ciclo nuevo por día (el relanzamiento diario no lo supera); declarar y quitar la urgencia con un ciclo en marcha continúa sin reiniciar; pasada la fecha límite dejan de salir olas, se avisa una vez y la solicitud no se cancela ni vence aunque pasen 30 días; la acción del administrador exige motivo, abre un ciclo propio sin duplicar avisos en ese ciclo, no salta ocupados ni filtros, deja bitácora y respeta la separación mínima y `en_acuerdo`; sin recargo (el precio no cambia al declarar la urgencia); orden `relevancia` y filtro `urgentes` del mapa.
- **Alcance por departamento** (`tests/Unit`, reloj inyectado; ADR 0045, punto L): una solicitud solo avisa y se ve en el mapa a proveedores que trabajan en su departamento; el criterio `departamento` nunca lo relajan la escalera, la súper ola, el relanzamiento ni la urgencia; ampliar avisa a los de otros departamentos dentro del ciclo vigente sin abrir un ciclo ni contar contra el tope diario, y ampliar una, dos o diez veces, o ampliar, restringir y volver a ampliar, deja las mismas filas y avisos; restringir saca la solicitud del mapa de otros departamentos salvo de quien tiene una negociación y no cierra negociaciones; la cola de otro departamento queda quieta al restringir; solo el cliente dueño amplía (el administrador y otro cliente, no); `en_acuerdo` y los estados cerrados responden 409; la declaración del proveedor exige al menos un departamento del catálogo y solo la cambia el titular o el encargado; el aviso y el mapa marcan `en_otro_departamento`; el proveedor de otro departamento no ve datos del cliente antes de adjudicar; «Avisar ahora a todos» respeta el alcance; las preferencias no traen `departamentos_excluidos`.
- **Privacidad de la cola** (seguridad): ninguna respuesta al proveedor trae posición, casilla, tamaño de la cola ni datos de otra negociación; ninguna respuesta al cliente trae la cola ni su tamaño.
- **Empresa**: un piloto sin delegación recibe 403 `oferta_no_delegada`; dos personas de la misma empresa no abren dos negociaciones.
- **Replay**: `inicio`, `avance`, `cierre` y `mensaje` aplicados dos, tres o diez veces, en orden y en desorden parcial, dejan la base idéntica.
- **Decimales**: `monto_estimado` y `monto_final` exactos con casos de redondeo.
- **Contrato**: cada ruta nueva está en `docs/api/openapi.yaml` con sus ejemplos.
