<!-- ciclo: critica=no turno-noche=1 rama=feature/panel-ordenes-mantenimiento etapas=4 descongela=tests modelo=opus -->

# Tarea 116 — panel homogéneo: Órdenes de mantenimiento y Planes

Parte del plan `docs/gestion/plan_homogeneizacion_panel.md`. **Leelo entero
antes de tocar nada**: §3 son las reglas del dueño, §4 la receta común que esta
tarea sigue paso a paso, §1.1 lo que queda afuera. Este prompt solo agrega lo
propio de este módulo.

Cargá los skills `verificacion`, `panel-design-ui` y `redaccion-neutra` y `dominio-backend`.

## Prerrequisito

La tarea 111 tiene que estar en `develop`: deben existir
`tests/Unit/PanelHomogeneoTest.php` y
`docs/diseno/panel_homogeneo_pendientes.txt`. Si no están, escribí
`BLOQUEADA` en `runs/116.estado`, explicalo en `runs/116.md` y terminá.

## Pantallas de esta tarea

Módulo `Mantenimiento` — vistas en
`app/Dominios/Mantenimiento/Infraestructura/Http/Views/pages/`:

- `ordenes/` — `index`, `create`, `edit` (hoy separados, con `_repuesto-campos`);
  máquina: `TransicionesOrdenMantenimiento`
- `planes/` — `index`, `_formulario`, `create`, `edit`

Referencia a imitar: `comercial::pages.contratos.*` + `PasosDeContrato` (pasos, `_cambio-estado`, acciones de fila con el tono del estado) y `personal::pages.cuadrillas._formulario` (tabla de detalle con paginación).

## Qué hacer

Aplicá la receta del plan §4 a cada pantalla de arriba. Lo particular de acá:

- **Orden de mantenimiento:** unificá `create` y `edit` en un `_formulario`
  compartido. Pasos `step-arrow` bajo la cabecera en edición, con
  `PasosDeEstado::armar()` sobre `TransicionesOrdenMantenimiento`; presentador
  `PasosDeOrdenMantenimiento` con `TONO_POR_ESTADO`, que comparten el badge del
  listado, los pasos y los modales. Textos `mantenimiento.orden.estado.*` y
  `estado_ayuda.*`. Forms y modales en `_cambio-estado`, fuera del formulario,
  apuntando a la ruta de cambio de estado existente. Test unitario del
  presentador.
- **Repuestos de la orden:** es una tabla de detalle → `index-table` dentro de
  su `form-section`, con `pagination` si puede pasar de una página; la fila
  repetible lleva la clase `ag-form-section__body` (guía §6.3 regla 2).
- **Listado de órdenes:** acciones de cambio de estado con `<tono>-outline` del
  estado de llegada + `confirm-modal` con la ficha «actual → destino»
  (mirá `contratos/_estado-transicion`; si conviene reutilizarla entre módulos,
  subila al catálogo, no la copies).
- **Aviso de estado (plan §3.5):** mientras la orden no esté en su estado
  operativo (en curso / en ejecución, el que defina la máquina), el aside es
  una sola `empty-state` que lo explica.
- **Planes:** catálogo sin máquina; `confirm()` nativo fuera.
- KPI del listado de órdenes: candidata (abiertas, en curso, cerradas en el
  período, costo — hasta cuatro, con el mismo filtro).

### Resumen relacionado (solo en edición)

- **Orden de mantenimiento:** el equipo intervenido (dron/batería/generador/
  vehículo), el plan que la originó, repuestos consumidos (Inventario, por
  contrato), gasto asociado (Finanzas, por contrato) si la FK existe.
- **Plan:** órdenes generadas desde el plan, equipos a los que aplica.

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

- Etapa 1: presentador de pasos + test + textos de estado.
- Etapa 2: formulario unificado de la orden (pasos, modales, tabla de repuestos, aside).
- Etapa 3: listado de órdenes (acciones por estado, KPI si corresponde).
- Etapa 4: Planes, pendientes, `./bin/verify`, capturas.

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
- No tocar `MaquinaEstadosOrdenMantenimiento` ni su tabla de transiciones.
- `descongela=tests` es para el test NUEVO del presentador: no edites tests existentes.

## Criterio de aceptación

1. `./bin/verify` = 0.
2. Ninguna pantalla de esta tarea figura en
   `docs/diseno/panel_homogeneo_pendientes.txt`:
   `! grep -E '^Mantenimiento/(ordenes|planes)/' docs/diseno/panel_homogeneo_pendientes.txt`
3. `grep -rn "confirm(" ` sobre las vistas y el JS de página tocados no devuelve
   nada.
4. Prueba en navegador (plan §4.7) con Playwright headless del repo
   (`NODE_PATH=$PWD/node_modules node <script>.js`, script en `runs/`, login
   `miguelo` / `0000` en `http://localhost:8000`): cada pantalla en tema claro y
   oscuro, capturas a la ruta ABSOLUTA `$PWD/runs/116-capturas/`. El script
   termina con exit 0 solo si: `.ag-panel__content` no desborda en horizontal,
   cada modal de confirmación abre visible (probá también una acción que caiga
   en el menú «⋮»), y guardar un alta vuelve a la ficha de edición con su
   `alert-strip` de éxito. Mirá las capturas antes de declarar `OK` — el
   reporte no prueba nada, la captura sí.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/116-verify.log 2>&1 & echo $! > runs/116-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/116-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/116-verify.log`.

## Cierre de cada etapa

`runs/116.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/116.md`
con qué se hizo, **qué falta** y qué decisiones quedaron para el dueño. Al
llegar a `OK`: `runs/116.pr.md` (título en la primera línea, cuerpo debajo),
la fila 116 de la tabla §7 del plan marcada, y si cambió el catálogo,
`sistema_diseno_panel.md` §3 al día.

Commits agrupados por función (un commit por objeto: listado + formulario +
copy + CSS de ese objeto; aparte los contratos de lectura), en español,
imperativo, explicando el porqué. Sin `Co-Authored-By`.
