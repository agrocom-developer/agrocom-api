# Estado de la API

Qué está hecho en `agrocom-api` para las apps móviles (`cliente` y `piloto` de `agrocom-field`) y qué falta. La fuente de verdad funcional es `docs/especificacion/marketplace.md`; el contrato técnico es `docs/api/openapi.yaml`, generado desde el código (ADR 0014).

## Hecho

### Dominio del marketplace

| Área | Qué existe en la API | Decisión |
|---|---|---|
| Proveedores y flota | Registro público de proveedor con datos de la persona y del dron, pilotos, documentos, drones con dueño y fotos, equipos; aprobación y reenvío tras un rechazo. | ADR 0028, 0029, 0032, 0036, 0042 |
| Solicitud | Terrenos del cliente (con departamento y provincia del catálogo oficial más municipio y comunidad), solicitud idempotente por `uuid_cliente` con precio por hectárea propuesto, estimación, apertura al mercado (exige ubicación y la congela), cambio de precio y cancelación sin penalidad. La solicitud no vence sola y la fecha deseada es opcional. | ADR 0027, 0034, 0037, 0045 |
| Ubicación | Catálogo oficial de Bolivia de solo lectura (9 departamentos y 112 provincias con código INE) en `GET /api/catalogos/ubicaciones`, sin token. | ADR 0045 |
| Negociación | Mapa de solicitudes abiertas con filtros, ofertas y contraofertas por rondas, adjudicación atómica que requiere red, vencimiento de ofertas (`negociacion:procesar`; la solicitud no vence), cancelación del proveedor que reabre la solicitud y reputación sin sanciones automáticas. | ADR 0030, 0038, 0041 |
| Seguimiento | Inicio, avance y cierre del piloto con fotos de avance (menos de 300 KB), chat entre cliente y proveedor adjudicado con cursor, última ubicación del piloto solo durante la ejecución, y preferencias de aviso por hectáreas mínimas. | ADR 0040, 0041 |
| Calificación y reclamos | Calificación bidireccional (la del cliente por el proveedor es privada), reporte de una calificación abusiva, reclamos con mensajes, retiro y una apelación, y métricas. | ADR 0031, 0033, 0034, 0039 |
| Ejecución offline | `GET /api/sync/catalogo` con cursor (solicitudes adjudicadas y en ejecución del equipo), registros `inicio`, `avance`, `cierre` y `mensaje` por sus rutas REST idempotentes por `uuid_cliente` y `POST /api/solicitudes/{id}/evidencias` en cola aparte. | ADR 0005, 0040; especificación §10.3 |
| Roles del panel | El panel tiene dos roles, `administrador` (todos los permisos del panel, matriz fija) y `operador` (la matriz que marca el administrador, con su preset de solo lectura); los permisos reservados al administrador no se asignan al operador y los sensibles se asignan con aviso. Una cuenta de panel tiene un solo rol. Los roles `cliente`, `proveedor` y `piloto` son de ámbito `app` y siguen vivos solo hasta la fase del contrato del token. | ADR 0046 |
| Cuentas por app | `sec_user.tipo_cuenta` vale `panel`, `cliente` o `proveedor` y una cuenta tiene un solo tipo. El panel autentica solo cuentas `panel` y el token de dispositivo se emite solo a cuentas de app. `POST /api/auth/token` recibe `app` (`cliente` o `proveedor`; `piloto` es alias) y responde `403 tipo_de_cuenta_incorrecto` con el tipo de la cuenta si no coincide; una cuenta de panel recibe el mismo rechazo que una credencial inválida. Mientras la app no envíe `app`, el tipo se deduce de la cuenta, y el rol sigue viajando como alias hasta el contrato del token. «Ver como» solo sobre cuentas de panel; el portal web del cliente y su guard se retiraron. | ADR 0046 |
| Persona única, teléfono y correo | `sec_personas` identifica a la persona por su documento normalizado; una persona tiene a lo sumo una cuenta de cliente y una de proveedor. El teléfono de la cuenta (`sec_user.telefono`, E.164 con Bolivia por defecto) y el correo son únicos por tipo de cuenta: el mismo teléfono puede estar en una cuenta de cliente y en una de proveedor, nunca en dos del mismo tipo. Si el teléfono o el documento ya tienen una cuenta de ese tipo, el registro responde `409 cuenta_ya_registrada` sin datos de nadie, con un tope de 5 por hora por IP, por teléfono y por documento (después, `429`). El login acepta el teléfono. El teléfono no se presenta como verificado: la prueba por SMS o WhatsApp espera al proveedor (no se envían códigos). | ADR 0046 |
| Estado de la cuenta y compuerta | El cliente sigue el mismo ciclo de aprobación que proveedores y pilotos (`pendiente → aprobado ⇄ suspendido`, `rechazado`, `baja`), con historial de autor, motivo y momento; nace `pendiente` y la oficina lo aprueba o rechaza desde su ficha (`solicitudes.cliente.aprobar`). Una cuenta de app que no está aprobada entra, ve sus pantallas y arma terrenos y borradores, pero publicar una solicitud (cliente), aceptar o contraofertar (cliente y proveedor) y ofertar (proveedor) responden `403 cuenta_no_aprobada` con el estado y lo que falta; rechazar y retirar no se frenan. Distinto de `proveedor_no_habilitado` (falta un equipo apto). Un proveedor no oferta sobre lo publicado por su propia cuenta de cliente ni por otra cuenta de la misma persona (`persona_id`). | ADR 0046 |
| Códigos, recuperación y eliminación de cuenta | Códigos de un solo uso (`sec_codigos_verificacion`) por `whatsapp`, `sms` o `correo` a elección de la persona; `POST /api/auth/recuperar` y `/confirmar`, cambio de contraseña (`POST /api/cuenta/contrasena/codigo`, `PUT /api/cuenta/contrasena`), verificación del teléfono (`POST /api/cuenta/telefono/verificacion` y `/confirmar`), contraseña de 4 caracteres con bloqueo temporal del login, renovación del token (`POST /api/auth/renovar`), `siguiente_paso` del registro en `GET /api/proveedores/mi` y eliminar mi cuenta (`GET/POST /api/cuenta/eliminacion`, `/codigo`, `/cancelar`; gracia de 7 días y tarea `cuentas:ejecutar-eliminaciones`). El correo sale por `Notification`; WhatsApp y SMS salen por el adaptador de `config/verificacion.php`. | ADR 0046, 0047 |
| Retiro de la operación interna | El alcance de finanzas, inventario y mantenimiento de flota no forma parte del marketplace; sus endpoints y esquemas no están en el contrato. | ADR 0035 |

