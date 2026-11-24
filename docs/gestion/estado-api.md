# Estado de la API

Qué está hecho en `agrocom-api` para las apps móviles (`cliente`, `piloto` y `auxiliar` de `agrocom-field`) y qué falta. La fuente de verdad funcional es `docs/especificacion/marketplace.md`; el contrato técnico es `docs/api/openapi.yaml`, generado desde el código (ADR 0014).

## Hecho

### Dominio del marketplace

| Área | Qué existe en la API | Decisión |
|---|---|---|
| Proveedores y flota | Registro público de proveedor, pilotos, auxiliares, documentos, drones con dueño, equipos y disponibilidad; aprobación y reenvío tras un rechazo. | ADR 0028, 0029, 0032, 0036 |
| Solicitud y trabajo | Terrenos del cliente, solicitud idempotente por `uuid_cliente`, estimación, publicación con tarifa congelada, cancelación y conformidad; el trabajo nace de una solicitud aceptada. | ADR 0027, 0037 |
| Asignación | Ofertas con ronda de propios y luego de terceros, aceptación atómica que requiere red, rechazo, cancelación del proveedor, reputación y sanciones. | ADR 0030, 0038 |
| Calificación, disputas y comisión | Calificación bidireccional (la del cliente por el proveedor es privada), reporte de una calificación abusiva, disputas con mensajes, retiro y una apelación, comisión y métricas. | ADR 0031, 0033, 0034, 0039 |
| Ejecución offline | `POST /api/sync` (lote idempotente por `uuid_cliente`), `GET /api/sync/catalogo` con cursor, `POST /api/evidencias` en cola aparte, actas y reporte del lote. | ADR 0005; especificación §10.3 |
| Retiro de la operación interna | El alcance de finanzas, inventario y mantenimiento de flota no forma parte del marketplace; sus endpoints y esquemas no están en el contrato. | ADR 0035 |

### Contrato

- `docs/api/openapi.yaml` se regenera con `composer openapi` y coincide con el código: 69 operaciones documentadas, ninguna ruta de `/api` sin documentar ni documentada sin ruta, y la seguridad declarada coincide con la que exigen las rutas.
- La sección 10 de la especificación lista las rutas reales de sincronización (`POST /api/sync`, `POST /api/evidencias`, `GET /api/sync/catalogo`) y el prefijo `/api`.
- Los campos que pueden ser nulos y apuntan a un esquema se declaran con `allOf` y `nullable: true`; el esquema de entrada de terrenos (`TerrenoEntrada`) es distinto del de salida.
- `docs/api/ejemplos/` guarda 56 intercambios reales (petición y respuesta) de 36 operaciones, capturados con `bin/generar-ejemplos` contra un PostgreSQL desechable. Cubren éxito, validación, recurso ajeno (404), rol equivocado (403), token ausente o revocado (401), reintento idempotente, replay de un lote y rechazo parcial. El formato está en `docs/api/ejemplos/README.md`.

### Pruebas de contrato

Corren siempre en CI (son parte del humo del selector de impacto):

- `tests/Feature/Contrato/RutasDelContratoTest.php`: las rutas registradas y las documentadas son la misma superficie.
- `tests/Unit/Contrato/EjemplosDeApiTest.php`: cada ejemplo corresponde a una operación del contrato, su estado HTTP está documentado, sus cuerpos cumplen los esquemas (sin campos sin documentar, con nullables y enums respetados), ningún ejemplo guarda una credencial viva y hectáreas, litros y montos viajan como decimal exacto en cadena. Además verifica que un replay del lote responde `duplicado` para cada registro y que un registro inválido no frena el resto.
- Las apps pueden usar los mismos fixtures en sus pruebas de contrato: son lo que el servidor devuelve de verdad.

### Endurecimiento

- `laravel/framework` sube a 13.43 y `larastan/larastan` a 3.15; PHPStan (nivel 6) pasa sin errores y sin baseline.
- Las referencias a documentos y módulos inexistentes (`docs/legacy`, `docs/negocio`, los planes de sprint y los módulos comerciales, de finanzas e inventario) se apuntan a `docs/especificacion/marketplace.md` o se quitan en `README.md`, `CLAUDE.md`, `CONTRIBUTING.md`, los agentes y skills de `.claude/`, las plantillas de `prompts/`, los ADR y los comentarios del código.

## Falta

### Endpoints de la especificación sin implementar

La especificación §10 los define y la API no los expone:

| Endpoint | Para qué | Quién lo necesita |
|---|---|---|
| `POST /api/solicitudes/{id}/en-camino` | El equipo avisa que salió; pasa la solicitud de `asignada` a `en_camino` y avisa al cliente. | App `piloto` y seguimiento del `cliente` |
| `POST /api/incidencias` | Reporte de incidencia del cliente fuera del lote (el piloto ya la registra por `POST /api/sync`). | App `cliente` |
| `POST /api/emergencias` | SOS con aviso de máxima prioridad a soporte y al contacto de emergencia. | Las tres apps |
| `POST /api/solicitudes/{id}/acta/firmar` | La firma de conformidad existe como `POST /api/actas/{uuid_cliente}/firmar`; falta alinear la ruta con la especificación o corregir la especificación. | App `cliente` |

### Contrato y ejemplos

- 33 operaciones sin ejemplo real. Las que más importan a las apps son el alta (`clientes/registro`, `proveedores/registro`), `ofertas/{id}/rechazar`, `solicitudes/{id}/conformidad`, la calificación en ambos sentidos, las disputas (abrir, ver, mensajes, retirar, apelar), la firma y el PDF del acta, y la alta y edición de pilotos, auxiliares, drones y equipos.
- El generador (`bin/lib/generar-ejemplos.php`) escribe instantes de reloj reales, así que cada regeneración cambia `ofrecida_en`, `vence_en` y similares. Las apps no deben comparar esos valores.
- Las pruebas de contrato validan el subconjunto de OpenAPI 3 que emite swagger-php (`$ref`, `allOf`, `oneOf`, `anyOf`, `enum`, `nullable`, formatos `date`, `date-time` y `uuid`); un esquema con `discriminator` o `patternProperties` requiere extender `tests/Soporte/ContratoOpenApi.php`.

### Notificaciones

- El canal in-app (`GET /api/notificaciones`, `POST /api/notificaciones/{id}/leida`) funciona. La notificación push del sistema operativo (oferta con sonido y cuenta regresiva) no tiene proveedor ni registro de token de dispositivo en la API.

### Documentación histórica

- `docs/gestion/cola_tareas.md` es un registro cronológico de tareas con las rutas que tenían al cerrarse; no es una guía y no se reescribe.
