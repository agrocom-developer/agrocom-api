<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/roles-permisos-panel etapas=4 -->

# Tarea 64 — roles y permisos visibles y administrables desde el panel

## Por qué esta tarea

Hoy `sec_role`, `sec_permission` y `sec_role_permission` solo se pueblan por
`database/seeders/Catalogo/SeguridadSeeder.php`. Cambiar qué puede hacer
`jefe_campo` es editar PHP y redesplegar, y nadie puede ver desde el panel
qué permite cada rol. El usuario pidió el 4/9/2026 "la vista del frontend
sobre los permisos": qué ve y qué puede hacer cada rol, y en particular un
usuario multirol según el rol con el que entró. TE-12 del plan de sprints
("auditoría de permisos por rol contra la matriz de la especificación §3")
necesita exactamente esta pantalla para poder hacerse.

Es crítica: administra el modelo de seguridad. Se implementa igual y el PR
queda anotado para revisión humana.

## Lo que ya existe

- 5 roles y 89 permisos en `SeguridadSeeder` (familias `seguridad.*`,
  `operaciones.*`, `comercial.*`, `personal.*`, `finanzas.*`,
  `mantenimiento.*`, `inventario.*`, `distribucion.*`), asignados por listas
  constantes. `sec_role.state` existe pero no se administra.
- `ObtenerMenuPorRolActivo` calcula el menú de un rol; `PermisoVista` y la
  directiva `@puede` deciden botones. `SecUser::tienePermisoEnRol()` es el
  único evaluador con sesión (tras la tarea 62).
- ABM de usuarios en `UsuariosController` con `<select multiple>` de roles:
  es el arquetipo de formulario a seguir. La tarea 62 dejó que otorgar un
  rol con `seguridad.usuario.asignar_rol_dueno` exige tener ese permiso en el
  rol activo — respetalo acá también.
- La bitácora (ADR 0007) registra sola las mutaciones de modelos con el
  trait; `SecRole` y la tabla pivote tienen que quedar cubiertas.
- `tests/Feature/Seguridad/` tiene 21 archivos; `SecMenuSeederTest`,
  `PermisosPorRolActivoTest`, `AsignarRolesUsuarioTest` son los que más se
  parecen a lo que hay que escribir.

## Qué hacer

Cargá las skills `seguridad-roles`, `dominio-backend`, `panel-design-ui` y
`verificacion`. Leé el ADR 0004 completo.

1. **Permisos nuevos** en `SeguridadSeeder`: `seguridad.rol.ver`,
   `seguridad.rol.crear`, `seguridad.rol.editar`, `seguridad.rol.bloquear`,
   `seguridad.rol.asignar_permisos`. Solo `dueno` los tiene todos;
   `encargado_operaciones` solo `seguridad.rol.ver`.
2. **`GET /panel/roles`** (arquetipo Listado): cada rol con nombre legible
   (`lang/es/seguridad.php` → `rol.meta.{slug}`; si no hay clave, el slug),
   descripción, estado, cantidad de permisos y cantidad de usuarios que lo
   tienen. Ítem de menú bajo Seguridad.
3. **`GET /panel/roles/{rol}`** — la vista de "qué ve este rol": (a) la
   **matriz de permisos** agrupada por familia/módulo, con cada permiso
   marcado sí/no; (b) la **vista previa del menú** que ese rol obtiene
   (reusá `ObtenerMenuPorRolActivo` con ese rol: es el mismo cálculo que
   hace el sidebar, así la pantalla nunca miente); (c) la lista de usuarios
   que lo tienen. Con `seguridad.rol.asignar_permisos`, la matriz es
   editable (checkbox por permiso, un solo `PUT` con el set completo, como
   hace `AsignarRolesUsuario` con los roles). Caso de uso
   `AsignarPermisosRol` con la misma guarda que los roles: no se puede
   otorgar `seguridad.usuario.asignar_rol_dueno` ni los `seguridad.rol.*` a
   un rol si el actor no tiene ese permiso en su rol activo; y no se puede
   dejar sin `seguridad.rol.asignar_permisos` al último rol activo que lo
   tiene (si no, nadie vuelve a poder editar la matriz).
