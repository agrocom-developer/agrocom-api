<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/permisos-rol-activo etapas=3 -->

# Tarea 62 — cerrar las tres fugas del modelo de permisos por rol activo

## Por qué esta tarea

Una auditoría de solo lectura del 4/9/2026 sobre `develop` confirmó que el
modelo `sec_*` funciona como dice el ADR 0004 (rol activo en sesión,
permisos efectivos solo del rol activo, menú filtrado, 403 por acción) salvo
en tres lugares concretos. Los tres son fail-open: dejan ver o hacer algo
que el rol activo no autoriza. Esta tarea los cierra, con un test por fuga
que falle antes y pase después.

Es crítica: toca el modelo de seguridad. El PR se abre igual (no en
borrador) y queda anotado para revisión humana posterior.

## Las tres fugas, con evidencia

1. **La asignación de roles evalúa por unión, no por rol activo.**
   `app/Dominios/Seguridad/Aplicacion/AsignarRolesUsuario.php` usa
   `$actor->tienePermiso($codigo)` (unión de todos los roles del actor) en la
   guarda general y en `verificarGuardaRolDueno()`. Se invoca desde
   `UsuariosController::store()` y `::update()`, donde SÍ hay sesión y rol
   activo. Consecuencia: un usuario con `dueno` + `encargado_operaciones`,
   operando como `encargado_operaciones`, no ve la opción "dueño" en el
   `<select>` (filtro cosmético en `UsuariosController`), pero si postea el id
   del rol `dueno` a mano, la guarda lo acepta. Viola la invariante 10 de
   `CLAUDE.md` y el punto 5 de la extensión del ADR 0004.
2. **Dashboard y organización sin permiso.**
   `DashboardController::index()` y `OrganizacionController` no tienen ningún
   `abort_unless`. Un `auxiliar` (un solo permiso en todo el catálogo:
   `finanzas.devengo.ver`) ve el tablero completo con detalle de clientes,
   mapa operativo y stock, y la ficha de la compañía. El engranaje del riel
   (`panel-layout.blade.php`) enlaza a organización sin condición.
3. **Ítems de menú sin ruta ni permiso que abren grupos para todos.**
   `database/seeders/Catalogo/SecMenuSeeder.php` siembra cuatro ítems sin
   `codigoPermiso`: `operacion.programacion` (→ dashboard),
   `operacion.evidencias` (sin ruta), `comercial.reportes_cliente` (sin
   ruta) y `seguridad.organizacion`. Por la regla de grupos de
   `ObtenerMenuPorRolActivo` (un grupo es visible si algún hijo lo es), eso
   hace que **Operación**, **Comercial** y **Seguridad** aparezcan en el
   menú de cualquier rol, con hijos que no llevan a nada.

## Qué hacer

Cargá las skills `seguridad-roles`, `dominio-backend` y `verificacion`.
Leé `docs/decisiones/0004-modelo-seguridad-sec-multirol.md` entero antes de
tocar nada.

1. **Fuga 1.** `AsignarRolesUsuario` recibe el rol activo del actor (el id que
   ya tiene el controlador en `sec_rol_activo_id`) y evalúa con
   `tienePermisoEnRol()`, nunca con `tienePermiso()`. Si el caso de uso
   también se usa sin sesión (seeders, comandos), hacé explícito ese camino
   con un parámetro o un método aparte — no con un default que vuelva a la
   unión. Reemplazá los dos hardcodeos del nombre de rol `'dueno'`
   (`AsignarRolesUsuario::ROL_DUENO` y el `reject()` del `<select>` en
   `UsuariosController`) por el permiso ya existente
   `seguridad.usuario.asignar_rol_dueno`: la regla es "solo quien tiene ese
   permiso en su rol activo puede otorgar o quitar un rol que a su vez tenga
   ese permiso", no "el rol que se llama dueño".
