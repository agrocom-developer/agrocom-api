<!-- ciclo: critica=no turno-noche=1 rama=feature/panel-drones-pausas etapas=3 -->

# Tarea 113 — panel homogéneo: Drones, Pausas y Alertas

Parte del plan `docs/gestion/plan_homogeneizacion_panel.md`. **Leelo entero
antes de tocar nada**: §3 son las reglas del dueño, §4 la receta común que esta
tarea sigue paso a paso, §1.1 lo que queda afuera. Este prompt solo agrega lo
propio de este módulo.

Cargá los skills `verificacion`, `panel-design-ui` y `redaccion-neutra` y `dominio-backend`.

## Prerrequisito

La tarea 111 tiene que estar en `develop`: deben existir
`tests/Unit/PanelHomogeneoTest.php` y
`docs/diseno/panel_homogeneo_pendientes.txt`. Si no están, escribí
`BLOQUEADA` en `runs/113.estado`, explicalo en `runs/113.md` y terminá.

## Pantallas de esta tarea

Módulo `Operaciones` — vistas en
`app/Dominios/Operaciones/Infraestructura/Http/Views/pages/`:

- `drones/` — `index`, `_formulario`, `create`, `edit`
- `pausas/` — `index`, `create` (alta simple, sin edición)
- `alertas/` — `index` (la Alerta tiene máquina: `MaquinaEstadosAlerta`)

Referencia a imitar: `operaciones::pages.estadias.*` (mismo módulo: listado con KPI, formulario con pasos) y `comercial::pages.cultivos.*` para el catálogo simple.

## Qué hacer

Aplicá la receta del plan §4 a cada pantalla de arriba. Lo particular de acá:

- Drones: listado con `confirm()` nativo y tabla propia → patrón completo.
  El dron NO tiene máquina de estados en `Dominio/MaquinaEstados/`: si muestra
  un estado operativo es un dato con badge, sin pasos.
- Pausas: alta simple. El formulario igual usa `form-layout` (sin aside),
  `form-section` y `form-actions-bar`; tras guardar vuelve al listado con su
  flash (no hay `edit()` al cual volver — guía §6.3.2). Rango horario con
  `atoms/time-range`, fecha con `atoms/date`.
- Alertas: filtros viejos (`ag-filtros`) → `filter-panel`. Badge y acciones de
  fila (atender, descartar, lo que la máquina ofrezca) con el tono del estado
  de llegada; definí `TONO_POR_ESTADO` una sola vez en el controlador. Las
  transiciones siguen yendo por la ruta existente al servicio de estados — no
  se agrega ninguna.
- KPI (plan §3.6): Alertas es candidata (abiertas / atendidas / por severidad,
  hasta cuatro, mismo filtro que la tabla, resueltas en el caso de uso del
  listado). Pausas y Drones: solo si el caso de uso ya tiene con qué.

### Resumen relacionado (solo en edición)

- **Dron:** ficha de inventario y órdenes de mantenimiento del dron
  (Mantenimiento, por contrato), baterías asociadas si la FK existe, cuadrillas
  que lo tienen asignado (Personal, por contrato), sesiones voladas (mismo
  módulo).
- Pausas y Alertas no tienen edición: sin resumen.

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

- Etapa 1: Drones (listado + formulario + resumen).
- Etapa 2: Pausas y Alertas.
- Etapa 3: KPI de Alertas si corresponde, pendientes, `./bin/verify`, capturas.

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
- No tocar `estadias/`, `ordenes/` ni `ordenes-trabajo/`: son referencias.

## Criterio de aceptación

1. `./bin/verify` = 0.
2. Ninguna pantalla de esta tarea figura en
   `docs/diseno/panel_homogeneo_pendientes.txt`:
   `! grep -E '^Operaciones/(drones|pausas|alertas)/' docs/diseno/panel_homogeneo_pendientes.txt`
3. `grep -rn "confirm(" ` sobre las vistas y el JS de página tocados no devuelve
   nada.
4. Prueba en navegador (plan §4.7) con Playwright headless del repo
   (`NODE_PATH=$PWD/node_modules node <script>.js`, script en `runs/`, login
   `miguelo` / `0000` en `http://localhost:8000`): cada pantalla en tema claro y
   oscuro, capturas a la ruta ABSOLUTA `$PWD/runs/113-capturas/`. El script
   termina con exit 0 solo si: `.ag-panel__content` no desborda en horizontal,
   cada modal de confirmación abre visible (probá también una acción que caiga
   en el menú «⋮»), y guardar un alta vuelve a la ficha de edición con su
   `alert-strip` de éxito. Mirá las capturas antes de declarar `OK` — el
   reporte no prueba nada, la captura sí.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/113-verify.log 2>&1 & echo $! > runs/113-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/113-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/113-verify.log`.

## Cierre de cada etapa

`runs/113.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/113.md`
con qué se hizo, **qué falta** y qué decisiones quedaron para el dueño. Al
llegar a `OK`: `runs/113.pr.md` (título en la primera línea, cuerpo debajo),
la fila 113 de la tabla §7 del plan marcada, y si cambió el catálogo,
`sistema_diseno_panel.md` §3 al día.

Commits agrupados por función (un commit por objeto: listado + formulario +
copy + CSS de ese objeto; aparte los contratos de lectura), en español,
imperativo, explicando el porqué. Sin `Co-Authored-By`.