### Contrato

- `docs/api/openapi.yaml` se regenera con `composer openapi` y coincide con el código: 103 operaciones documentadas, ninguna ruta de `/api` sin documentar ni documentada sin ruta, y la seguridad declarada coincide con la que exigen las rutas.
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

### Cuenta y acceso

- **Adaptadores de WhatsApp y SMS**: la interfaz `EnviadorDeCodigo` y el punto de enchufe (`config/verificacion.php`) existen; falta conectar la función de envío de WhatsApp Business del dueño. Sin adaptador, esos canales responden `503 canal_no_disponible` y solo funciona el correo.
- **Cambio de teléfono** (`POST /api/cuenta/telefono` y `/confirmar`): descrito en el ADR 0046, punto 28, sin implementar.
- **Eliminación de cuenta**: faltan la página pública `GET /eliminar-cuenta`, la solicitud desde la oficina, los correos al pedirla y al ejecutarla, la retención mínima de la selfie y el documento y el registro antifraude (ADR 0047, punto 2 de «Retención»), la redacción de los valores personales en la bitácora y la anonimización de drones, fotos del dron, equipos, integrantes y calificaciones.
- **Verificación del teléfono exigida para aprobar** (`verificacion.telefono.exigido_para_aprobar`): la sesión devuelve `usuario.telefono_verificado`, pero la aprobación todavía no lo exige.

### Endpoints de la especificación sin implementar

La especificación §10 los define y la API no los expone:

| Endpoint | Para qué | Quién lo necesita |
|---|---|---|
| `POST /api/incidencias` | Reporte de incidencia del cliente fuera del lote (el piloto ya la registra por `POST /api/sync`). | App `cliente` |
| `POST /api/emergencias` | SOS con aviso de máxima prioridad a soporte y al contacto de emergencia. | Las dos apps |

### Contrato y ejemplos

- 40 operaciones sin ejemplo real. Las que más importan a las apps son el alta (`clientes/registro`, `proveedores/registro`), `ofertas/{id}/rechazar`, `ofertas/{id}/retirar`, `solicitudes/{id}/mensajes/leidos`, la calificación en ambos sentidos, los reclamos (abrir, ver, mensajes, retirar, apelar), la firma y el PDF del acta, y la alta y edición de pilotos, auxiliares, drones y equipos.
- El generador (`bin/lib/generar-ejemplos.php`) escribe instantes de reloj reales, así que cada regeneración cambia `creada_en`, `vence_en` y similares. Las apps no deben comparar esos valores.
- Las pruebas de contrato validan el subconjunto de OpenAPI 3 que emite swagger-php (`$ref`, `allOf`, `oneOf`, `anyOf`, `enum`, `nullable`, formatos `date`, `date-time` y `uuid`); un esquema con `discriminator` o `patternProperties` requiere extender `tests/Soporte/ContratoOpenApi.php`.

### Notificaciones

- El canal in-app (`GET /api/notificaciones`, `POST /api/notificaciones/{id}/leida`) funciona. La notificación push del sistema operativo (oferta con sonido y cuenta regresiva) no tiene proveedor ni registro de token de dispositivo en la API.

### Documentación histórica

- `docs/gestion/cola_tareas.md` es un registro cronológico de tareas con las rutas que tenían al cerrarse; no es una guía y no se reescribe.
