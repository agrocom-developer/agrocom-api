# Plan — Dashboard por rol y sistema de notificaciones interno

**Origen:** el dueño, recorriendo el compose el 22-23/9/2026, encontró que el
dashboard actual (4 tabs — resumen/mapa/lotes/multimedia) es el mismo para
los seis roles internos y no responde a lo que cada uno necesita mirar
primero. De paso, la campana de notificaciones del header es decorativa: no
lleva a ningún lado. Encarga una reestructuración del dashboard en tabs
propios por rol y un motor de notificaciones real. Da la tarea 130 por
encolada en la misma sesión; esta es la continuación.

## 1. Diagnóstico: el mecanismo por rol ya existe, solo falta el agrupamiento

`App\Dominios\Seguridad\Aplicacion\ArmarDashboard::ejecutar()` (tarea 67) ya
decide, por rol activo, **qué secciones** se ven: itera
`SeccionDashboard::cases()` (14 casos —
`app/Dominios/Seguridad/Dominio/SeccionDashboard.php`), evalúa
`$usuario->tienePermisoEnRol($seccion->permiso(), $idRolActivo)` —el MISMO
permiso que gatea la pantalla completa, invariante anti-fuga de la tarea
62— y arma `secciones` (contenido) + `visibles` (claves). Nunca consulta una
tabla directo: pide cada parte a su módulo dueño por Contrato (9 inyectados).

Lo que está fijo, y es exactamente lo que hay que cambiar, es el
**agrupamiento en tabs**: `dashboard.blade.php:20-29` arma un `$tabs` con 4
entradas hardcodeadas (`resumen` agrupa 11 de las 14 claves posibles, más
`mapa`, `lotes`, `multimedia`), igual para cualquier rol — el filtro por rol
ya pasó en `ArmarDashboard`, acá solo decide si esa tab tiene contenido
(`array_intersect($visibles, [...])`). Cambiar el agrupamiento por rol es
tocar esa lista de claves por rol, no reconstruir el mecanismo.

