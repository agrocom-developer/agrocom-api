# Estado de la API

Qué está hecho en `agrocom-api` para las apps móviles (`cliente` y `piloto` de `agrocom-field`) y qué falta. La fuente de verdad funcional es `docs/especificacion/marketplace.md`; el contrato técnico es `docs/api/openapi.yaml`, generado desde el código (ADR 0014).

## Hecho

### Dominio del marketplace

| Área | Qué existe en la API | Decisión |
|---|---|---|
| Proveedores y flota | Registro público de proveedor con datos de la persona y del dron, pilotos, documentos, drones con dueño y fotos, equipos; aprobación y reenvío tras un rechazo. | ADR 0028, 0029, 0032, 0036, 0042 |
| Solicitud | Terrenos del cliente, solicitud idempotente por `uuid_cliente` con precio por hectárea propuesto, estimación, apertura al mercado, cambio de precio, renovación y cancelación sin penalidad. | ADR 0027, 0034, 0037 |
| Negociación | Mapa de solicitudes abiertas con filtros y resumen por región, ofertas y contraofertas por rondas, adjudicación atómica que requiere red, vencimiento de ofertas y solicitudes (`negociacion:procesar`), cancelación del proveedor que reabre la solicitud y reputación sin sanciones automáticas. | ADR 0030, 0038, 0041 |
| Seguimiento | Inicio, avance y cierre del piloto con fotos de avance (menos de 300 KB), chat entre cliente y proveedor adjudicado con cursor, última ubicación del piloto solo durante la ejecución, y preferencias de aviso por zona, región y hectáreas. | ADR 0040, 0041 |
| Calificación y disputas | Calificación bidireccional (la del cliente por el proveedor es privada), reporte de una calificación abusiva, disputas con mensajes, retiro y una apelación, y métricas. | ADR 0031, 0033, 0034, 0039 |
| Ejecución offline | `GET /api/sync/catalogo` con cursor (solicitudes adjudicadas y en ejecución del equipo), registros `inicio`, `avance`, `cierre` y `mensaje` por sus rutas REST idempotentes por `uuid_cliente` y `POST /api/solicitudes/{id}/evidencias` en cola aparte. | ADR 0005, 0040; especificación §10.3 |
| Retiro de la operación interna | El alcance de finanzas, inventario y mantenimiento de flota no forma parte del marketplace; sus endpoints y esquemas no están en el contrato. | ADR 0035 |

### Contrato

- `docs/api/openapi.yaml` se regenera con `composer openapi` y coincide con el código: 92 operaciones documentadas, ninguna ruta de `/api` sin documentar ni documentada sin ruta, y la seguridad declarada coincide con la que exigen las rutas.
- La sección 10 de la especificación lista las rutas reales de sincronización (`POST /api/sync`, `POST /api/evidencias`, `GET /api/sync/catalogo`) y el prefijo `/api`.
- Los campos que pueden ser nulos y apuntan a un esquema se declaran con `allOf` y `nullable: true`; el esquema de entrada de terrenos (`TerrenoEntrada`) es distinto del de salida.
- `docs/api/ejemplos/` guarda 77 intercambios reales (petición y respuesta) de 52 operaciones, capturados con `bin/generar-ejemplos` contra un PostgreSQL desechable. Cubren éxito, validación, recurso ajeno (404), rol equivocado (403), token ausente o revocado (401), reintento idempotente, replay de un lote y rechazo parcial. El formato está en `docs/api/ejemplos/README.md`.

### Pruebas de contrato

Corren siempre en CI (son parte del humo del selector de impacto):

- `tests/Feature/Contrato/RutasDelContratoTest.php`: las rutas registradas y las documentadas son la misma superficie.
- `tests/Unit/Contrato/EjemplosDeApiTest.php`: cada ejemplo corresponde a una operación del contrato, su estado HTTP está documentado, sus cuerpos cumplen los esquemas (sin campos sin documentar, con nullables y enums respetados), ningún ejemplo guarda una credencial viva y hectáreas, litros y montos viajan como decimal exacto en cadena. Además verifica que un replay del lote responde `duplicado` para cada registro y que un registro inválido no frena el resto.
- Las apps pueden usar los mismos fixtures en sus pruebas de contrato: son lo que el servidor devuelve de verdad.

### Endurecimiento

- `laravel/framework` sube a 13.43 y `larastan/larastan` a 3.15; PHPStan (nivel 6) pasa sin errores y sin baseline.
- Las referencias a documentos y módulos inexistentes (`docs/legacy`, `docs/negocio`, los planes de sprint y los módulos comerciales, de finanzas e inventario) se apuntan a `docs/especificacion/marketplace.md` o se quitan en `README.md`, `CLAUDE.md`, `CONTRIBUTING.md`, los agentes y skills de `.claude/`, las plantillas de `prompts/`, los ADR y los comentarios del código.

## Falta

### Negociación de ofertas

El motor está implementado; lo que sigue pendiente de `negociacion-contrato.md`:

- Aviso push al sistema operativo: `POST /api/dispositivos` y `DELETE /api/dispositivos/{token}` para registrar el token de push (el aviso vive en la lista `GET /api/notificaciones`).
- `POST /api/solicitudes/{id}/pago-confirmado`, `PUT /api/proveedores/perfil/foto` y `POST /api/terrenos/{id}/fotos`.
- Revisión de las fotos del dron desde el panel (`panel/drones-flota`) y la exigencia de las fotos obligatorias para aprobar el dron.
- Los registros `inicio`, `avance`, `cierre` y `mensaje` viajan hoy por sus rutas REST idempotentes por `uuid_cliente`, no por `POST /api/sync`.

### Endpoints de la especificación sin implementar

La especificación §10 los define y la API no los expone:

| Endpoint | Para qué | Quién lo necesita |
|---|---|---|
| `POST /api/incidencias` | Reporte de incidencia del cliente fuera del lote (el piloto ya la registra por `POST /api/sync`). | App `cliente` |
| `POST /api/emergencias` | SOS con aviso de máxima prioridad a soporte y al contacto de emergencia. | Las dos apps |

### Contrato y ejemplos

- 40 operaciones sin ejemplo real. Las que más importan a las apps son el alta (`clientes/registro`, `proveedores/registro`), `ofertas/{id}/rechazar`, `ofertas/{id}/retirar`, `solicitudes/{id}/mensajes/leidos`, la calificación en ambos sentidos, las disputas (abrir, ver, mensajes, retirar, apelar), la firma y el PDF del acta, y la alta y edición de pilotos, auxiliares, drones y equipos.
- El generador (`bin/lib/generar-ejemplos.php`) escribe instantes de reloj reales, así que cada regeneración cambia `creada_en`, `vence_en` y similares. Las apps no deben comparar esos valores.
- Las pruebas de contrato validan el subconjunto de OpenAPI 3 que emite swagger-php (`$ref`, `allOf`, `oneOf`, `anyOf`, `enum`, `nullable`, formatos `date`, `date-time` y `uuid`); un esquema con `discriminator` o `patternProperties` requiere extender `tests/Soporte/ContratoOpenApi.php`.

### Notificaciones

- El canal in-app (`GET /api/notificaciones`, `POST /api/notificaciones/{id}/leida`) funciona. La notificación push del sistema operativo (oferta con sonido y cuenta regresiva) no tiene proveedor ni registro de token de dispositivo en la API.

### Documentación histórica

- `docs/gestion/cola_tareas.md` es un registro cronológico de tareas con las rutas que tenían al cerrarse; no es una guía y no se reescribe.
