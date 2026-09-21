<!-- ciclo: critica=no turno-noche=1 rama=feature/panel-comercial-restante etapas=3 -->

# Tarea 120 — panel homogéneo: Cultivos, Facturas y Reportes comerciales

Parte del plan `docs/gestion/plan_homogeneizacion_panel.md`. **Leelo entero
antes de tocar nada**: §3 son las reglas del dueño, §4 la receta común que esta
tarea sigue paso a paso, §1.1 lo que queda afuera. Este prompt solo agrega lo
propio de este módulo.

Cargá los skills `verificacion`, `panel-design-ui` y `redaccion-neutra` y `dominio-backend`.

## Prerrequisito

La tarea 111 tiene que estar en `develop`: deben existir
`tests/Unit/PanelHomogeneoTest.php` y
`docs/diseno/panel_homogeneo_pendientes.txt`. Si no están, escribí
`BLOQUEADA` en `runs/120.estado`, explicalo en `runs/120.md` y terminá.

## Pantallas de esta tarea

Módulo `Comercial` — vistas en
`app/Dominios/Comercial/Infraestructura/Http/Views/pages/`:

- `cultivos/` — `index`, `_formulario` (referencia a medias: le faltan dos piezas)
- `facturas/` — `index`, `create`
- `reportes-comerciales/index`

Referencia a imitar: el resto del propio módulo: `clientes`, `propiedades`, `lotes`, `contratos`.

## Qué hacer

Aplicá la receta del plan §4 a cada pantalla de arriba. Lo particular de acá:

- **Cultivos:** el listado todavía arma su tabla a mano → `index-table`. El
  formulario no tiene aside → resumen relacionado real (no estático: la guía
  §6.3.1 lo dejó pendiente de conectar).
- **Facturas:** listado sin toolbar, tabla propia → patrón completo con
  filtros (cliente, contrato, período, estado si lo tiene). Alta con
  `form-layout` sin aside; vuelve al listado con flash. Montos en mono. Si la
  factura tiene un estado que es dato (emitida/anulada) → badge; sin máquina
  en `Dominio/MaquinaEstados/`, sin pasos.
- **Reportes comerciales:** filtros dentro de `filter-panel`, tabla con
  `index-table`, y el vacío por filtro migra de `alert-strip` a `empty-state`
  (guía §5.1 lo nombra como pendiente). Las acciones de descarga se conservan.
- KPI: Facturas es candidata (facturado del período, cantidad, pendiente de
  cobro si el dato existe).

### Resumen relacionado (solo en edición)

- **Cultivo:** lotes sembrados con ese cultivo en la campaña vigente y
  propiedades donde aparece (mismo módulo; la campaña, por el contrato de
  `Campania`). Recordá: el cultivo es dato del lote dentro de la campaña.

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

- Etapa 1: Cultivos (tabla + resumen relacionado).
- Etapa 2: Facturas.
- Etapa 3: Reportes comerciales, pendientes, `./bin/verify`, capturas.

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
- No tocar clientes, propiedades, lotes ni contratos: son las referencias.

## Criterio de aceptación

1. `./bin/verify` = 0.
2. Ninguna pantalla de esta tarea figura en
   `docs/diseno/panel_homogeneo_pendientes.txt`:
   `! grep -E '^Comercial/(cultivos|facturas|reportes-comerciales)/' docs/diseno/panel_homogeneo_pendientes.txt`
3. `grep -rn "confirm(" ` sobre las vistas y el JS de página tocados no devuelve
   nada.
4. Prueba en navegador (plan §4.7) con Playwright headless del repo
   (`NODE_PATH=$PWD/node_modules node <script>.js`, script en `runs/`, login
   `miguelo` / `0000` en `http://localhost:8000`): cada pantalla en tema claro y
   oscuro, capturas a la ruta ABSOLUTA `$PWD/runs/120-capturas/`. El script
   termina con exit 0 solo si: `.ag-panel__content` no desborda en horizontal,
   cada modal de confirmación abre visible (probá también una acción que caiga
   en el menú «⋮»), y guardar un alta vuelve a la ficha de edición con su
   `alert-strip` de éxito. Mirá las capturas antes de declarar `OK` — el
   reporte no prueba nada, la captura sí.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/120-verify.log 2>&1 & echo $! > runs/120-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/120-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/120-verify.log`.

## Cierre de cada etapa

`runs/120.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/120.md`
con qué se hizo, **qué falta** y qué decisiones quedaron para el dueño. Al
llegar a `OK`: `runs/120.pr.md` (título en la primera línea, cuerpo debajo),
la fila 120 de la tabla §7 del plan marcada, y si cambió el catálogo,
`sistema_diseno_panel.md` §3 al día.

Commits agrupados por función (un commit por objeto: listado + formulario +
copy + CSS de ese objeto; aparte los contratos de lectura), en español,
imperativo, explicando el porqué. Sin `Co-Authored-By`.
