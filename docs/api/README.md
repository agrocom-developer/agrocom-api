# Contrato de API

Acá vivirá `openapi.yaml`: el contrato único entre `agrocom-api` y `agrocom-field` (endpoints, payloads de ejemplo, códigos de respuesta del protocolo de sync). Se agrega cuando arranca TE-04/TE-05 del Sprint 2 (`docs/gestion/plan_sprints.md`) — el motor de sync.

Mientras no exista, el listado de endpoints de `docs/especificacion/especificacion_funcional_tecnica.md` (sección 8) es la referencia.

Regla: ante cualquier duda entre el YAML y el código, **el YAML gana** — `agrocom-field` lo consume como fuente única.
