# ADR 0040 — Seguimiento del servicio: avance con fotos, mensajes y ubicación del piloto

**Estado:** aceptada, revisable · **Fecha:** 2026-11-24 · **Decide:** el dueño

> **Nota de retiro (26/11/2026):** el auxiliar se eliminó del sistema (rol, permisos `proveedores.auxiliar.*`, página del panel, endpoints `/api/proveedores/auxiliares`, documentos y calificación del auxiliar). Donde este ADR lo menciona, ya no aplica; las tablas `prv_auxiliares` y la columna `flo_equipos.auxiliar_id` siguen en el esquema, sin uso, hasta la reconsolidación de migraciones. Un reemplazo (integrantes del equipo del servicio) se define aparte.

## Contexto

Tras adjudicar, cliente y piloto se ven en el lote y acuerdan cómo trabajar; eso queda fuera de la plataforma. Lo que el cliente necesita de la plataforma es confirmar que el servicio avanza: ver fotos, conversar y saber dónde está el piloto. El lote no tiene señal, y la ubicación y los datos de contacto son datos personales.

## Decisión

1. **Avance (`seg_avances`).** El piloto del equipo adjudicado registra `inicio`, `avance` (hectáreas acumuladas o porcentaje) y `cierre` (hectáreas ejecutadas). Son registros del lote: nacen en el dispositivo con `uuid_cliente`, `secuencia` y outbox, y se aplican por `POST /sync` de forma idempotente.
2. **Fotos (`seg_evidencias`).** Piloto y auxiliar suben fotos de avance comprimidas a menos de 300 KB, en una cola separada de los registros livianos. El cierre exige al menos las fotos mínimas del parámetro ya subidas para pasar a `finalizada`.
3. **Precio final.** Al finalizar se congela `monto_final = hectareas_ejecutadas × precio_ha_acordado` y se muestra a ambos (ADR 0034).
4. **Mensajes (`seg_mensajes`).** Chat de texto entre el cliente y el proveedor adjudicado, desde `adjudicada` hasta el cierre del plazo de calificación o de la disputa. El cliente escribe en línea; el piloto escribe por el outbox y la entrega ocurre al recuperar red. La entrega es por consulta con cursor y aviso push; si el producto exige un canal en tiempo real, se cambia el transporte sin cambiar los recursos.
5. **Ubicación (`seg_ubicaciones`).** Solo la última posición de la app del piloto, enviada en línea (sin outbox) durante `en_ejecucion` cada 30 segundos. El cliente la consulta como un punto que se refresca; no hay ruta ni tiempo estimado. Al finalizar o cancelar la fila se elimina. No se registra en historial, bitácora, logs ni analíticas. El piloto puede desactivarla; el cliente entonces ve «sin ubicación reciente».
6. **Datos de contacto.** Nombre, teléfono, documento y referencia de dirección del cliente se entregan al proveedor adjudicado, y el teléfono del piloto al cliente, solo al adjudicar. Antes de adjudicar el piloto ve lotes, hectáreas, cultivo, accesos, observaciones y fotos del terreno para decidir su precio, no quién es el cliente.
7. **Un solo rol escritor por registro.** El avance y la ubicación los escribe el piloto; los mensajes, cada autor los suyos; la calificación, su autor.
8. **Pago informativo.** El proveedor puede registrar que recibió el pago; no cambia el estado.

## Alternativas descartadas

- **Seguimiento en vivo con ruta y tiempo de llegada**: costo y privacidad desproporcionados para el valor que aporta al cliente.
- **Guardar el historial de ubicaciones**: dato personal sin uso de negocio.
- **Mostrar el contacto del cliente desde la oferta**: invita a cerrar fuera de la plataforma y expone datos a quien no fue elegido.
- **Mensajes con adjuntos**: las fotos ya tienen su cola comprimida y su control.

## Consecuencias

Nuevo módulo `Seguimiento` (`seg_`) con sus contratos hacia `Solicitudes`. Los registros `inicio`, `avance`, `cierre` y `mensaje` entran en el protocolo de sincronización y en la prueba de replay. Política en `docs/politicas/seguimiento_y_privacidad.md`.
