# Panel: la torre de control del marketplace

**Agrocom SRL · Documento vigente**

El panel web es la torre de control del administrador de la plataforma: desde él se ve el estado del mercado, se aprueba a quien presta el servicio, se modera la calidad y se interviene cuando una solicitud lo necesita. No ejecuta servicios ni registra trabajo de campo: eso lo hacen las apps. La referencia funcional es `docs/especificacion/marketplace.md` (§10.4) y `docs/especificacion/negociacion-contrato.md`.

## Pantallas

| Módulo del menú | Pantalla | Ruta | Qué hace |
|---|---|---|---|
| Torre de control | Tablero | `panel/dashboard` | Lo que espera una decisión (colas de aprobación, moderación y disputas), solicitudes por estado, demanda abierta por macrorregión, ofertas vigentes, tasa de adjudicación, tiempo hasta adjudicar, hectáreas y monto acordados, precio acordado frente al propuesto y calificación. Período de 7, 30 o 90 días. |
| | Solicitudes | `panel/solicitudes` | Lista con filtros por estado, región, zona y búsqueda. La ficha muestra lo pedido, el terreno, la negociación ronda por ronda (ofertas y contraofertas de cada proveedor), la ejecución (inicio, avances, fotos y última ubicación del piloto), el chat entre las partes y el historial. Intervenir es cancelar con motivo cuando el estado lo permite. |
| | Disputas | `panel/disputas` | Cola, mensajes, mediación y resolución con resultado y motivo. |
| | Calificaciones | `panel/calificaciones` | Moderación: ocultar con causa y motivo, o dejar visible. |
| | Métricas del mercado | `panel/metricas` | Mercado, calidad, oferta y volumen del período. |
| Proveedores | Proveedores, pilotos, auxiliares | `panel/proveedores`, `panel/pilotos`, `panel/auxiliares` | Aprobar, rechazar con motivo, suspender, rehabilitar y dar de baja. |
| | Drones | `panel/drones-flota` | Ficha con modelo, año y capacidad, documentos y las fotos que subió el proveedor. Cada foto se aprueba o se rechaza con motivo. Un dron no se aprueba sin una foto aprobada de cada tipo de `config/flota.php` (`fotos_obligatorias`). |
| | Equipos | `panel/equipos-flota` | Piloto, dron y auxiliar de cada equipo. |
| | Documentos | `panel/documentos` | Cola de revisión de licencias, seguros y registros. |
| Clientes y zonas | Clientes | `panel/clientes` | Lista y ficha con terrenos y solicitudes; suspender con motivo o reactivar. |
| | Zonas | `panel/zonas` | Zonas de Santa Cruz con su macrorregión, terrenos y demanda abierta; edición del nombre y la macrorregión. |
| | Avisos | `panel/avisos` | Lo que el sistema avisó a cada cuenta, y si lo leyó. Solo lectura. |
| Seguridad | Usuarios, roles, dispositivos, organización, versiones del APK, configuración y bitácora | `panel/usuarios`, `panel/roles`, … | Administración de cuentas, roles y permisos, y auditoría. |

Los números del tablero y de los avisos pendientes del menú salen del contrato `Metricas\Contratos\LecturaTablero`; la ficha de una solicitud lee el trato y la ejecución por `Negociacion\Contratos\LecturaNegociacionPanel`. Las fotos se sirven desde el disco privado por rutas del panel, nunca por una URL pública.

## Roles y permisos

Las acciones se autorizan por permiso del rol activo, verificado dentro de cada controlador.

| Rol | Alcance en el panel |
|---|---|
| `admin_plataforma` | Todo el catálogo, incluida la vista «como otra cuenta». |
| `dueno` | Todo el catálogo salvo lo reservado a la plataforma. |
| `encargado_operaciones` | Cuentas, aprobaciones, solicitudes, clientes, zonas, moderación, disputas, métricas y avisos. |
| `soporte` | Ve solicitudes (con chat, fotos y ubicación), clientes, zonas, calificaciones, proveedores y flota; atiende disputas y puede cancelar una solicitud con motivo. No aprueba, no suspende, no modera ni resuelve. |
| `cliente`, `proveedor`, `piloto`, `auxiliar` | Operan por la API; no tienen pantallas. |

El chat, las fotos y la ubicación del piloto son datos de las partes: los protege el permiso propio `solicitudes.solicitud.ver_seguimiento`, que ningún rol de la app tiene.

## Diseño

Sobrio y legible. El acento es el verde de marca (`--ag-color-primary`, `#1e7a34`; verde oscuro `#0f3d1a` en hover y rieles); los enlaces usan el mismo verde en claro y oscuro. Todo color sale de un token CSS, el panel respeta el modo oscuro y los textos escalan sin romper el layout. Cada pantalla tiene su estado vacío, de error y de éxito con texto en español.

## Verificación

- `tests/Unit/TorreDeControlTest.php`: rutas con sesión y rol activo, menú coherente con el catálogo, alcance de `soporte`, permisos retirados y reglas del cliente y de las fotos del dron.
- `tests/Unit/PanelHomogeneoTest.php`: cada listado, formulario y ficha sigue el patrón del panel.
- Se recorre con `bin/demo up`, que siembra solicitudes en todos los estados, fotos, mensajes y ubicación; el administrador es `miguelo` (contraseña `0000`).
