<!-- ciclo: critica=si turno-noche=1 rama=feature/panel-trabajos-reparto etapas=4 descongela=tests modelo=opus -->

# Tarea 114 — panel homogéneo: Trabajo, Reparto de cuadrillas, Reportes técnicos y Validación

Parte del plan `docs/gestion/plan_homogeneizacion_panel.md`. **Leelo entero
antes de tocar nada**: §3 son las reglas del dueño, §4 la receta común que esta
tarea sigue paso a paso, §1.1 lo que queda afuera. Este prompt solo agrega lo
propio de este módulo.

Cargá los skills `verificacion`, `panel-design-ui` y `redaccion-neutra`, `dominio-backend` y `seguridad-roles`.

## Prerrequisito

La tarea 111 tiene que estar en `develop`: deben existir
`tests/Unit/PanelHomogeneoTest.php` y
`docs/diseno/panel_homogeneo_pendientes.txt`. Si no están, escribí
`BLOQUEADA` en `runs/114.estado`, explicalo en `runs/114.md` y terminá.

## Pantallas de esta tarea

Módulo `Operaciones` — vistas en
`app/Dominios/Operaciones/Infraestructura/Http/Views/pages/`:

- `trabajos/edit` — ficha de edición de un Trabajo (máquina: `TransicionesTrabajo`)
- `reparto-cuadrillas/index` — listado de órdenes a repartir
- `reportes-tecnicos/index` — listado con filtros viejos
- `sesiones/validacion` — cola de validación: SOLO su capa de listado
- NO se tocan: `trabajos/show`, `trabajos/evidencias`, `reparto-cuadrillas/show`.

Referencia a imitar: `operaciones::pages.ordenes._formulario` + `PasosDeOrden` + `_orden-modales` (pasos con un permiso por transición).

## Qué hacer

Aplicá la receta del plan §4 a cada pantalla de arriba. Lo particular de acá:

- **Trabajo (edición):** `form-layout`, pasos `step-arrow` bajo la cabecera
  armados con `PasosDeEstado::armar()` sobre `TransicionesTrabajo` (guía
  §6.3.4): presentador `PasosDeTrabajo` si hay reglas propias, textos en
  `operaciones.trabajo.estado.*` y `estado_ayuda.*`, partial `_cambio-estado`
  con forms y modales FUERA del formulario, tono por estado definido una vez.
  El paso no cambia el estado: abre el modal, y el modal envía a la ruta de
  cambio de estado **que ya existe**. Si una transición no tiene ruta en el
  panel hoy, ese paso queda `pending` y lo anotás en `runs/114.md` — no se
  agrega la ruta. Test unitario del presentador, como `PasosDeOrdenTest`.
- **Aviso de estado en el aside (plan §3.5):** mientras el Trabajo no esté en
  su estado operativo, el aside es una sola `empty-state` que dice qué paso
  falta; desde ahí, las tarjetas.
- **Reparto y Reportes técnicos:** `filter-panel` + `index-table` +
  `row-actions` (la acción de entrar al reparto es «Ver»: `info-outline`).
- **Validación de sesiones:** es pantalla crítica (invariante 4: validador ≠
  piloto, a nivel de persona). Se homogeneiza SOLO lo visual del listado —
  toolbar, `index-table`, badges, `confirm-modal` en vez de confirmación
  nativa si la hay. Ni una línea del controlador que decide quién puede
  validar, ni del caso de uso, ni del evento `SesionValidada`. Si la pantalla
  no es una tabla de objetos y no encaja en el arquetipo, dejala como está,
  agregala a las exclusiones con el porqué en `runs/114.md` y seguí.

### Resumen relacionado (solo en edición)

- **Trabajo:** su orden de trabajo y orden de aplicación (padres, mismo
  módulo), sesiones del trabajo, evidencias; cuadrilla asignada (Personal, por
  contrato).

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

- Etapa 1: presentador de pasos del Trabajo + su test + textos.
- Etapa 2: ficha del Trabajo (layout, pasos, modales, aside con aviso de estado).
- Etapa 3: Reparto y Reportes técnicos.
- Etapa 4: Validación (solo listado), pendientes, `./bin/verify`, capturas.

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
- No tocar `MaquinaEstadosTrabajo`, `MaquinaEstadosSesion`, el motor de sync ni ningún listener.
- `descongela=tests` es para el test NUEVO del presentador: no edites tests existentes.

## Criterio de aceptación

1. `./bin/verify` = 0.
2. Ninguna pantalla de esta tarea figura en
   `docs/diseno/panel_homogeneo_pendientes.txt`:
   `! grep -E '^Operaciones/(trabajos|reparto-cuadrillas|reportes-tecnicos|sesiones)/' docs/diseno/panel_homogeneo_pendientes.txt`
3. `grep -rn "confirm(" ` sobre las vistas y el JS de página tocados no devuelve
   nada.
4. Prueba en navegador (plan §4.7) con Playwright headless del repo
   (`NODE_PATH=$PWD/node_modules node <script>.js`, script en `runs/`, login
   `miguelo` / `0000` en `http://localhost:8000`): cada pantalla en tema claro y
   oscuro, capturas a la ruta ABSOLUTA `$PWD/runs/114-capturas/`. El script
   termina con exit 0 solo si: `.ag-panel__content` no desborda en horizontal,
   cada modal de confirmación abre visible (probá también una acción que caiga
   en el menú «⋮»), y guardar un alta vuelve a la ficha de edición con su
   `alert-strip` de éxito. Mirá las capturas antes de declarar `OK` — el
   reporte no prueba nada, la captura sí.

## Sobre `./bin/verify` en esta máquina (macOS)

`timeout` y `tail --pid` no existen acá. Lanzalo una vez con
`nohup ./bin/verify > runs/114-verify.log 2>&1 & echo $! > runs/114-verify.pid`
y esperalo con llamadas Bash cortas:
`p=$(cat runs/114-verify.pid); n=0; while kill -0 $p 2>/dev/null && [ $n -lt 50 ]; do sleep 10; n=$((n+1)); done; tail -5 runs/114-verify.log`.

## Cierre de cada etapa

`runs/114.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/114.md`
con qué se hizo, **qué falta** y qué decisiones quedaron para el dueño. Al
llegar a `OK`: `runs/114.pr.md` (título en la primera línea, cuerpo debajo),
la fila 114 de la tabla §7 del plan marcada, y si cambió el catálogo,
`sistema_diseno_panel.md` §3 al día.

Commits agrupados por función (un commit por objeto: listado + formulario +
copy + CSS de ese objeto; aparte los contratos de lectura), en español,
imperativo, explicando el porqué. Sin `Co-Authored-By`.