2. **Fuga 2.** Permiso nuevo `seguridad.dashboard.ver` y
   `seguridad.organizacion.ver` en `SeguridadSeeder`, con `abort_unless` en
   ambos controladores, como en el resto del panel. Asignalos a `dueno`,
   `encargado_operaciones` y `jefe_campo`; **no** a `piloto` ni `auxiliar`.
   Como el dashboard es la pantalla de aterrizaje tras elegir rol, un rol sin
   ese permiso tiene que aterrizar en otro lado y nunca en un 403: resolvé el
   destino post-login como "el primer ítem visible del menú del rol activo"
   (ya sabés calcularlo en `ObtenerMenuPorRolActivo`) y aplicalo tanto al
   login como al cambio de rol activo. El engranaje del riel se muestra solo
   con `@puede('seguridad.organizacion.ver')`.
3. **Fuga 3.** En `SecMenuSeeder`: `operacion.programacion` y
   `seguridad.organizacion` llevan sus permisos nuevos; `operacion.evidencias`
   y `comercial.reportes_cliente` o apuntan a una ruta real con su permiso, o
   se retiran. Hoy sí existen pantallas candidatas: la galería de evidencias
   (HU-42, tarea 56) y el listado de reportes técnicos (HU-43, tarea 57) —
   revisá si ya tienen su propio ítem; si lo tienen, los dos ítems sin ruta
   se retiran (el seeder ya sabe migrar filas viejas: mirá cómo hizo la 59
   con `operacion.mezclas`). Después de esto, ningún ítem de `sec_menu`
   queda sin `permission_id`, y el test de `SecMenuSeederTest` lo afirma.

## Qué NO hacer

- No cambies la semántica de `tienePermiso()` (unión): la API de campo y los
  contextos sin sesión la necesitan. La corrección es que el panel no la use.
- No inventes un middleware genérico de permisos en esta tarea (es deuda
  conocida, `routes/web.php` lo dice): seguí el patrón `abort_unless` del
  resto de los controladores.
- No toques el contenido del dashboard (sigue siendo mock; es la tarea 67).
- No agregues roles nuevos ni cambies los permisos de los cinco roles más
  allá de los dos permisos nuevos.

## Cómo repartir las etapas

- **Etapa 1**: fuga 1 — caso de uso por rol activo, permiso en vez de nombre
  de rol, tests (el de "posteo el id de dueño operando como encargado → 403"
  es obligatorio y tiene que fallar contra `develop`).
- **Etapa 2**: fuga 2 — permisos nuevos, `abort_unless`, aterrizaje post-login
  por rol, engranaje condicionado, tests por rol (auxiliar no ve dashboard ni
  organización y no cae en 403 al entrar).
- **Etapa 3**: fuga 3 — seeder de menú, test "ningún ítem sin permiso", test
  de que `piloto` no ve los grupos Operación/Comercial/Seguridad, capturas
  de Playwright regeneradas solo donde el sidebar cambió, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature: usuario con `dueno` + `encargado_operaciones`, activo como
  `encargado_operaciones`, hace `PUT /panel/usuarios/{u}` con el id de `dueno`
  en `roles[]` → 403 y `sec_user_role` sin cambios.
- Test Feature: `auxiliar` como rol activo → `GET /panel/dashboard` 403,
  `GET /panel/organizacion` 403, y el flujo login → seleccionar rol termina
  en una pantalla 200 que sí puede ver.
- Test de seeder: toda fila de `sec_menu` con ruta tiene `permission_id`, y
  el menú de `piloto` no contiene los módulos Operación, Comercial ni
  Seguridad.
- `tests/Feature/Seguridad/PermisosPorRolActivoTest.php` y
  `ObtenerMenuPorRolActivoTest.php` siguen en verde.

## Puede tocar

`app/Dominios/Seguridad/**`, `database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/**`, las vistas del
riel/topbar bajo `resources/views/components/**` solo para el engranaje,
`tests/**`, capturas de `tests/Visual/**` que cambien por el sidebar.

## Cierre obligatorio de cada etapa

`runs/62.estado` (`PARCIAL` / `OK` / `BLOQUEADA`), `runs/62.md` con qué se
hizo y qué falta, y al `OK` `runs/62.pr.md` con título y cuerpo del PR.
Commits agrupados por función, en español, imperativo, sin `Co-Authored-By`.