4. **Alta/edición/bloqueo de roles** (`crear`, `editar` nombre legible y
   descripción, `bloquear`/desbloquear `state`). El `name` técnico (slug)
   se genera al crear y no se edita después: es la clave que usan seeders y
   tests. Bloquear un rol: quienes lo tienen dejan de poder elegirlo como
   activo (ya lo hace `idsDeRolesActivos()` por `state`), y si era el rol
   activo de una sesión viva, el middleware `rol.activo` la manda a elegir
   otro (verificá que ya pase; si no, es parte de esta tarea). Baja lógica
   de un rol solo si ningún usuario vivo lo tiene.
5. **Lo que ve un usuario multirol.** En la ficha de usuario
   (`/panel/usuarios/{u}/editar` o una vista `show` nueva), por cada rol
   asignado un enlace a la vista del rol, y una nota fija: "los permisos
   efectivos son los del rol activo de la sesión, nunca la unión". En el
   selector de rol (`/panel/seleccionar-rol`), cada tarjeta muestra los
   módulos del menú que ese rol habilita (mismo cálculo del punto 3b), para
   que el usuario elija con información.
6. **Semilla y catálogo.** `SeguridadSeeder` sigue siendo la fuente de la
   asignación inicial y sigue siendo idempotente, pero **no pisa** en cada
   corrida lo que un dueño cambió desde el panel: solo agrega permisos y
   roles que no existen, nunca borra filas de `sec_role_permission` que
   alguien quitó a mano. Documentá ese contrato en el docblock del seeder y
   cubrilo con test.

## Qué NO hacer

- No crees una pantalla para inventar permisos nuevos: el catálogo de
  `sec_permission` nace del código (cada `abort_unless` lo referencia por
  string) y solo cambia por seeder. La pantalla los muestra; no los crea.
- No hagas un ABM del menú (`sec_menu`) acá: es otra tarea si el usuario la
  pide.
- No cambies `tienePermisoEnRol()` ni el middleware `rol.activo`.
- No caches la matriz en sesión: un cambio en `sec_role_permission` tiene
  que verse en el siguiente request sin re-login (el test lo exige).

## Cómo repartir las etapas

- **Etapa 1**: permisos nuevos, `ListarRoles`, `VerRol` (matriz + preview de
  menú + usuarios), pantallas de listado y detalle en solo lectura, ítem de
  menú, tests de 403.
- **Etapa 2**: `AsignarPermisosRol` con sus guardas y tests (incluido "un
  cambio se ve en el siguiente request").
- **Etapa 3**: alta/edición/bloqueo/baja de roles, contrato del seeder que
  no pisa cambios manuales, tests.
- **Etapa 4**: ficha de usuario multirol y tarjetas del selector de rol con
  los módulos habilitados, specs visuales (`roles.spec.ts`: index y show,
  claro/oscuro; actualizar `seleccionar-rol.spec.ts` y `usuarios.spec.ts`),
  `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test: la vista previa del menú de `piloto` en `/panel/roles/{piloto}` es
  idéntica al menú que `ObtenerMenuPorRolActivo` calcula para una sesión con
  `piloto` activo.
- Test: quitar `finanzas.devengo.ver` a `auxiliar` desde `PUT
  /panel/roles/{auxiliar}/permisos` hace que un auxiliar reciba 403 en
  `/panel/devengos` en el request siguiente, sin re-login.
- Test: un actor sin `seguridad.usuario.asignar_rol_dueno` en su rol activo
  no puede otorgar ese permiso a ningún rol (403, matriz sin cambios).
- Test: no se puede dejar a ningún rol activo con
  `seguridad.rol.asignar_permisos` (422 con mensaje).
- Test: `php artisan db:seed --class=...SeguridadSeeder` dos veces, con una
  quita manual en el medio, no restaura lo quitado.
- Bitácora: alta de rol y cambio de matriz quedan en `plt_bitacoras` con
  antes/después (test).

## Puede tocar

`app/Dominios/Seguridad/**`, `database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, migraciones nuevas si hacen
falta (descripción de rol), `lang/es/**`, `resources/**`, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/64.estado`, `runs/64.md`, y al `OK` `runs/64.pr.md`. Commits agrupados
por función, en español, imperativo, sin `Co-Authored-By`.
