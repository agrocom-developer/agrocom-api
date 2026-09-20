<!-- ciclo: critica=no turno-noche=1 rama=feature/panel-usuarios-dispositivos etapas=3 -->

# Tarea 121 — panel homogéneo: Usuarios, Dispositivos y Versiones de APK

Parte del plan `docs/gestion/plan_homogeneizacion_panel.md`. **Leelo entero
antes de tocar nada**: §3 son las reglas del dueño, §4 la receta común que esta
tarea sigue paso a paso, §1.1 lo que queda afuera. Este prompt solo agrega lo
propio de este módulo.

Cargá los skills `verificacion`, `panel-design-ui` y `redaccion-neutra`, `seguridad-roles` y `dominio-backend`.

## Prerrequisito

La tarea 111 tiene que estar en `develop`: deben existir
`tests/Unit/PanelHomogeneoTest.php` y
`docs/diseno/panel_homogeneo_pendientes.txt`. Si no están, escribí
`BLOQUEADA` en `runs/121.estado`, explicalo en `runs/121.md` y terminá.

## Pantallas de esta tarea

Módulo `Seguridad` — vistas en
`app/Dominios/Seguridad/Infraestructura/Http/Views/pages/`:

- `Seguridad/usuarios/` — `index`, `_formulario`, `create`, `edit`
- `Seguridad/dispositivos/index`
- `Distribucion/versiones-apk/index` (otro módulo: máquina `TransicionesVersionApk`)
- **Excluidas por el dueño, no se tocan:** `roles/*`, `organizacion`,
  `bitacora`, `dashboard*`. Tampoco `perfil`, `configuracion`, `busqueda`,
  login ni selección de rol.

Referencia a imitar: `comercial::pages.clientes.*` para Usuarios; `comercial::pages.contratos.index` para las acciones por estado de Versiones de APK.

## Qué hacer

Aplicá la receta del plan §4 a cada pantalla de arriba. Lo particular de acá:

- **Usuarios:** ya tiene `filter-panel`, `table-search` y `row-actions`; falta
  `index-table` y sacar el `confirm()` nativo. Formulario a `form-layout`, con
  3 controles crudos a revisar. La asignación de roles usa
  `atoms/checkbox-group` o las tarjetas que ya tenga — conservá el eje
  gris↔verde de las etiquetas de permisos. Login por `username`, nunca correo.
  No se crea nada que permita duplicar una cuenta por rol (invariante 10).
- **Dispositivos:** listado de tokens por dispositivo → `index-table`, toolbar,
  revocar con `danger-outline` + `confirm-modal` si la acción existe hoy.
- **Versiones de APK:** badge y acciones de fila con el tono del estado de
  llegada (`TONO_POR_ESTADO` una vez), `confirm-modal`. Sin ficha de edición →
  sin pasos. La subida del APK, si está en esta pantalla, con
  `molecules/file-field`.
- El criterio de aceptación 2 de abajo cubre `Seguridad/`; sumale a mano
  `! grep '^Distribucion/versiones-apk/' docs/diseno/panel_homogeneo_pendientes.txt`.

### Resumen relacionado (solo en edición)

- **Usuario:** roles asignados (mismo módulo), persona vinculada (Personal, por
  contrato), dispositivos/tokens activos (mismo módulo), últimos movimientos en
  bitácora solo si ya hay una lectura disponible — no se toca la bitácora.

Reglas que no se negocian (plan §3.4, ADR 0003, memoria «entre módulos solo por
Contratos/»): lo que es de **otro** módulo llega por una interfaz de
`Contratos/` de ese módulo, con su DTO — nunca un modelo ajeno, un `DB::table`
ni un `join` a una tabla de otro prefijo. Si el contrato de lectura no existe,
crealo en el módulo dueño (interfaz + DTO + implementación en su
`Infraestructura/` + binding en su ServiceProvider): es solo lectura y cuenta
filas. Cada tarjeta se gatea por el permiso `.ver`/`.crear` del módulo de LO
QUE MUESTRA, contra el rol activo. Máximo cuatro tarjetas: las relaciones más
cercanas, derivadas de las FK reales — no inventes una relación que el esquema
no tiene. Corré `ArquitecturaModulosTest` apenas toques un `Contratos/`.

## Cómo repartir las etapas

- Etapa 1: Usuarios (listado + formulario + resumen).
- Etapa 2: Dispositivos y Versiones de APK.
- Etapa 3: pendientes, `./bin/verify`, capturas.

## Qué NO hacer

- No tocar casos de uso de escritura, máquinas de estado, migraciones, permisos
  ni seeders de permisos. Es capa de presentación. Si para cumplir la receta
  hiciera falta una regla de negocio nueva, escribí la pregunta y seguí con lo
  demás.
- No tocar las pantallas excluidas (plan §1.1) ni ningún `show.blade.php`.
  Si un listado ya tiene su acción «Ver», se conserva (en `info-outline`); no
  se crean detalles nuevos.
- No crear componentes del catálogo dentro de la página. Si falta una pieza o
  un prop, se agrega al catálogo en `resources/views/components/` con su CSS y
  su fila en `sistema_diseno_panel.md` §3 — y solo si ninguna existente sirve.
- No mostrar activo/inactivo como columna, filtro ni campo de formulario
  (guía §6.2 y §6.3.3).
- Texto blanco sobre todo relleno de estado, también `warning`. No «armonizar»
  tonos que ya están en `develop`.
- No borrar datos demo del Postgres del compose.
- No abrir el PR: lo abre el ciclo.
- No tocar autenticación, middleware de rol activo, `sec_*`, `SecMenuSeeder` ni `SeguridadSeeder`.

## Criterio de aceptación

1. `./bin/verify` = 0.
2. Ninguna pantalla de esta tarea figura en
   `docs/diseno/panel_homogeneo_pendientes.txt`:
   `! grep -E '^Seguridad/(usuarios|dispositivos)/' docs/diseno/panel_homogeneo_pendientes.txt`
3. `grep -rn "confirm(" ` sobre las vistas y el JS de página tocados no devuelve
   nada.
4. Prueba en navegador (plan §4.7) con Playwright headless del repo
   (`NODE_PATH=$PWD/node_modules node <script>.js`, script en `runs/`, login
   `miguelo` / `0000` en `http://localhost:8000`): cada pantalla en tema claro y
   oscuro, capturas a la ruta ABSOLUTA `$PWD/runs/121-capturas/`. El script
   termina con exit 0 solo si: `.ag-panel__content` no desborda en horizontal,
   cada modal de confirmación abre visible (probá también una acción que caiga
   en el menú «⋮»), y guardar un alta vuelve a la ficha de edición con su
   `alert-strip` de éxito. Mirá las capturas antes de declarar `OK` — el
   reporte no prueba nada, la captura sí.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/121-verify.log 2>&1 & echo $! > runs/121-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/121-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/121-verify.log`.

## Cierre de cada etapa

`runs/121.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/121.md`
con qué se hizo, **qué falta** y qué decisiones quedaron para el dueño. Al
llegar a `OK`: `runs/121.pr.md` (título en la primera línea, cuerpo debajo),
la fila 121 de la tabla §7 del plan marcada, y si cambió el catálogo,
`sistema_diseno_panel.md` §3 al día.

Commits agrupados por función (un commit por objeto: listado + formulario +
copy + CSS de ese objeto; aparte los contratos de lectura), en español,
imperativo, explicando el porqué. Sin `Co-Authored-By`.
