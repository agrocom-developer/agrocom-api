<!-- ciclo: critica=no turno-noche=1 rama=feature/fichas-personal etapas=2 descongela=tests -->

# Tarea 125 — fichas de Personal al arquetipo Detalle: Cuadrilla y Desempeño de persona

Segunda tarea de la ronda de fichas que abrió la 124 (decisión del dueño del
22/9/2026: el arquetipo Detalle, §6.4 de `docs/diseno/guia_pantalla_panel.md`,
se adopta en todo el sistema; referencias `/panel/ordenes/1` y
`/panel/trabajos/1`). La 124 dejó la compuerta (`PanelHomogeneoTest` exige el
arquetipo a toda ficha que no figure en `docs/diseno/panel_homogeneo_pendientes.txt`),
la hoja compartida `resources/css/pages/detalle.css` y la sección §8 «Fichas» de
`docs/gestion/plan_homogeneizacion_panel.md`. Si algo de eso no está en
`develop`, `runs/125.estado` = `BLOQUEADA` y explicalo en `runs/125.md`.

Cargá los skills `verificacion`, `panel-design-ui`, `redaccion-neutra` y
`dominio-backend`. Leé el plan §3 y §4, la guía §6.4, `runs/124.md` y las dos
fichas de referencia.

## Pantallas de esta tarea

Módulo `Personal`, vistas en `app/Dominios/Personal/Infraestructura/Http/Views/pages/`:

- `cuadrillas/show.blade.php` — `GET /panel/cuadrillas/{equipoTrabajo}`,
  `CuadrillasController::show()`: integrantes y recursos vigentes a una fecha.
- `personas/desempeno.blade.php` — `GET /panel/personas/{persona}/desempeno`,
  `PersonasController::desempenio()`: hechos por sesión de una persona, con
  filtros por GET (`.ag-filtros` viejo) y el filtro dependiente de
  `resources/js/pages/personas-desempeno.js`.

## Qué hacer

### Cuadrilla

- `boton-volver`, `page-header` (código y nombre, subtítulo con la base, chip con
  el estado Activa/Inactiva en el tono de `TransicionesEquipoTrabajo`), en
  `actions` solo lo que ya ofrece el listado: **Editar** (`warning-outline`).
- El selector de fecha («vigentes al…») deja de ser un form suelto: va en
  `filter-panel` (es un filtro de la ficha), con el mismo `GET`.
- KPI: integrantes vigentes, recursos vigentes, días de vigencia de la cuadrilla
  (desde/hasta), estadías en curso si el dato ya llega por contrato de lectura
  (`LecturaResumenCuadrilla` o el que use `cuadrillas/edit`); si no llega, tres
  tarjetas y no cuatro.
- Principal: integrantes e integrantes históricos, recursos (dron, camioneta,
  generador, baterías) y accesorios, cada uno como `index-table`.
- **Formularios de alta/finalizar:** HU-101 punto 4 los puso en la ficha de
  EDICIÓN (`cuadrillas/edit`, diálogos «Agregar» y «Finalizar»/«Quitar»).
  Comprobá que ahí estén; si es así, `show` queda de **solo lectura** y enlaza a
  Editar — no dupliques formularios. Si `edit` no los tiene, quedan en `show`
  como `confirm-modal` con campos en su slot, nunca `confirm()` nativo.
- Aside: `summary-card` con base, vigencia y creada por; «Relacionado» con
  `link-row` a la base, a las órdenes de trabajo y estadías de la cuadrilla
  (lo de Operaciones llega por `Contratos/` de Operaciones, como lo hace
  `cuadrillas/edit` — nunca un modelo ajeno); «Actividad» con `timeline` solo de
  columnas reales (creación, altas y bajas de integrantes/recursos por sus
  `desde`/`hasta`).

### Desempeño de persona

- `boton-volver` a la ficha de la persona; `page-header` con nombre y rol
  operativo; sin acciones (no hay ninguna que exista).
- Los filtros (desde, hasta, cliente, campaña) pasan a `filter-panel` con los
  campos del catálogo; el JS del filtro dependiente sigue funcionando (adaptá los
  ganchos `data-*`, no la lógica).
- Los cuatro `stat-card` que ya tiene se quedan como KPI bajo la cabecera.
- Principal: sesiones, rechazos e incidencias como `index-table` con vacíos por
  `empty-state`.
- Aside: «Relacionado» (ficha de la persona, cuadrillas que integra, sus devengos
  si el permiso del rol activo lo permite —`finanzas.devengo.ver`— y el contrato
  de lectura existe); sin timeline.
- **Sin puntaje, ranking ni semáforo**: la regla de la tarea 81 sigue.
- `resources/css/pages/` de las dos pantallas: borrá lo que el catálogo resuelve;
  si `filter-bar.css` queda sin uso después de esta tarea y de la 126, anotalo
  en `runs/125.md` (no lo borres: lo usa Devengos hasta la 126).

### Cierre

Sacá las dos fichas de `panel_homogeneo_pendientes.txt`, actualizá §8 del plan,
`./bin/verify`, navegador.

## Datos para probar (compose)

`marcela.antelo` / `0000`. Cuadrillas `#1` (EQ3, activa, tres baterías),
`#3` (EQ5, inactiva, cerrada); fecha `2026-08-05` y hoy. Desempeño: persona
`#4` (Josué, cinco sesiones validadas, una rechazada en el relato) y `#5`.

## Qué NO hacer

Nada de negocio: ni `ArmarCuadrilla`, ni transiciones, ni permisos. No dupliques
un formulario que ya vive en `edit`. Copy en `lang/es/personal.php`, tuteo
neutro, pasado enlazado. Sin `git stash`, sin `reset --hard`.

## Criterio de aceptación

- `./bin/verify` = 0 (con las dos fichas ya fuera de la lista de pendientes).
- `grep -c 'ag-filtros' app/Dominios/Personal/Infraestructura/Http/Views/pages/personas/desempeno.blade.php` = 0.
- Playwright (`runs/125-navegador.cjs`): cuadrillas #1 y #3, desempeño #4 con y
  sin filtro de campaña, claro y oscuro, capturas en `runs/125-capturas/`
  (ruta absoluta), 200, sin desborde de `.ag-panel__content`, el filtro
  dependiente sigue poblando campañas al elegir cliente.

## Cierre obligatorio de cada etapa

`runs/125.estado`, `runs/125.md`, y al `OK` `runs/125.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.
