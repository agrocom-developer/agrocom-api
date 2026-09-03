<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/gestion-usuarios etapas=3 -->

# Tarea 39 — HU-45: gestión de usuarios desde el panel

## Por qué esta tarea

`plan_sprints.md` Sprint 7 (§179): "Como encargado, quiero dar de alta
usuarios y asignarles roles desde el panel, para no depender de un seeder —
cierra la parte de HU-01 que quedó sin hacer." Es la única fila de Sprint 7
que quedó deliberadamente sin escribir en la vuelta anterior (ver
`cola_tareas.md`, "Sprint 7 — de destrabado a en marcha"): con clientes,
contratos, campos, drones, personas/bases y órdenes ya integrados (PR #77,
#78/#79, #80/#81, #82, #83/#84, #85), Sprint 7 se cierra con esta.

**La lógica de dominio pesada ya existe y está testeada.**
`AsignarRolesUsuario::ejecutar()`
(`app/Dominios/Seguridad/Aplicacion/AsignarRolesUsuario.php`) cubre alta Y
edición con la misma guarda (a propósito: si solo protegiera "crear", un
encargado lograría asignarse el rol `dueno` editando después de crear la
cuenta), sincroniza roles como set completo (revoca lo que falte, asigna lo
nuevo) y traduce duplicados de `username`/`persona_id` a `UsuarioDuplicado`
en vez de un 500 crudo. Cubierto por
`tests/Feature/Seguridad/AsignarRolesUsuarioTest.php`. Esta tarea es la capa
HTTP encima de eso — **no reimplementes nada de esa lógica**, invocala.

`UsuariosController` hoy solo tiene `index()`, y su propio docblock lo dice
explícito: "placeholder mínimo... la pantalla real de gestión de usuarios
(CRUD) es alcance de una HU futura". Esa HU futura es esta. Los permisos ya
están sembrados (`seguridad.usuario.ver/crear/editar/bloquear/eliminar/
asignar_rol_dueno`, `SeguridadSeeder.php:36-41`) y el ítem de menú
"Usuarios" ya apunta a `panel.usuarios.index` — no hace falta tocar ninguno
de los dos seeders.

**"Cambiar roles no invalida la sesión activa" ya es un comportamiento del
sistema, no algo que construir.** El middleware `ResolverRolActivo`
revalida el rol activo contra la base en cada request (no contra algo
cacheado en la sesión): si los roles de un usuario cambian mientras tiene
una sesión abierta, el próximo request lo recalcula solo (auto-elige si le
queda uno, pide selección si le quedan varios, nunca revienta con un
`id_role` fantasma). El test de esta tarea **confirma** ese comportamiento,
no agrega un mecanismo nuevo.

**El login ya rechaza `state=false`** (`IniciarSesionPanel.php:64`,
`->where('state', true)`), así que el permiso `seguridad.usuario.bloquear`
—ya sembrado, sin ninguna acción que lo use todavía— solo necesita un
toggle de esa columna para que "bloquear" bloquee de verdad. No hay que
tocar el login.

## Qué hacer

Cargá las skills `dominio-backend`, `seguridad-roles`, `panel-design-ui` y
`verificacion`. Mirá `PersonasController`/`OrdenesController` (Sprint 7)
como referencia del patrón HTTP a seguir.

### 1. Caso de uso de baja — `app/Dominios/Seguridad/Aplicacion/EliminarUsuario.php`

Soft delete de `SecUser` (invariante 8), permiso `seguridad.usuario.eliminar`
verificado dentro — mismo criterio que `EliminarPersona`/`EliminarOrden`.

### 2. Toggle de bloqueo — nuevo caso de uso o método directo

El permiso `seguridad.usuario.bloquear` ya sembrado no tiene ninguna acción
que lo consuma. Agregá la que invierte `sec_user.state` (bitácora automática
vía `RegistraBitacora` al hacer `save()` con `updated_by` seteado — no hace
falta nada manual ahí).

### 3. HTTP — `UsuariosController`

Extendé con `create`, `store`, `edit`, `update`, `destroy` y el toggle de
bloqueo. Migrá también el `index()` actual (chequeo manual de permiso) al
patrón `AutorizacionPanelWeb` que ya usan `PersonasController`/
`OrdenesController`, para consistencia con el resto del panel.

- `store`/`update` arman el `SecUser $actor` con `$request->user('interno')`
  y llaman `AsignarRolesUsuario::ejecutar()`.
- Capturá `UsuarioDuplicado`/`PermisoDenegado` con try/catch →
  `redirect()->back()->withErrors(['estado' => $excepcion->getMessage()])`
  (mismo patrón que `OrdenesController::update()` con
  `TransicionOrdenNoPermitida`/`OrdenVigenteDuplicadaEnLote`).

### 4. Requests — `CrearUsuarioRequest`/`ActualizarUsuarioRequest`

`name` requerido; `username` requerido (validalo también acá para feedback
temprano — `Rule::unique('sec_user', 'username')->whereNull('deleted_at')`,
ignorando el propio id en edición — la excepción de dominio sigue siendo la
segunda línea de defensa); `password` requerido en creación (min 8),
`nullable` en edición (vacío = conserva el hash vigente, ya lo soporta
`AsignarRolesUsuario`); `persona_id` `nullable` + `exists:per_personas,id`
entre vivas; `roles` array de ids, cada uno `exists:sec_role,id` entre
vivos. **No expongas `type` en el formulario** — fijalo a
`TipoUsuario::Interno` en el controller. `type: cliente` es del portal
(HU-41, Sprint 12), otra HU con otro flujo de alta.

### 5. Vistas — `create.blade.php`/`edit.blade.php`

Sobre el arquetipo Formulario. Un selector de roles (checkboxes o `<select
multiple>` nativo — no hay átomo de eso en el catálogo, no inventes uno,
mismo criterio que contratos/campos) y un `<select>` de personas: vivas de
`per_personas` sin `sec_user.persona_id` ya asignado, más la propia persona
en edición (si no la excluís, el propio registro se autoexcluye de su
lista al re-guardar). Si el actor autenticado no tiene
`seguridad.usuario.asignar_rol_dueno`, ocultá la opción "dueño" del
selector de roles — la guarda real ya está en el backend, esto es solo para
no mostrar una opción que el submit va a rechazar.

### 6. Rutas — `routes/web.php`

`panel.usuarios.create/store/edit/update/destroy` + una para el toggle de
bloqueo (`panel.usuarios.bloqueo` o el nombre que prefieras, documentado).
Mismo grupo `auth:interno` → `rol.activo` que el resto.

## Qué NO hacer

- No reimplementes la sincronización de roles ni la guarda de rol dueño —
  ya existen en `AsignarRolesUsuario`, invocalas.
- No toques `TipoUsuario::Cliente` ni ningún flujo de portal.
- No agregues columnas nuevas a `sec_user`/`sec_user_role` — el modelo de
  datos está completo.
- No implementes recuperación de contraseña ni invitación por correo — no
  hay infraestructura de email en el proyecto. La contraseña inicial la
  asigna el encargado directo en el formulario de alta.
- No toques `SeguridadSeeder.php` ni `SecMenuSeeder.php` — permiso y menú
  de usuarios ya están sembrados completos.
- No toques `ResolverRolActivo` ni `ElegirRolActivo` — el comportamiento que
  necesitás ya existe, tu test lo confirma, no lo modifica.

## Cómo repartir las etapas

- **Etapa 1**: `EliminarUsuario`, toggle de bloqueo, `UsuariosController`
  completo, rutas.
- **Etapa 2**: los dos Requests + `create.blade.php`/`edit.blade.php`.
- **Etapa 3**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con la etapa de Playwright.
- Test Feature (`tests/Feature/Seguridad/GestionUsuariosPanelTest.php`)
  cubriendo:
  - Alta de un usuario con roles → persiste; login real con el
    username/password dados funciona.
  - Edición reasigna roles (set completo: uno se agrega, otro se revoca) y
    actualiza `name`/`persona_id`.
  - Un actor sin `asignar_rol_dueno` que intenta crear o editar asignando
    el rol `dueno` → rechazado con error legible (422/redirect con
    `withErrors`), nunca 500.
  - Baja: soft delete, no aparece en `index`, 404 al reintentar.
  - `username` duplicado entre cuentas vivas → error de validación legible
    (no `QueryException` cruda).
  - Bloqueo: togglear `state` no borra al usuario (sigue en `index`, sin
    `deleted_at`); con `state=false` el login rechaza esa cuenta
    (confirmalo contra `IniciarSesionPanel`, ya filtra por `state`).
  - **Cambiar roles no invalida la sesión activa**: usuario A logueado con
    rol activo elegido; el encargado le reasigna un conjunto de roles
    distinto vía `update`; el siguiente request de A sigue funcionando
    (200, o lo manda al selector de rol si se quedó sin el que tenía
    activo — nunca 500) gracias a `ResolverRolActivo`.
  - 403 sin el permiso correspondiente en cada acción; permiso en un rol
    no-activo no alcanza (patrón estándar del sprint).
- Spec visual nuevo (`tests/Visual/usuarios.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Seguridad/**`, `routes/web.php`, `tests/**`.

Fuera de alcance: `SeguridadSeeder.php`, `SecMenuSeeder.php` (ya sembrados),
cualquier otro módulo, el portal del cliente.
