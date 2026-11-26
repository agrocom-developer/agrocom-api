# Demo local del marketplace

**Agrocom SRL · Documento vigente**

Una API local con datos de ejemplo para recorrer las dos apps (`cliente` y `piloto` de `agrocom-field`) de punta a punta: el cliente abre una solicitud con su precio por hectárea, los pilotos la ven en el mapa y ofertan o contraofertan, el cliente compara y elige, el piloto ejecuta y sube fotos de avance, el cliente ve el avance, conversa por el chat y ve la última ubicación del piloto, y al final califica o abre un reclamo.

## Un solo comando

```bash
bin/demo up      # postgres en docker + migra + siembra + sirve la API
```

| Subcomando | Qué hace |
|---|---|
| `bin/demo up` | Levanta postgres en docker (un puerto libre desde el 55440), migra, siembra catálogo y demo, y sirve la API en `0.0.0.0:8000`. Se puede repetir: solo completa lo que falta y renueva la vigencia de las solicitudes y ofertas abiertas. |
| `bin/demo seed` | Vuelve a sembrar sobre la base actual (idempotente) y renueva la vigencia de las solicitudes y ofertas abiertas y las ventanas de fechas que caducaron. |
| `bin/demo reset` | Borra todos los esquemas de la base, migra y siembra de cero. |
| `bin/demo down` | Detiene la API y postgres; los datos se conservan. Con `--borrar` elimina también el contenedor y su volumen. |
| `bin/demo estado` | Dice qué está corriendo. |
| `bin/demo logs` | Sigue el log de la API (`storage/demo/`). |

Variables de entorno opcionales: `DEMO_PORT` (puerto de la API, 8000), `DEMO_HOST` (interfaz, `0.0.0.0`), `DEMO_DB_PORT` (puerto de postgres), `DEMO_NOMBRE` (nombre del contenedor, `agrocom-demo-pg`), `DEMO_PHP` (binario de PHP) y `DEMO_SCHEDULER=1` para correr también el planificador: con él las ofertas y solicitudes vencen como en producción (`negociacion:procesar` cada minuto); sin él quedan vigentes mientras recorres la demo.

El comando no lee ni escribe el `.env`: toda la configuración entra por variables de entorno del proceso y el estado (clave de la app, pid, log) vive en `storage/demo/`, fuera de git. Para levantar una segunda demo en paralelo basta con otro nombre y otros puertos: `DEMO_NOMBRE=agrocom-demo-2 DEMO_PORT=8001 bin/demo up`.

## A dónde apuntar las apps

| Dónde corre la app | `API_BASE_URL` |
|---|---|
| Simulador de iOS o escritorio | `http://localhost:8000` |
| Emulador de Android | `http://10.0.2.2:8000` |
| Teléfono en la misma red | `http://<ip-de-tu-equipo>:8000` (`bin/demo up` imprime la IP) |

El tráfico `http` a `localhost` en iOS necesita la excepción de transporte solo en las compilaciones de desarrollo; las de release usan HTTPS.

## Usuarios

Contraseña de todos: `0000`. El login es por `username` y pide `uuid_dispositivo`; si la cuenta tiene más de un rol, la respuesta lista los roles y se repite el pedido con `role_id`.

| Usuario | Rol | Qué muestra |
|---|---|---|
| `agro.sanrafael` | cliente | Una solicitud con cuatro ofertas y contraofertas en curso, una en ejecución con fotos y ubicación, una calificada, una reabierta y un borrador. |
| `luis.antelo` | cliente | Solicitudes abiertas en la Chiquitania, una con una oferta rechazada, una adjudicada con chat, una finalizada sin calificar y una cancelada. |
| `coop.valle.grande` | cliente | Abiertas en valles y en la Chiquitania, una adjudicada con chat, una en ejecución, una en reclamo y una expirada. |
| `rodrigo.suarez` | piloto (Agrocom) | El mapa de abiertas, su negociación por 100 ha y su servicio en ejecución. |
| `valeria.montano` | piloto (Agrocom) | Un servicio ya calificado por el cliente. |
| `carlos.mamani` | piloto y proveedor (independiente) | Ofertas vigentes, un servicio adjudicado y uno finalizado, calificaciones recibidas. |
| `fumigaciones.oriente` | proveedor (tercero) | Su flota y sus negociaciones. |
| `marcelo.terrazas`, `ivan.cuellar` | pilotos del tercero | Marcelo recibe avisos solo de la Chiquitania y los valles, desde 50 ha; Iván tiene documentos pendientes. |
| `rosa.condori` | proveedor independiente **pendiente** | La app no le muestra solicitudes hasta que se apruebe. |
| `alberto.hurtado` | proveedor tercero **rechazado** | El motivo del rechazo y cómo reenviar. |

La cuenta de panel del administrador es `miguelo` (la crea el catálogo de arranque).

## Qué hay sembrado

Cada solicitud nace con un `uuid_cliente` determinista, así que volver a sembrar no duplica nada. Los pasos de cada una (oferta, contraoferta, mensaje, foto, avance) llevan marcas de hora espaciadas hacia atrás desde el momento de la siembra: todo es relativo a `now()`.

