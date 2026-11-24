# ADR 0038 — Motor de negociación: módulo, ofertas, adjudicación y vencimientos

**Estado:** aceptada · **Fecha:** 2026-11-24 · **Decide:** el dueño

## Contexto

El ADR 0030 fija el qué de la negociación: el cliente propone un precio por hectárea, los pilotos aceptan o contraofertan, el cliente elige. Falta el cómo en el código: en qué módulo vive, qué tablas y eventos tiene, cómo se serializa la adjudicación y cómo se comunica con la solicitud, la flota y los proveedores sin importar sus modelos.

## Decisión

1. **Módulo `Negociacion`** bajo `app/Dominios/` con prefijo de tabla `neg_`. Escribe solo `neg_ofertas` y `neg_cancelaciones`. Habla con los demás módulos por contratos de primitivos (ADR 0003): lee proveedores, equipos y solicitudes por `LecturaProveedores`, `LecturaEquipos` y `LecturaSolicitudesParaNegociar`, y mueve la solicitud por `AdjudicacionDeSolicitud`, que la máquina de estados de `Solicitudes` implementa.
2. **Oferta.** Estados `vigente → aceptada | reemplazada | rechazada | retirada | vencida | cerrada` (`TransicionesOferta`; `MaquinaEstadosOferta` es la única clase que escribe `estado`). La base garantiza con índices únicos parciales una sola oferta `vigente` por `(solicitud, proveedor)` y una sola `aceptada` por solicitud.
3. **Adjudicación atómica y sin interbloqueo.** Toda decisión (aceptar, contraofertar, rechazar, retirar, vencer, cerrar) bloquea primero la fila de la solicitud y después las de sus ofertas, siempre en ese orden. Dos aceptaciones simultáneas se serializan: la segunda recibe 409 `solicitud_ya_adjudicada`. Repetir la misma decisión devuelve la misma oferta sin repetir avisos. Al adjudicar se revalida el equipo (aprobado, documentos vigentes, sin cruce de fechas con otra solicitud `adjudicada` o `en_ejecucion`).
4. **Idempotencia.** Toda oferta nace con `uuid_cliente` generado en el dispositivo; reintentar el mismo uuid devuelve la misma oferta.
5. **Alcance.** Todo parte de la cuenta autenticada (invariante 5): el proveedor ve sus negociaciones, el cliente las de sus solicitudes, y un id ajeno responde 404. El proveedor ve de las demás solo el conteo de ofertas vigentes.
6. **Vencimiento por proceso programado.** `negociacion:procesar` corre cada minuto (`withoutOverlapping`, `onOneServer`): vence ofertas, expira solicitudes sin adjudicar, devuelve `con_ofertas` a `abierta` si no queda oferta vigente y cierra las ofertas de lo que expiró o se canceló. Es idempotente; un fallo en una solicitud se reporta y no detiene a las demás.
7. **Mapa de solicitudes abiertas.** Una lectura (`LecturaSolicitudesAbiertas`) con filtros por encuadre, zona, región, hectáreas, precio, fecha y cultivo, y un resumen por zona. Calcula la distancia con Haversine desde la posición que el piloto envía en la consulta, que no se guarda.
8. **Cancelación del proveedor** (`POST /solicitudes/{id}/cancelar-proveedor`): solo desde `adjudicada`; reabre la solicitud, registra la cancelación en `neg_cancelaciones` y marca al proveedor como no elegible en esa solicitud. No se aplican restricciones automáticas.
9. **Alertas, no sanciones.** Tras cada cancelación y cada cambio de reputación se evalúan los umbrales de calificación y de tasa de cancelación; si se superan, se emite `ProveedorEnAlerta` para el panel. La suspensión sigue siendo una decisión del administrador (ADR 0032).
10. **Eventos** como DTO de primitivos, anunciados al confirmar la transacción: `SolicitudAbierta`, `OfertaRecibida`, `ContraofertaRecibida`, `OfertaRechazada`, `OfertaRetirada`, `OfertaVencida`, `OfertaCerrada`, `SolicitudAdjudicada`, `SolicitudExpirada`, `SolicitudRenovada`, `ProveedorEnAlerta`. El motor de notificaciones (ADR 0025) los traduce según las preferencias (ADR 0041).
11. **Panel:** `panel/solicitudes` con la ficha de cada solicitud y la línea de tiempo de sus negociaciones y ofertas, de solo lectura salvo cancelar con motivo, y la alerta de proveedores en `panel/proveedores`.
12. **Parámetros** en `config/negociacion.php` (ADR 0016): vigencia de oferta y de solicitud, plazo de renovación, máximo de ofertas por negociación, precio máximo de sanidad, precios de referencia, umbrales de alerta.

## Alternativas descartadas

- **Poner el motor dentro de `Solicitudes`**: mezcla el ciclo de la solicitud con el de las ofertas; `Solicitudes` ya no podría explicarse sin la negociación.
- **Un job en cola por oferta con retraso** en lugar de un proceso cada minuto: más piezas para un vencimiento que tolera un minuto de holgura.
- **Aplicar restricciones desde el motor**: la política exige revisión y apelación (ADR 0032).
- **Guardar la ubicación del piloto cada vez que consulta el mapa**: dato personal sin función.

## Consecuencias

- Migraciones nuevas para `neg_ofertas` y `neg_cancelaciones`, columnas de precio en `sol_solicitudes_servicio` y la ampliación de los CHECK de `ntf_notificaciones`. Los esquemas `respaldo_*` desaparecen con la migración `2026_12_04_100100_elimina_esquemas_de_respaldo` (`negociacion-contrato.md`, §11).
- La revisión humana posterior a la integración cubre la adjudicación (`ResponderOferta`), `VencerOfertas`, `CancelarComoProveedor`, el mapa de abiertas y las migraciones.
