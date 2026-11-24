# Demo local del marketplace

**Agrocom SRL · Documento vigente**

Una API local con datos de ejemplo para recorrer las tres apps (`cliente`, `piloto` y `auxiliar` de `agrocom-field`) de punta a punta: el cliente pide un servicio, el proveedor lo recibe como oferta con cuenta regresiva, lo acepta, sale en camino, lo ejecuta en el lote, el cliente da su conformidad, califica y, si hace falta, abre una disputa.

## Un solo comando

```bash
bin/demo up      # postgres en docker + migra + siembra + sirve la API
```

| Subcomando | Qué hace |
|---|---|
| `bin/demo up` | Levanta postgres en docker (un puerto libre desde el 55440), migra, siembra catálogo y demo, y sirve la API en `0.0.0.0:8000`. Se puede repetir: solo completa lo que falta y renueva la vigencia de las ofertas. |
| `bin/demo seed` | Vuelve a sembrar sobre la base actual (idempotente) y renueva la cuenta regresiva de las ofertas y las ventanas de fechas que caducaron. |
| `bin/demo reset` | Borra todos los esquemas de la base, migra y siembra de cero. |
| `bin/demo down` | Detiene la API y postgres; los datos se conservan. Con `--borrar` elimina también el contenedor y su volumen. |
| `bin/demo estado` | Dice qué está corriendo. |
| `bin/demo logs` | Sigue el log de la API (`storage/demo/`). |

Variables de entorno opcionales: `DEMO_PORT` (puerto de la API, 8000), `DEMO_HOST` (interfaz, `0.0.0.0`), `DEMO_DB_PORT` (puerto de postgres), `DEMO_NOMBRE` (nombre del contenedor, `agrocom-demo-pg`), `DEMO_PHP` (binario de PHP) y `DEMO_SCHEDULER=1` para correr también el planificador: con él las ofertas vencen y escalan de ronda como en producción; sin él quedan vigentes mientras recorres la demo.

El comando no lee ni escribe el `.env`: toda la configuración entra por variables de entorno del proceso y el estado (clave de la app, pid, log) vive en `storage/demo/`, fuera de git. Para levantar una segunda demo en paralelo basta con otro nombre y otros puertos: `DEMO_NOMBRE=agrocom-demo-2 DEMO_PORT=8001 bin/demo up`.

## A dónde apuntar las apps

| Dónde corre la app | `API_BASE_URL` |
|---|---|
| Simulador de iOS o escritorio | `http://localhost:8000` |
| Emulador de Android | `http://10.0.2.2:8000` |
| Teléfono en la misma red | `http://<ip-de-tu-equipo>:8000` (`bin/demo up` imprime la IP) |

El tráfico `http` a `localhost` en iOS necesita la excepción de transporte solo en las compilaciones de desarrollo; las de release usan HTTPS.

## Usuarios

Contraseña de todos: `0000`. El login es por `username` y pide `uuid_dispositivo`; si la cuenta tiene más de un rol, la respuesta `4xx` los lista y se repite el pedido con `role_id`.

| Usuario | Rol | Qué muestra |
|---|---|---|
| `agro.sanrafael` | cliente | El recorrido completo: solicitudes en casi todos los estados, calificada y disputas. |
| `luis.antelo` | cliente | Servicios de Mamani Agroservicios y dos solicitudes ofertadas a terceros. |
| `coop.valle.grande` | cliente | Servicios de Fumigaciones del Oriente, uno sin proveedor. |
| `rodrigo.suarez` | piloto (Agrocom, equipo con auxiliar) | Ofertas vigentes, un servicio asignado y uno en ejecución. |
| `valeria.montano` | piloto (Agrocom, equipo sin auxiliar) | Un servicio en camino y otro suspendido por clima. |
| `diego.parada` | auxiliar (Agrocom) | Los servicios del equipo de Rodrigo. |
| `carlos.mamani` | piloto `1` y proveedor `7` (independiente) | Ofertas vigentes de terceros, un servicio asignado y uno en ejecución, calificaciones recibidas. |
| `esteban.choque` | auxiliar de Carlos | Los servicios del equipo de Carlos. |
| `fumigaciones.oriente` | proveedor (tercero) | Su flota, su oferta vigente y un servicio asignado. |
| `marcelo.terrazas`, `ivan.cuellar` | pilotos del tercero | Marcelo vuela en el equipo; Iván tiene documentos pendientes. |
| `lucia.flores` | auxiliar del tercero | Los servicios del equipo de Marcelo. |
| `rosa.condori` | proveedor independiente **pendiente** | La app no le muestra ofertas hasta que se apruebe. |
| `alberto.hurtado` | proveedor tercero **rechazado** | El motivo del rechazo y cómo reenviar. |

La cuenta de panel del administrador es `miguelo` (la crea el catálogo de arranque).

## Qué hay sembrado

Cada solicitud nace con un `uuid_cliente` determinista, así que volver a sembrar no duplica nada. Todas las fechas son relativas a hoy.