| Estado | Solicitud | Qué muestra |
|---|---|---|
| `borrador` | `agro.sanrafael`, Las Palmas, 30 ha | Antes de abrirla. |
| `abierta`, sin ofertas | `luis.antelo` (Estancia El Cerro, Chiquitania), `coop.valle.grande` (Hacienda Velasco, Chiquitania; Fundo Los Valles, valles), `agro.sanrafael` (Lote Norte, norte integrado) | El mapa del piloto repartido por región; la de Warnes es urgente y otra se reabrió porque el proveedor devolvió el servicio. |
| `con_ofertas` | `agro.sanrafael`, Las Palmas, 100 ha | Tres proveedores ofertan; el cliente y Agrocom negocian por rondas (135, 122, 128) y Carlos baja a 118 y recibe 115. |
| `con_ofertas` | `luis.antelo`, Lote Sur, 90 ha | Una oferta que acepta el precio propuesto y otra que el cliente rechazó. |
| `adjudicada` | `coop.valle.grande` (Marcelo, 118 por ha), `luis.antelo` (Carlos, 120 por ha) | Precio acordado congelado, datos de contacto entregados y chat con mensajes. |
| `en_ejecucion` | `agro.sanrafael` (Rodrigo, 64 %), `coop.valle.grande` (Marcelo, 25 %) | Fotos de avance, chat y la última ubicación del piloto. |
| `finalizada` | `luis.antelo`, Lote Sur (Carlos) | Hectáreas ejecutadas y monto final a pagar; el cliente aún no califica. |
| `calificada` | `agro.sanrafael`, Las Palmas (Valeria) | Calificación del cliente y del proveedor. |
| `disputada` | `coop.valle.grande`, Lote Este (Marcelo) | Un reclamo abierto con un mensaje de soporte. |
| `cancelada` | `luis.antelo`, La Esperanza | Sin penalidad. |
| `expirada` | `coop.valle.grande`, Parcela Central | Se puede renovar dentro de 30 días. |

Además: nueve terrenos con zona, región, departamento y provincia (todos de Santa Cruz) y polígono (valles, Chiquitania, norte integrado y Chaco), proveedores en cada estado de aprobación con sus documentos, drones y equipos, calificaciones y reputación, y avisos in-app para cada cuenta (nuevas solicitudes en su zona, ofertas, mensajes, avance). Cada piloto tiene su persona operativa vinculada (`usuario.persona_id` en `GET /api/auth/sesion`).

Cada vez que corre `bin/demo up` o `bin/demo seed`, las solicitudes y ofertas abiertas recuperan su vigencia (cinco días y 24 horas).

## El recorrido

1. **Cliente.** `agro.sanrafael` crea una solicitud sobre su terreno con `precio_ha_propuesto` (`POST /api/solicitudes`), ve la estimación (`GET /api/solicitudes/{id}/estimacion`) y la abre (`POST /api/solicitudes/{id}/publicar`). Los pilotos con avisos activos de esa zona reciben la notificación.
2. **Mapa.** Un piloto o proveedor ve las solicitudes abiertas (`GET /api/solicitudes-abiertas`, con filtros de zona, región, hectáreas y precio), el resumen por región (`GET /api/solicitudes-abiertas/resumen-zonas`) y el detalle con los lotes antes de ofertar (`GET /api/solicitudes-abiertas/{id}`). Nada de eso trae datos del cliente.
3. **Oferta.** El proveedor acepta el precio propuesto o contraoferta el suyo (`POST /api/solicitudes/{id}/ofertas`).
4. **Negociación.** El cliente compara (`GET /api/solicitudes/{id}/ofertas`), contraoferta (`POST /api/ofertas/{id}/contraofertar`), rechaza o acepta (`POST /api/ofertas/{id}/aceptar`). Aceptar adjudica: las demás ofertas se cierran y se abren el contacto y el chat.
5. **Chat.** Cliente y proveedor conversan (`GET` y `POST /api/solicitudes/{id}/mensajes`, con `despues` como cursor).
6. **Ejecución.** El piloto marca el inicio (`POST /api/solicitudes/{id}/inicio`), el avance (`POST /api/solicitudes/{id}/avance`), sube fotos (`POST /api/solicitudes/{id}/evidencias`) y envía su última ubicación (`PUT /api/ubicacion`). El cliente ve el avance (`GET /api/solicitudes/{id}/avance`) y el punto del piloto (`GET /api/solicitudes/{id}/ubicacion-piloto`).
7. **Cierre.** El piloto cierra con las hectáreas ejecutadas (`POST /api/solicitudes/{id}/cierre`): la solicitud queda `finalizada` con el monto a pagar. El pago se acuerda fuera de la app.
8. **Calificación.** El cliente califica al proveedor y al piloto (`POST /api/solicitudes/{id}/calificaciones`); el proveedor califica al cliente en privado (`POST /api/solicitudes/{id}/calificar-cliente`) y ve su reputación (`GET /api/reputacion`). Cualquiera de las partes puede abrir un reclamo.

Para probar a mano:

```bash
curl -s -X POST http://localhost:8000/api/auth/token \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"username":"agro.sanrafael","password":"0000","uuid_dispositivo":"11111111-1111-4111-8111-111111111111"}'
```

La respuesta trae `token`; las demás peticiones llevan `Authorization: Bearer <token>`. El contrato de cada endpoint es `docs/api/openapi.yaml`.

## Lo que no hace la demo

- No envía notificaciones push del sistema operativo: los avisos viven en la campana de la app (`GET /api/notificaciones`).
- Las fotos se guardan en el disco local, no en el almacenamiento de objetos.
- Las contraseñas de las cuentas demo (`0000`) no cumplen la política de registro público (8 caracteres y una letra): se siembran por los casos de uso, no por el endpoint de registro.
- La lista `personas` de `GET /api/sync/catalogo` es global: incluye al personal de Agrocom y a los operarios de los demás proveedores.
