<!-- ciclo: critica=no turno-noche=1 rama=feature/panel-equipos-mantenimiento etapas=4 -->

# Tarea 115 — panel homogéneo: Baterías, Generadores, Vehículos y Fichas de dron

Parte del plan `docs/gestion/plan_homogeneizacion_panel.md`. **Leelo entero
antes de tocar nada**: §3 son las reglas del dueño, §4 la receta común que esta
tarea sigue paso a paso, §1.1 lo que queda afuera. Este prompt solo agrega lo
propio de este módulo.

Cargá los skills `verificacion`, `panel-design-ui` y `redaccion-neutra` y `dominio-backend`.

## Prerrequisito

La tarea 111 tiene que estar en `develop`: deben existir
`tests/Unit/PanelHomogeneoTest.php` y
`docs/diseno/panel_homogeneo_pendientes.txt`. Si no están, escribí
`BLOQUEADA` en `runs/115.estado`, explicalo en `runs/115.md` y terminá.

## Pantallas de esta tarea

Módulo `Mantenimiento` — vistas en
`app/Dominios/Mantenimiento/Infraestructura/Http/Views/pages/`:

- `baterias/`, `generadores/`, `vehiculos/`, `fichas-dron/` — cada uno con
  `index`, `_formulario`, `create`, `edit`.

Referencia a imitar: `comercial::pages.propiedades.*` y `comercial::pages.lotes.*` (formularios con campos variados y resumen), `comercial::pages.cultivos.index` para el listado.

## Qué hacer

Aplicá la receta del plan §4 a cada pantalla de arriba. Lo particular de acá:

- Los cuatro listados: `table-search` ya está; falta `filter-panel` (tres
  usan `ag-filtros` viejo), `index-table`, `row-actions` y `confirm-modal`
  (los cuatro eliminan con `confirm()` nativo).
- Ninguno de estos objetos tiene máquina en `Dominio/MaquinaEstados/`: el
  estado del vehículo (incluida «pausa», HU-84) es un dato → badge con tono
  fijo y columna sí (es información operativa, no un on/off), sin pasos.
- Contadores que crecen solos (ciclos de batería, horas de generador): se
  muestran en mono y de solo lectura donde ya lo son — no los vuelvas editables.
- Fichas de dron tiene 3 controles crudos y Vehículos 1: al átomo, salvo `hidden`.
- Números con unidad (kg, horas, ciclos, km): `atoms/input` como grupo con
  sufijo, no un label aparte.
- KPI (plan §3.6): opcional por listado, solo si el caso de uso puede dar
  cifras útiles (p. ej. baterías cerca del tope de ciclos). Ante la duda, no.

### Resumen relacionado (solo en edición)

- **Batería / Generador / Vehículo:** órdenes de mantenimiento y planes que lo
  nombran (mismo módulo); el dron o la cuadrilla al que está asignado, si la FK
  existe (por contrato de Operaciones/Personal).
- **Ficha de dron:** el dron (Operaciones, por contrato), sus órdenes de
  mantenimiento, repuestos usados (Inventario, por contrato) si hay relación.

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

- Etapa 1: Baterías (sirve de molde para los otros tres).
- Etapa 2: Generadores y Vehículos.
- Etapa 3: Fichas de dron.
- Etapa 4: resúmenes relacionados que falten, pendientes, `./bin/verify`, capturas.

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
- No tocar `ordenes/` ni `planes/`: son de la tarea 116.

## Criterio de aceptación

1. `./bin/verify` = 0.
2. Ninguna pantalla de esta tarea figura en
   `docs/diseno/panel_homogeneo_pendientes.txt`:
   `! grep -E '^Mantenimiento/(baterias|generadores|vehiculos|fichas-dron)/' docs/diseno/panel_homogeneo_pendientes.txt`
3. `grep -rn "confirm(" ` sobre las vistas y el JS de página tocados no devuelve
   nada.
4. Prueba en navegador (plan §4.7) con Playwright headless del repo
   (`NODE_PATH=$PWD/node_modules node <script>.js`, script en `runs/`, login
   `miguelo` / `0000` en `http://localhost:8000`): cada pantalla en tema claro y
   oscuro, capturas a la ruta ABSOLUTA `$PWD/runs/115-capturas/`. El script
   termina con exit 0 solo si: `.ag-panel__content` no desborda en horizontal,
   cada modal de confirmación abre visible (probá también una acción que caiga
   en el menú «⋮»), y guardar un alta vuelve a la ficha de edición con su
   `alert-strip` de éxito. Mirá las capturas antes de declarar `OK` — el
   reporte no prueba nada, la captura sí.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/115-verify.log 2>&1 & echo $! > runs/115-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/115-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/115-verify.log`.

## Cierre de cada etapa

`runs/115.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/115.md`
con qué se hizo, **qué falta** y qué decisiones quedaron para el dueño. Al
llegar a `OK`: `runs/115.pr.md` (título en la primera línea, cuerpo debajo),
la fila 115 de la tabla §7 del plan marcada, y si cambió el catálogo,
`sistema_diseno_panel.md` §3 al día.

Commits agrupados por función (un commit por objeto: listado + formulario +
copy + CSS de ese objeto; aparte los contratos de lectura), en español,
imperativo, explicando el porqué. Sin `Co-Authored-By`.
