<!-- ciclo: critica=si turno-noche=1 rama=feature/panel-liquidacion-finanzas etapas=3 -->

# Tarea 119 — panel homogéneo: Planillas y Rendiciones

Parte del plan `docs/gestion/plan_homogeneizacion_panel.md`. **Leelo entero
antes de tocar nada**: §3 son las reglas del dueño, §4 la receta común que esta
tarea sigue paso a paso, §1.1 lo que queda afuera. Este prompt solo agrega lo
propio de este módulo.

Cargá los skills `verificacion`, `panel-design-ui` y `redaccion-neutra` y `dominio-backend`.

## Prerrequisito

La tarea 111 tiene que estar en `develop`: deben existir
`tests/Unit/PanelHomogeneoTest.php` y
`docs/diseno/panel_homogeneo_pendientes.txt`. Si no están, escribí
`BLOQUEADA` en `runs/119.estado`, explicalo en `runs/119.md` y terminá.

## Pantallas de esta tarea

Módulo `Finanzas` — vistas en
`app/Dominios/Finanzas/Infraestructura/Http/Views/pages/`:

- `planillas/index` — máquina `TransicionesPlanilla`
- `rendiciones/index` y `rendiciones/create` — máquina `TransicionesRendicion`
- NO se tocan: `planillas/show`, `rendiciones/show`, el recibo PDF, ni Devengos
  (`/panel/devengos` pinta `devengos/show`: es un detalle, no un listado).

Referencia a imitar: `comercial::pages.contratos.index` (badge + acciones de fila con el tono del estado de llegada + `confirm-modal` con ficha de transición).

## Qué hacer

Aplicá la receta del plan §4 a cada pantalla de arriba. Lo particular de acá:

- Planilla y Rendición tienen máquina pero NO ficha de edición: no llevan
  pasos. Lo que sí: `TONO_POR_ESTADO` definido una vez por objeto, badge del
  listado y acciones de fila de cambio de estado (`<tono>-outline` del estado
  de llegada) con `confirm-modal` y la ficha «actual → destino». Las acciones
  son EXACTAMENTE las que ya existen hoy, contra las rutas que ya existen.
- «Ver» (planilla, rendición) es la primera acción de
  fila, en `info-outline`.
- Rendiciones: `ag-filtros` viejo → `filter-panel`. Alta con `form-layout` sin
  aside; vuelve al listado con flash.
- Montos en mono, a la derecha. La vista solo formatea: cualquier total nuevo
  (KPI) se calcula en el caso de uso del listado con DECIMAL (`Brick\Math`,
  no bcmath — no está instalado), y tiene que cuadrar con la suma de las filas.
- KPI (plan §3.6): candidatas — planillas por estado y monto del período;
  rendiciones abiertas/aprobadas y monto. Hasta cuatro.

### Resumen relacionado (solo en edición)

- No aplica: no hay fichas de edición en esta tarea.

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

- Etapa 1: Planillas (listado).
- Etapa 2: Rendiciones (listado + alta).
- Etapa 3: KPI, pendientes, `./bin/verify`, capturas.

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
- **Es dinero** (CLAUDE.md, «qué no delegar»): ni una línea de `MaquinaEstadosPlanilla`, `MaquinaEstadosRendicion`, generación de devengos, listeners ni cálculo de planilla. Si un KPI necesitara tocar algo de eso, no va el KPI.

## Criterio de aceptación

1. `./bin/verify` = 0.
2. Ninguna pantalla de esta tarea figura en
   `docs/diseno/panel_homogeneo_pendientes.txt`:
   `! grep -E '^Finanzas/(planillas|rendiciones)/' docs/diseno/panel_homogeneo_pendientes.txt`
3. `grep -rn "confirm(" ` sobre las vistas y el JS de página tocados no devuelve
   nada.
4. Prueba en navegador (plan §4.7) con Playwright headless del repo
   (`NODE_PATH=$PWD/node_modules node <script>.js`, script en `runs/`, login
   `miguelo` / `0000` en `http://localhost:8000`): cada pantalla en tema claro y
   oscuro, capturas a la ruta ABSOLUTA `$PWD/runs/119-capturas/`. El script
   termina con exit 0 solo si: `.ag-panel__content` no desborda en horizontal,
   cada modal de confirmación abre visible (probá también una acción que caiga
   en el menú «⋮»), y guardar un alta vuelve a la ficha de edición con su
   `alert-strip` de éxito. Mirá las capturas antes de declarar `OK` — el
   reporte no prueba nada, la captura sí.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/119-verify.log 2>&1 & echo $! > runs/119-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/119-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/119-verify.log`.

## Cierre de cada etapa

`runs/119.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/119.md`
con qué se hizo, **qué falta** y qué decisiones quedaron para el dueño. Al
llegar a `OK`: `runs/119.pr.md` (título en la primera línea, cuerpo debajo),
la fila 119 de la tabla §7 del plan marcada, y si cambió el catálogo,
`sistema_diseno_panel.md` §3 al día.

Commits agrupados por función (un commit por objeto: listado + formulario +
copy + CSS de ese objeto; aparte los contratos de lectura), en español,
imperativo, explicando el porqué. Sin `Co-Authored-By`.
