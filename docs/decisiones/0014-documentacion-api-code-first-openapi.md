# ADR 0014 — Documentación de API code-first: OpenAPI/Swagger generado desde atributos, organizado por módulo

**Estado:** Aceptada.

## Contexto

`docs/api/README.md` era un placeholder que planeaba un único `openapi.yaml` escrito a mano a partir de TE-04/TE-05 (el motor de sync, Sprint 2), con la regla "ante cualquier duda entre el YAML y el código, el YAML gana" y, mientras tanto, la sección 8 de `docs/especificacion/especificacion_funcional_tecnica.md` como referencia. Ese plan quedó desfasado por dos lados: el primer endpoint real (órdenes de aplicación, núcleo comercial) ya existe antes del Sprint 2, y `agrocom-field` (ADR 0005) arranca pronto y necesita un contrato consumible ya.

Además, el supuesto de fondo del contrato-first manual no se sostiene en este proyecto: con una sola persona y agentes de IA como implementadores principales (`CLAUDE.md`), un YAML mantenido a mano en paralelo al código es trabajo doble y una fuente casi garantizada de drift — el documento que "gana" sería justamente el que nadie verifica contra la realidad.

## Decisión

1. **Documentación code-first con OpenAPI/Swagger, organizada por módulo.** Los endpoints se documentan con atributos PHP de swagger-php (`OA\...`) directamente en los controllers, form requests y API resources de cada módulo — que viven en `app/Dominios/<Módulo>/Infraestructura/Http/` (ADR 0003). Cada módulo documenta sus propios endpoints, con un tag OpenAPI por módulo (Operaciones, Comercial, Sync, Seguridad, ...): la frontera modular del ADR 0003 se refleja también en la documentación.
2. **Paquete: `darkaonline/l5-swagger`** (el estándar Laravel sobre `zircote/swagger-php` + Swagger UI). La UI se sirve en `/api/documentation` **solo en entornos no productivos** (local/staging); en producción queda deshabilitada hasta que se decida lo contrario.
3. **El spec generado es el contrato para `agrocom-field`.** El `openapi.yaml`/`json` que produce la generación se versiona en el repo como artefacto publicado. La regla del placeholder se invierte: **el código es la fuente de verdad y genera el YAML; el YAML versionado es el contrato que consume la app**. Se regenera y commitea con cada cambio de API.
4. **`docs/api/README.md` se elimina** (lo hace el agente `backend` en la rama en curso). La sección 8 de la especificación sigue siendo la referencia funcional de *qué* endpoints existen; el spec OpenAPI es el contrato técnico de su forma exacta (payloads, códigos de respuesta, esquemas).

## Alternativas descartadas

- **Contrato-first con un `openapi.yaml` escrito a mano** (el enfoque del placeholder): con un solo dev y agentes de IA generando el código, el drift entre el YAML manual y el código real es un riesgo alto y mantenerlos sincronizados es trabajo doble. La anotación junto al código es lo único que los agentes actualizan en el mismo diff que el endpoint.
- **No documentar hasta el Sprint 2** (esperar a TE-04/TE-05 como planeaba el placeholder): el endpoint de órdenes ya existe y la app Flutter arranca pronto — dejar el contrato para después significaría que `agrocom-field` se construya contra una API sin contrato.

## Consecuencias

- Todo endpoint nuevo o modificado lleva sus atributos `OA\...` en el mismo PR — un endpoint sin documentar es visible en el diff igual que un color hardcodeado o un string sin clave de traducción (invariante 11, ADR 0013).
- El spec versionado se regenera y commitea con cada cambio de API. **Pendiente:** un check de CI que regenere el spec y falle si difiere del versionado — hasta que exista, la regeneración es responsabilidad de quien toca la API, verificada en revisión de PR.
- La ruta versionada del artefacto publicado la fija el agente `backend` al configurar el paquete (candidata natural: `docs/api/openapi.yaml`, que queda libre al eliminarse el README); la salida por defecto de l5-swagger (`storage/api-docs/`) no se versiona.
- La configuración del paquete condiciona la UI al entorno (`/api/documentation` deshabilitada en producción); habilitarla en producción requerirá una decisión explícita que actualice este ADR.
- Los tags por módulo hacen que la documentación navegable refleje la arquitectura: si un endpoint no tiene un tag/módulo obvio donde caer, eso es una señal de encaje arquitectónico a resolver antes de escribirlo, no después.
- Los esquemas anotados en resources/requests describen el vocabulario del dominio en español tal como viaja por la API (`hectareas_declaradas`, `uuid_cliente`) — la documentación no traduce el dominio (convención de `CLAUDE.md`, ADR 0013).
