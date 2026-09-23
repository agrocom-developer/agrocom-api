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
convive con ella o la absorbe como un tipo más (decide la tarea 141).

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
  de los trabajos; resumen de vuelos (diario, semanal o mensual).
- **Piloto y Ayudante** (137 — "ayudante" es como el dueño llama al rol
  `auxiliar`; la tarea decide si es solo el label visible o si el dueño
  quiere el rol renombrado en serio, y en ese caso NO lo hace sin
  confirmarlo primero, por lo invasivo de tocar la clave de un rol): estado
  de sus devengos, trabajos pendientes y realizados.
- **Jefe de Campo** (138): recursos que se ocupan o faltan; órdenes de
  trabajo con sus cuadrillas; estado de todas las órdenes de aplicación
  (considerando haciendas y estado del equipamiento).
- **Administrador de plataforma** (139): no es operativo ni financiero, es
  técnico — usuarios, bitácoras, accesos directos a configuración del
  sistema. Mapea casi directo a permisos que ya existen:
  `seguridad.usuario.ver`, `seguridad.bitacora.ver`,
  `seguridad.configuracion.ver`, `seguridad.organizacion.ver`,
  `seguridad.dispositivo.ver`, `distribucion.version.autorizar`.

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

## 8. Orden de la cola

135 (mecanismo + Dueño) → 136 (Encargado Operaciones) → 137 (Piloto/Ayudante)
→ 138 (Jefe de Campo) → 139 (Administrador) → 140 (Ver como, crítica) → 141
(Motor de notificaciones, crítica). Ver cada prompt individual
(`prompts/135-*.md` a `prompts/141-*.md`) para el detalle técnico de cada
una.