| Estado | Dónde verlo |
|---|---|
| `borrador` | `agro.sanrafael` (dos), `luis.antelo`, `coop.valle.grande` (dos) |
| `en_asignacion` con ofertas a propios | `agro.sanrafael`, `luis.antelo` y `coop.valle.grande`; el equipo de Agrocom las ve en `GET /api/ofertas` |
| `en_asignacion` con ofertas a terceros | `luis.antelo` (una programada y una urgente para `carlos.mamani`) y `coop.valle.grande` (una para `fumigaciones.oriente`) |
| `sin_proveedor` | `agro.sanrafael`, `coop.valle.grande` |
| `asignada` | `agro.sanrafael` (Rodrigo), `luis.antelo` (Carlos), `coop.valle.grande` (Oriente) |
| `en_camino` | `agro.sanrafael` (Valeria) |
| `en_ejecucion` | `agro.sanrafael` (Rodrigo), `luis.antelo` (Carlos) |
| `suspendida_por_clima` | `agro.sanrafael` (Valeria) |
| `ejecutada`, a la espera de conformidad | `agro.sanrafael`, `luis.antelo`, `coop.valle.grande` |
| `completada`, calificada por ambas partes | `agro.sanrafael`, `luis.antelo`, `coop.valle.grande` |
| `completada`, aún sin calificar | `luis.antelo` |
| `en_disputa` y disputa resuelta con reembolso parcial | `agro.sanrafael` |
| `cancelada` | `agro.sanrafael`, `luis.antelo` |

Además: seis terrenos con zona y polígono (la oferta muestra la zona y la ubicación aproximada), proveedores en cada estado de aprobación con sus documentos, drones, equipos y disponibilidad en línea, calificaciones y reputación de cada proveedor, y avisos in-app para cada cuenta. Cada piloto y auxiliar tiene su persona operativa vinculada (`usuario.persona_id` en `GET /api/auth/sesion`): es el `piloto_id` y el `auxiliar_id` de las sesiones de vuelo.

Las ofertas pendientes recuperan 3 horas de cuenta regresiva cada vez que corre `bin/demo up` o `bin/demo seed`.

## El recorrido

1. **Cliente.** `agro.sanrafael` crea una solicitud sobre su terreno (`POST /api/solicitudes`), ve la estimación (`GET /api/solicitudes/{id}/estimacion`) y la publica (`POST /api/solicitudes/{id}/publicar`). El motor de asignación la ofrece a los propios.
2. **Oferta.** `rodrigo.suarez` o `valeria.montano` ven la oferta con su cuenta regresiva (`GET /api/ofertas`) y la aceptan (`POST /api/ofertas/{id}/aceptar`). Si la rechazan o vence, escala a independientes y terceros: `carlos.mamani` (con `role_id` 7) recibe las de su zona.
3. **Asignada.** El cliente recibe el aviso y ve la solicitud `asignada` con su proveedor. El equipo la baja del lote con `GET /api/sync/catalogo`.
4. **En camino.** El proveedor avisa con `POST /api/solicitudes/{id}/en-camino`; el cliente recibe el aviso.
5. **En el lote.** El piloto abre el trabajo y las sesiones (`POST /api/sync` con `trabajo`, `sesion`, `condiciones`, `mezcla`, `recarga`, `incidencia` y los cierres) y sube las evidencias (`POST /api/evidencias`). Al cerrar los trabajos que cubren lo pedido, la solicitud pasa a `ejecutada` y el cliente recibe el aviso.
6. **Conformidad.** El cliente da su conformidad (`POST /api/solicitudes/{id}/conformidad`) o abre una disputa (`POST /api/solicitudes/{id}/disputas`).
7. **Calificación.** El cliente califica al proveedor, al piloto y al auxiliar (`POST /api/solicitudes/{id}/calificaciones`); el proveedor califica al cliente en privado (`POST /api/solicitudes/{id}/calificar-cliente`) y ve su reputación (`GET /api/reputacion`, `GET /api/calificaciones/recibidas`).

Para probar a mano:

```bash
curl -s -X POST http://localhost:8000/api/auth/token \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"username":"agro.sanrafael","password":"0000","uuid_dispositivo":"11111111-1111-4111-8111-111111111111"}'
```

La respuesta trae `token`; las demás peticiones llevan `Authorization: Bearer <token>`. El contrato de cada endpoint es `docs/api/openapi.yaml`.

## Lo que no hace la demo

- No envía notificaciones push del sistema operativo: los avisos viven en la campana de la app (`GET /api/notificaciones`).
- Las evidencias se guardan en el disco local, no en el almacenamiento de objetos.
- Las contraseñas de las cuentas demo (`0000`) no cumplen la política de registro público (8 caracteres y una letra): se siembran por los casos de uso, no por el endpoint de registro.
- La lista `personas` de `GET /api/sync/catalogo` es global: incluye al personal de Agrocom y a los operarios de los demás proveedores.