**Regla que ya impone la vista y hay que conservar:** una sola tab visible no
se muestra como pestaña (`dashboard.blade.php:51-53`, "una pestaña
decorativa no es navegación"). Con 3 tabs por rol, ninguno debería caer en
ese caso, pero si un rol no tiene datos para una de sus tabs (campaña sin
abrir, por ejemplo), puede pasar — está bien, es el comportamiento ya
probado.

## 2. Roles — claves exactas

`database/seeders/Catalogo/SeguridadSeeder.php`, const `ROLES`: `piloto`,
`auxiliar` (el "ayudante" del pedido del dueño — ver §5), `jefe_campo`,
`encargado_operaciones`, `dueno`, `admin_plataforma`. Rol activo:
`(int) $request->session()->get('sec_rol_activo_id')`, evaluado siempre
contra el rol activo (invariante 10), nunca contra la unión de roles del
usuario.

**Dos guards separados** (`config/auth.php:34-46`): `interno` (los 6 roles
de arriba, provider `usuarios_internos`) y `cliente` (portal, provider
`usuarios_cliente`, sin fila en `sec_roles`). "Ver como" cruzando a un
usuario del portal cruza de guard — no es solo cambiar `sec_rol_activo_id`
(ver §6).

## 3. Notificaciones actuales — decorativas, por diseño angosto

`resources/views/components/molecules/notifications-menu.blade.php` pinta
cada notificación como `<li>` de texto plano — sin `<a href>`, sin ninguna
acción al click. Un solo emisor la alimenta:
`CascaraPanel::notificaciones()` (`.../Presentacion/CascaraPanel.php:169-181`),
gateado por `operaciones.alerta.ver`, que lee
`LecturaPanelOperaciones::alertasRecientes()` — la tabla real detrás es
`ope_alertas` (migración `2026_09_23_000051`), con 4 tipos técnicos fijos de
equipo (`bateria_caliente`, `dron_sospechoso`, `condiciones_forzadas`,
`suma_excedida`). **No hay ninguna tabla ni modelo de notificación genérica
de flujo de negocio.** `ope_alertas` sigue existiendo y sigue siendo válida
para su caso (alertas técnicas de equipo) — el motor nuevo no la reemplaza,
convive con ella o la absorbe como un tipo más (**decidido en la tarea 141:
convive**, ver §7.1).

## 4. Eventos de dominio — patrón confirmado, un solo caso real

`App\Dominios\Operaciones\Contratos\Eventos\SesionValidada` es hoy el único
evento de dominio con listener real: una closure inline en
`FinanzasServiceProvider::boot()` (línea 54), no una clase `Listener`
aparte. `ComercialServiceProvider` y `MantenimientoServiceProvider` dejan el
mismo comentario ("mismo patrón que `FinanzasServiceProvider` con
`SesionValidada`") sin implementarlo todavía. No existe ningún módulo
`Notificaciones` ni `Plataforma` — los módulos de dominio hoy son:
`Campania`, `Comercial`, `Compartido`, `Distribucion`, `Finanzas`,
`Inventario`, `Mantenimiento`, `Mezclas`, `Operaciones`, `Personal`,
`Portal`, `Seguridad`, `Sincronizacion`.

## 5. Contenido pedido por rol (dictado por el dueño, 22-23/9/2026)

- **Dueño** (tarea 135, va con el mecanismo): estado de cuentas de clientes
  y contratos; resumen de trabajos actuales por equipo; progreso en toda la
  campaña.
- **Encargado de Operaciones** (136): estados de las aplicaciones; proceso
  de los trabajos; resumen de vuelos (diario, semanal o mensual). Hecha el
  23/9/2026: «aplicaciones» se leyó como las **órdenes de aplicación**
  (`ope_ordenes_aplicacion`, sus seis estados), no como las sesiones de vuelo
  —esas siguen en `DistribucionSesiones`, sin tab propia—; «proceso de los
  trabajos» agrupa la cola de validación y las pausas del mes; el resumen de
  vuelos muestra los últimos 14 días, 8 semanas (lunes a domingo) o 6 meses,
  según el selector, sobre hectáreas validadas.
- **Piloto y Ayudante** (137 — "ayudante" es como el dueño llama al rol
  `auxiliar`; la tarea decide si es solo el label visible o si el dueño
  quiere el rol renombrado en serio, y en ese caso NO lo hace sin
  confirmarlo primero, por lo invasivo de tocar la clave de un rol): estado
  de sus devengos, trabajos pendientes y realizados. Hecha el 23/9/2026:
  ambos roles comparten el agrupamiento —`Mis devengos` (`MiLiquidacion`) y
  `Mis trabajos` (`MisSesiones` + `MisEquipos`)— porque ven exactamente las
  mismas tres secciones, todas acotadas a su `persona_id`. Sobre el nombre:
  solo se cambió el texto visible —`seguridad.rol.meta.auxiliar.nombre` y
  `personal.roles.auxiliar` pasan a «Ayudante», como ya decían las pantallas
  de cuadrillas, tarifas y estadías—; la clave `auxiliar` de `sec_role`, los
  permisos y el enum `RolOperativoPersona` no se tocaron. Renombrar la clave
  en serio sigue esperando la confirmación del dueño.
- **Jefe de Campo** (138): recursos que se ocupan o faltan; órdenes de
  trabajo con sus cuadrillas; estado de todas las órdenes de aplicación
  (considerando haciendas y estado del equipamiento). Hecha el 23/9/2026,
  en tres tabs de solo lectura: `Recursos` (`RecursosEnUso`: las cuadrillas
  con algún trabajo abierto, con sus integrantes de hoy y su dron, vehículo,
  generador y baterías con el estado de cada pieza; y debajo `Stock`, lo que
  falta), `Órdenes de trabajo` (`OrdenesTrabajoPorCuadrilla`: las tandas de
  las órdenes aún abiertas, agrupadas por cuadrilla) y `Órdenes y
  equipamiento` (`OrdenesConEquipamiento`: todas las órdenes abiertas más las
  4 terminadas más recientes, con sus haciendas —de los lotes que la orden
  copió del contrato—, las cuadrillas asignadas contra las necesarias y las
  piezas de equipamiento que NO están operativas). «Órdenes de trabajo» se
  leyó como las tandas de `ope_ordenes_trabajo` (la pantalla `/panel/trabajos`)
  y «equipamiento» como lo que `per_equipo_recursos` le asigna hoy a cada
  cuadrilla; el estado del dron sale de sus órdenes de mantenimiento abiertas
  (`ope_drones` no tiene columna de estado). Contrato nuevo en `Mantenimiento`
  (`LecturaEstadoEquipamiento`): ningún contrato existente daba el estado en
  lote. **Permisos:** el jefe de campo NO tenía `operaciones.orden.ver`,
  `inventario.movimiento.ver` ni `personal.equipo_trabajo.ver`, y cada sección
  se gatea con el permiso de su pantalla completa (tarea 62); sin ellos no
  veía ni el stock que el pedido daba por hecho. Se le concedieron los tres,
  solo lectura (`SeguridadSeeder::PERMISOS_JEFE_CAMPO`): abren el listado de
  órdenes, el stock y las cuadrillas, pero no ningún alta ni edición. A
  cambio, con las tres tabs el jefe ya no ve en su tablero la cola de
  validación, las pausas, el mapa ni el resumen por lote (siguen en el menú).
- **Administrador de plataforma** (139): no es operativo ni financiero, es
  técnico — usuarios, bitácoras, accesos directos a configuración del
  sistema. Mapea casi directo a permisos que ya existen:
  `seguridad.usuario.ver`, `seguridad.bitacora.ver`,
  `seguridad.configuracion.ver`, `seguridad.organizacion.ver`,
  `seguridad.dispositivo.ver`, `distribucion.version.autorizar`. Hecha el
  23/9/2026, en dos tabs de solo lectura: `Usuarios y accesos`
  (`UsuariosPorRol`: las cuentas internas activas de cada rol del catálogo,
  con el total y las cuentas del portal aparte; `DispositivosConSesion`: los
  dispositivos de campo con sesión abierta, de la misma consulta que
  `/panel/dispositivos`; `VersionesApk`: la versión autorizada y las que
  esperan autorización) y `Bitácora y configuración` (`BitacoraReciente`: las
  8 últimas entradas de la auditoría, con la fecha en la zona de quien mira; y
  dos accesos directos —`AccesoConfiguracion` y `AccesoOrganizacion`— que son
  solo enlaces). Seis `SeccionDashboard` nuevos, cada uno con el permiso de su
  pantalla. El rol tiene todos los permisos del catálogo, así que lo
  operativo y lo financiero quedan fuera por lo que `tabsPara('admin_plataforma')`
  NO nombra. «Pendiente de autorizar» es una versión `pendiente` más nueva que
  la vigente: al autorizar otra, la anterior vuelve a `pendiente` (no a
  `rechazada`) y no espera autorización de nadie. Contrato nuevo en
  `Distribucion` (`LecturaVersionesApk`); ningún otro módulo se tocó. El
  encabezado del tablero también es propio de este rol (`Estado de la
  plataforma`, sin «operación de hoy» ni «corte de planilla»).

Ninguna de las cuatro tareas de rol (136-139) depende de las otras — cada
una toca su propio agrupamiento de tabs y, cuando haga falta, una sección
nueva en `SeccionDashboard`. Todas dependen de 135 (el mecanismo).

## 6. "Ver como" (140) — por qué es delicada

El dueño quiere que el administrador vea el dashboard/portal como un usuario
particular de cualquier rol, **incluido el portal del cliente** (decisión
del dueño, 23/9/2026: si lo necesita pronto, se hace completo desde el
principio en vez de una versión recortada). Ver como otro rol interno es
manejable: es el mismo guard, cambia a qué `sec_rol_activo_id` se evalúa (de
lectura, nunca de escritura). Ver como un usuario del portal cruza a un
guard distinto (`cliente`, provider `usuarios_cliente`) — técnicamente es
una impersonación, no un cambio de rol activo, y toca el scoping del portal
del cliente: la única cosa que `CLAUDE.md` nombra explícitamente como que
"nunca se delega sin revisión línea por línea" (invariante 5). Por eso la
139... la **140** queda marcada crítica de entrada y anotada en
`runs/revision-pendiente.txt` apenas se integre, sin esperar a que alguien
la encuentre.

## 7. Motor de notificaciones (141) — por qué es su propia tarea, y por qué al final

Es la pieza más nueva arquitectónicamente: ningún módulo `Notificaciones`
existe, el único patrón de evento de dominio real (`SesionValidada`) usa
closures inline por `ServiceProvider`, no una infraestructura de entrega
genérica. Construir el motor (tabla, reglas de "a quién avisa cada evento",
conectar `notifications-menu.blade.php` a datos reales con acción al click)
es una decisión de arquitectura nueva — la tarea escribe su propio ADR antes
de tocar código, como hizo la 0023 con el pago por trabajo. Va última en el
orden (135→141) porque no bloquea ni depende de las seis anteriores: los
dashboards de rol no necesitan que exista el motor para tener sus tabs, y el
motor no necesita que los dashboards estén rehechos para tener su primera
cadena de referencia (contrato → notifica Encargado de Operaciones; orden de
aplicación/trabajo creada → notifica al equipo asignado; trabajo marcado
como realizado → notifica Jefe de Campo, Encargado de Operaciones y,
opcionalmente, Dueño).

### 7.1 Cómo quedó (tarea 141, 23/9/2026)

Las decisiones de fondo están en el ADR 0025 («Motor de notificaciones interno:
un módulo, un evento por hecho, un aviso por cuenta»). Resumen, para no tener
que abrir el ADR:

- **Módulo `Notificaciones`, prefijo `ntf_`**, con una sola tabla,
  `ntf_notificaciones`: una fila por **cuenta** destinataria, creada al ocurrir
  el hecho. Soft delete, autoría y bitácora como todo modelo de dominio.
- **Quién recibe.** Las reglas declaran destinatarios por **rol**, por
  **persona** o por **equipo**, nunca por cuenta. «Por rol» es **rol asignado**
  (`sec_user_role`), no rol activo: una notificación no es un permiso
  (invariante 10), así que Abraham —piloto y jefe de campo— recibe los avisos del
  jefe de campo aunque hoy opere como piloto. Al **abrir**, el rol activo sí
  manda: el click lleva al recurso si ese rol puede verlo y, si no, a su primera
  pantalla (un piloto no tiene `operaciones.trabajo.ver` y cae en su tablero,
  no en un 403). Quien dispara el hecho no se avisa a sí mismo.
- **Un listener genérico** atiende todos los eventos; cada evento tiene una
  **regla** en `Notificaciones/Aplicacion/Reglas/`. Los eventos son DTO de
  primitivos en `Contratos/Eventos/` del módulo dueño y salen **después** de
  persistir, con `ShouldDispatchAfterCommit`. Un fallo al avisar se reporta y no
  rompe la operación de negocio.
- **Idempotencia** por `UNIQUE (usuario_id, clave_evento)`, con la clave armada
  con el id del recurso (`contrato_creado:12`), no parcial: un aviso dado de baja
  no resucita por un reintento.
- **`ope_alertas` convive**: la campana mezcla las dos fuentes (el motor y las
  alertas técnicas) y `CampanaDeAvisos` prioriza lo no leído. Las alertas ganan el
  enlace a `/panel/alertas`.

Primera cadena, de punta a punta:

| Hecho | Evento (módulo) | Destinatarios | Lleva a |
|---|---|---|---|
| Contrato creado | `ContratoCreado` (Comercial) | rol `encargado_operaciones` | el contrato |
| Orden de trabajo creada | `OrdenTrabajoCreada` (Operaciones) | integrantes vigentes de cada equipo asignado | la orden de trabajo |
| Trabajo cerrado en campo | `TrabajoCerrado` (Operaciones) | roles `jefe_campo` y `encargado_operaciones` | el trabajo |

Notas de lectura: la orden de **aplicación** no tiene equipo cuando nace, así
que «notifica al equipo» sale de la orden de trabajo; el «trabajo marcado como
realizado» del pedido es la transición `abierto → cerrado`, que hace el piloto
desde la app de campo; y **el dueño no recibe el «trabajo cerrado»** (es el aviso
más frecuente y su tablero ya muestra el avance) — sumarlo es una línea en
`ReglaTrabajoCerrado`.

Pendiente de integrar en el repo (el turno noche congela `tests/`,
`docs/decisiones/` y `.claude/`, y el prompt de la 141 no declara `descongela=`):
el ADR 0025 con su fila del prefijo `ntf_` en el ADR 0011, los tests de
idempotencia y aislamiento entre cuentas y la nota del skill `dominio-backend`
quedaron como parches verificados con `git apply --check`.

**Coordinación con la tarea 140 («ver como»):** `abrir` escribe `leida_en`. Con
el modo de solo lectura de la 140 activo esa escritura lanzaría; quien integre las
dos tiene que hacer que `abrir` redirija sin marcar leído en ese modo.

## 8. Orden de la cola

135 (mecanismo + Dueño) → 136 (Encargado Operaciones) → 137 (Piloto/Ayudante)
→ 138 (Jefe de Campo) → 139 (Administrador) → 140 (Ver como, crítica) → 141
(Motor de notificaciones, crítica). Ver cada prompt individual
(`prompts/135-*.md` a `prompts/141-*.md`) para el detalle técnico de cada
una.
