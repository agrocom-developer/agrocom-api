<!-- ciclo: critica=no turno-noche=1 rama=feature/fichas-finanzas etapas=3 descongela=tests -->

# Tarea 126 — fichas de Finanzas al arquetipo Detalle: Planilla, Rendición y Devengos

Tercera y última tarea de la ronda de fichas (124: compuerta y Operaciones;
125: Personal). Misma decisión del dueño (22/9/2026), mismas referencias
(`/panel/ordenes/1`, `/panel/trabajos/1`), misma compuerta y misma hoja
`resources/css/pages/detalle.css`. Si la 124 no está en `develop`,
`runs/126.estado` = `BLOQUEADA`.

Cargá los skills `verificacion`, `panel-design-ui`, `redaccion-neutra` y
`dominio-backend`. Leé el plan §3 y §4, la guía §6.4, `runs/124.md`,
`runs/125.md` y las dos fichas de referencia. **Zona de dinero:** estas
pantallas muestran devengos y planillas; no se cambia ni una suma ni un lector.

## Pantallas de esta tarea

Módulo `Finanzas`, vistas en `app/Dominios/Finanzas/Infraestructura/Http/Views/pages/`:

- `planillas/show.blade.php` — `GET /panel/planillas/{planilla}`: renglones por
  persona, «Aprobar» con `confirm()` nativo.
- `rendiciones/show.blade.php` — `GET /panel/rendiciones/{rendicion}`: gastos
  asociados y disponibles, «Presentar»/«Aprobar»/asociar con tres `confirm()`.
- `devengos/show.blade.php` — `GET /panel/devengos/{persona}`: los devengos de la
  persona autenticada por período, con `.ag-filtros` viejo.

## Qué hacer

### Planilla

- `boton-volver`, `page-header` («Planilla 2026-08», subtítulo con cantidad de
  personas, chip con el estado en el tono de `TransicionesPlanilla`). En
  `actions`, solo **Aprobar** cuando corresponde (borrador y permiso), en el tono
  del estado de llegada (`success-outline` si `aprobada` es success en
  `TONO_POR_ESTADO`), con `confirm-modal` — el `confirm()` nativo desaparece.
- KPI: total de la planilla, personas, devengos incluidos, período.
- Principal: renglones por persona como `index-table` (persona, jornales, por
  hectárea, total, recibo PDF cuando está aprobada como acción `info-outline`).
  Los totales que se muestran son los que ya calcula el controlador/caso de uso;
  no vuelvas a sumar en Blade.
- Aside: `summary-card` (período, generada por, aprobada por y cuándo);
  «Relacionado» (listado de planillas, devengos del período si hay ruta);
  «Actividad» con `timeline` de generada/aprobada por columnas reales.

### Rendición

- Igual anatomía. Chip con el estado en el tono de `TransicionesRendicion`;
  `actions` con **Presentar** y **Aprobar** según estado y permiso, cada una en
  el tono de su estado de llegada y con `confirm-modal`. El aviso «quien rindió
  no aprueba» va como `alert-strip` informativo, en tuteo neutro (hoy dice
  «vos»): revisá `lang/es/finanzas.php` con el skill `redaccion-neutra`.
- KPI: total rendido, cantidad de gastos, base, jefe de campo.
- Principal: gastos asociados como `index-table`; «Gastos disponibles» solo si
  está abierta, como `index-table` con la acción de asociar por `confirm-modal`
  (el form fuera de `row-actions`). Si no está abierta, la sección se ve igual
  con `empty-state` explicando por qué (§6.3.5).
- Aside: `summary-card`, «Relacionado» (listado, base por su contrato de
  lectura si existe), «Actividad» (creada, presentada, aprobada).

### Devengos de la persona

- Es la única ficha que **no** tiene objeto padre: la persona autenticada. El
  `abort_unless` del controlador que ata `{persona}` al usuario **no se toca**
  (invariante de exposición entre usuarios: es la prueba de seguridad que sí se
  mantiene). Agregá, si no existe, el test en `tests/Unit` que cubra la regla
  pura que use el controlador para esa comparación; si la regla vive solo en el
  controlador, no la muevas: anotalo en `runs/126.md`.
- `page-header` con la persona y el período; el filtro de período pasa a
  `filter-panel` (`.ag-filtros` desaparece).
- KPI: total del período (el que ya calcula `ListarDevengosPersona`), cantidad,
  jornales vs. por hectárea, absorbidos (ADR 0023) — solo si el lector ya
  expone esos desgloses; si no, tres tarjetas. **No agregues una suma nueva en
  la vista ni en el controlador.**
- Principal: `index-table` sin columna de acciones (fecha, trabajo, modalidad,
  hectáreas, monto, absorbido). Aside: «Relacionado» (anticipos de la persona si
  hay ruta y permiso), sin timeline.
- Después de esta tarea `filter-bar.css` (el `.ag-filtros` viejo) debería quedar
  sin uso en todo el panel: comprobalo con `grep -rn 'ag-filtros' app resources`
  y, si es así, borralo junto con su import.

### Cierre

Sacá las tres fichas de `panel_homogeneo_pendientes.txt` (la lista tiene que
quedar **vacía**), §8 del plan al día, `./bin/verify`, navegador.

## Datos para probar (compose)

Planillas `#1` (2026-08, aprobada, con recibos) y `#2` (2026-09, borrador).
Rendiciones `#1` aprobada, `#2` presentada, `#3` abierta (base 2). Devengos:
entrá como `josue.haenke` / `0000` (piloto, persona #4, cinco devengos desde
agosto) y probá período `2026-08`. Para planillas/rendiciones, `marcela.antelo`
/ `0000` o `miguelo` / `0000`. No apruebes la planilla #2 ni la rendición #2
«para probar»: abrí el modal y cancelalo; los datos demo quedan como están.

## Qué NO hacer

- Ni un cambio en `GenerarPlanilla`, `AprobarPlanilla`, `ListarDevengosPersona`,
  transiciones ni permisos. Ninguna suma nueva (invariante 6: lo que se muestra
  es lo que calcula el caso de uso con `BigDecimal`).
- No aflojes el gate de `{persona}` = usuario en Devengos.
- Sin `git stash`, sin `reset --hard`.

## Criterio de aceptación

- `./bin/verify` = 0 con `docs/diseno/panel_homogeneo_pendientes.txt` sin rutas.
- `grep -rc 'confirm(' app/Dominios/Finanzas/Infraestructura/Http/Views/pages/{planillas,rendiciones}/show.blade.php` = 0 en ambas;
  `grep -rn 'ag-filtros' app resources` vacío.
- Playwright (`runs/126-navegador.cjs`): planillas #1 y #2, rendiciones #1, #2 y
  #3, devengos de Josué (2026-08 y un mes vacío), claro y oscuro, capturas en
  `runs/126-capturas/` (absoluta), 200, sin desborde, cada modal abre y se
  cancela; con `marcela.antelo`, `/panel/devengos/4` responde 404 o 403 (nunca 200).

## Cierre obligatorio de cada etapa

`runs/126.estado`, `runs/126.md`, y al `OK` `runs/126.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.
