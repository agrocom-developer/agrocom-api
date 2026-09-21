<!-- ciclo: critica=no turno-noche=1 rama=feature/panel-personal etapas=3 -->

# Tarea 112 — panel homogéneo: Bases y Personas

Parte del plan `docs/gestion/plan_homogeneizacion_panel.md`. **Leelo entero
antes de tocar nada**: §3 son las reglas del dueño, §4 la receta común que esta
tarea sigue paso a paso, §1.1 lo que queda afuera. Este prompt solo agrega lo
propio de este módulo.

Cargá los skills `verificacion`, `panel-design-ui` y `redaccion-neutra` y `dominio-backend`.

## Prerrequisito

La tarea 111 tiene que estar en `develop`: deben existir
`tests/Unit/PanelHomogeneoTest.php` y
`docs/diseno/panel_homogeneo_pendientes.txt`. Si no están, escribí
`BLOQUEADA` en `runs/112.estado`, explicalo en `runs/112.md` y terminá.

## Pantallas de esta tarea

Módulo `Personal` — vistas en
`app/Dominios/Personal/Infraestructura/Http/Views/pages/`:

- `bases/` — `index`, `_formulario`, `create`, `edit`
- `personas/` — `index`, `_formulario`, `create`, `edit`
  (`personas/desempeno` es un detalle: NO se toca)
- `cuadrillas/` ya está conforme: es tu referencia dentro del módulo, no la edites.

Referencia a imitar: `comercial::pages.clientes.*` (catálogo con contactos y resumen) y `personal::pages.cuadrillas.*`.

## Qué hacer

Aplicá la receta del plan §4 a cada pantalla de arriba. Lo particular de acá:

- Los dos listados tienen `table-search` pero tabla, acciones y confirmación
  propias (`confirm()` nativo en eliminar). Pasan a `index-table` +
  `row-actions` + `confirm-modal`.
- Personas: si el listado filtra por rol/cargo o base, esos filtros van dentro
  de `filter-panel`. El badge de cargo o tipo usa el eje gris↔verde, nunca
  ámbar (skill `panel-design-ui`, regla 2).
- Personas tiene un control crudo en el formulario: revisalo (si es `hidden`,
  queda; si no, pasa al átomo).
- Foto o documento de la persona, si el formulario lo tiene: `molecules/file-field`.

### Resumen relacionado (solo en edición)

- **Base:** personas asignadas a la base y cuadrillas que salen de ella (mismo
  módulo); equipos con esa base (drones, vehículos) solo si la FK existe, por
  el contrato del módulo dueño.
- **Persona:** cuadrillas que integra (mismo módulo); usuario vinculado
  (Seguridad); sesiones/trabajos en los que participó (Operaciones); anticipos
  o devengos (Finanzas). Elegí las que tengan FK real, hasta cuatro.

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

- Etapa 1: Bases completo (listado, formulario, resumen, capturas).
- Etapa 2: Personas — listado y formulario.
- Etapa 3: Personas — resumen relacionado con sus contratos de lectura, lista
  de pendientes, `./bin/verify`, capturas finales.

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

## Criterio de aceptación

1. `./bin/verify` = 0.
2. Ninguna pantalla de esta tarea figura en
   `docs/diseno/panel_homogeneo_pendientes.txt`:
   `! grep -E '^Personal/(bases|personas)/' docs/diseno/panel_homogeneo_pendientes.txt`
3. `grep -rn "confirm(" ` sobre las vistas y el JS de página tocados no devuelve
   nada.
4. Prueba en navegador (plan §4.7) con Playwright headless del repo
   (`NODE_PATH=$PWD/node_modules node <script>.js`, script en `runs/`, login
   `miguelo` / `0000` en `http://localhost:8000`): cada pantalla en tema claro y
   oscuro, capturas a la ruta ABSOLUTA `$PWD/runs/112-capturas/`. El script
   termina con exit 0 solo si: `.ag-panel__content` no desborda en horizontal,
   cada modal de confirmación abre visible (probá también una acción que caiga
   en el menú «⋮»), y guardar un alta vuelve a la ficha de edición con su
   `alert-strip` de éxito. Mirá las capturas antes de declarar `OK` — el
   reporte no prueba nada, la captura sí.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/112-verify.log 2>&1 & echo $! > runs/112-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/112-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/112-verify.log`.

## Cierre de cada etapa

`runs/112.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/112.md`
con qué se hizo, **qué falta** y qué decisiones quedaron para el dueño. Al
llegar a `OK`: `runs/112.pr.md` (título en la primera línea, cuerpo debajo),
la fila 112 de la tabla §7 del plan marcada, y si cambió el catálogo,
`sistema_diseno_panel.md` §3 al día.

Commits agrupados por función (un commit por objeto: listado + formulario +
copy + CSS de ese objeto; aparte los contratos de lectura), en español,
imperativo, explicando el porqué. Sin `Co-Authored-By`.
