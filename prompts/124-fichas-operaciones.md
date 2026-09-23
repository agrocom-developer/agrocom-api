<!-- ciclo: critica=no turno-noche=1 rama=feature/fichas-operaciones etapas=3 descongela=tests -->

# Tarea 124 — fichas de Operaciones al arquetipo Detalle: Trabajo y Reparto de cuadrillas

**Decisión del dueño (22/9/2026):** el arquetipo Detalle (§6.4 de
`docs/diseno/guia_pantalla_panel.md`) se adopta en **todo** el sistema, no solo
en objetos transaccionales especiales. Las dos fichas de referencia —el diseño
aprobado— son `operaciones::pages.ordenes.show` (`/panel/ordenes/1`) y
`operaciones::pages.ordenes-trabajo.show` (`/panel/trabajos/1`). Con eso se cierra
la pregunta que `docs/gestion/plan_homogeneizacion_panel.md` §1.1 dejó abierta.
Esta tarea abre la ronda de fichas (124, 125, 126) y arma su compuerta.

Cargá los skills `verificacion`, `panel-design-ui`, `redaccion-neutra` y
`dominio-backend`. Leé el plan de homogeneización entero (§3 son las reglas del
dueño, §4 la receta) y la guía §6.4. Leé las dos fichas de referencia completas y
`resources/css/pages/ordenes.css` antes de escribir una línea.

## Pantallas de esta tarea

Módulo `Operaciones`, vistas en `app/Dominios/Operaciones/Infraestructura/Http/Views/pages/`:

- `trabajos/show.blade.php` — detalle de UN trabajo (equipo×lote):
  `GET /panel/trabajos/detalle/{trabajo}`, `TrabajosController::show()`. Hoy tiene
  `confirm()` nativo y su propio layout.
- `reparto-cuadrillas/show.blade.php` — reparto de una orden vigente:
  `GET /panel/reparto-cuadrillas/{orden}`, `RepartoCuadrillasController::mostrar()`.
  Ya no está en el menú (HU-101): se llega desde la ficha de la orden. El dueño la
  nombró como ejemplo de lo que hay que mejorar.

`trabajos/edit` y `trabajos/evidencias` NO son de esta tarea.

## Qué hacer

### 0. Decisión y compuerta (primero, en su propio commit)

- `docs/gestion/plan_homogeneizacion_panel.md`: en §1.1 sacá a las páginas de
  detalle de «fuera de alcance» y explicá la decisión con fecha; agregá una
  sección «8. Fichas» con la tabla de las siete fichas (Trabajo y Reparto → 124;
  Cuadrilla y Desempeño de persona → 125; Planilla, Rendición y Devengos → 126),
  su ruta y su estado. La guía §6.4 ya nombra la referencia: solo agregale una
  línea con la decisión.
- `tests/Unit/PanelHomogeneoTest.php`: hoy `panelHomogeneoExcluida()` perdona
  todo `show.blade.php` (y `personas/desempeno` cae fuera por nombre). Sacá esa
  exclusión y agregá un conjunto de reglas **solo para fichas**, las verificables
  por texto del arquetipo Detalle: `page-header`, `boton-volver`, `form-layout`,
  `stat-card`, ningún `confirm(` nativo, ningún `.ag-filtros`, ningún color
  literal. No le exijas a una ficha reglas de listado o formulario
  (`index-table` sí puede aparecer, no es obligatorio). Las dos fichas de
  referencia tienen que pasar **sin tocarlas**. Las cinco fichas de las tareas
  125 y 126 (y las dos de esta, hasta que las termines) van a
  `docs/diseno/panel_homogeneo_pendientes.txt`; la lista solo se achica.
- CSS compartido: las clases `ag-ordenes-detalle*` que la ficha de la OT reusa
  desde `ordenes.css` pasan a una hoja propia `resources/css/pages/detalle.css`
  con nombre neutro (`ag-detalle__kpis`, `ag-detalle__campo`, `__campo-label`,
  `__campo-valor`, `__vinculos`), importada donde corresponda, y las dos fichas
  de referencia pasan a usarla. Es un renombre, no un rediseño: capturas antes y
  después de `/panel/ordenes/1` y `/panel/trabajos/1` tienen que ser iguales a ojo.

### 1. Detalle de Trabajo

Misma anatomía que la ficha de la OT. Concretamente:

- `boton-volver` con memento (vuelve a la OT o al listado).
- `page-header`: título «Trabajo #N», subtítulo con lote y cuadrilla, chip con
  el badge del estado de tablero (`estadoTablero()`), y en `actions` solo lo que
  ya existe: **Editar** (`warning-outline`, si `@puede` y no está `validado`) y
  **Eliminar** (`danger-outline`, mismo gate) con `confirm-modal` — el `confirm()`
  nativo desaparece. El form y el modal van fuera de `row-actions`.
- KPI (3–4 `stat-card`): hectáreas declaradas, hectáreas aplicadas (lo que ya
  calcula `CalcularCoberturaTrabajo` o la suma que use hoy la vista — no
  inventes una cuenta nueva), sesiones (validadas / total), y turno.
- Columna principal, en `form-section` de solo lectura: datos del trabajo
  (orden, OT, lote, cuadrilla, turno y horario, condición de pago del equipo
  si la OT la tiene — leela como lo hace la ficha de la OT); sesiones como
  `index-table` (piloto, dron, hectáreas, estado, motivo de cierre, motivo del
  rechazo); evidencias: la sección se queda como está hoy en cuanto a datos
  (enlaza a la galería `panel.trabajos.evidencias` si existe la ruta).
- Aside: `progress-meter` con la cobertura; «Relacionado» con `link-row` a la
  OT, la orden, la cuadrilla (ruta `panel.cuadrillas.show`), el acta en PDF y el
  reporte técnico en PDF cuando existan; «Actividad» con `timeline` solo de
  eventos reconstruibles (`created_at` del trabajo, cada sesión validada/rechazada,
  cierre, acta firmada) — nunca una bitácora que no existe.
- Sin cambios en `TrabajosController` salvo armar datos para pintar
  (vínculos, actividad, KPI), como hace `OrdenesController::show()`.

### 2. Reparto de cuadrillas

Es una ficha **con un formulario adentro** (la asignación en bloque); el caso de
uso detrás (`asignar()` → `CrearOrdenTrabajo`) no se toca ni una línea.

- `boton-volver` a la ficha de la orden; `page-header` con la orden, el cliente
  y el chip del estado de la orden.
- KPI: hectáreas solicitadas, asignadas, restantes, equipos asignados.
- Principal: resumen por lote como `index-table`; equipos ya asignados como
  `index-table` (con «Ver» al trabajo, `info-outline`); y el formulario de
  asignación como `form-section`s con los campos del catálogo (§3.3 del plan) y
  `form-actions-bar`. El bloque por equipo se arma con el mismo criterio que
  `ordenes-trabajo/_equipo-bloque` — reusalo si encaja, no lo dupliques a mano.
- Aside: `summary-card`/`progress-meter` del avance del reparto, «Relacionado»
  (orden, contrato por su contrato de lectura, OT ya creadas) y sin timeline.
- Si la orden no está vigente, la ficha se ve igual con el formulario
  reemplazado por un `empty-state` que dice por qué (regla §6.3.5: nada se
  esconde).
- `resources/css/pages/reparto-cuadrillas.css`: borrá lo que el catálogo ya
  resuelve.

### 3. Cierre

Sacá las dos fichas de `panel_homogeneo_pendientes.txt`, actualizá §8 del plan,
`./bin/verify`, y la prueba en navegador.

## Datos para probar (compose, ya sembrado con `Demo\DemoSeeder`)

Cuentas `marcela.antelo` / `0000` (encargada de operaciones) y `miguelo` / `0000`.
Trabajos: `#1` y `#2` cerrados con acta firmada y sesiones validadas (OT #1,
orden #1 consumida), `#3` y `#5` abiertos (OT #2, orden #2 vigente), `#4`
cerrado con acta. Reparto: orden `#2` (vigente, con lotes por repartir) y orden
`#4` (emitida: sin formulario) y `#3` (pausada). No borres ni cambies datos
demo; lo que crees para probar, queda.

## Cómo repartir las etapas

- Etapa 1: paso 0 entero (plan, compuerta, `detalle.css`) + `./bin/verify`.
- Etapa 2: Detalle de Trabajo.
- Etapa 3: Reparto de cuadrillas, cierre y navegador.

## Qué NO hacer

- No inventes acciones (nada de «Duplicar», «Cerrar trabajo»): el cierre manual
  del Trabajo sigue siendo una decisión abierta del dueño.
- No toques máquinas de estados, casos de uso de escritura, permisos ni
  migraciones. No cambies el scoping de nada.
- No armes un modal a mano: `confirm-modal` con campos en su slot.
- No uses `{{ }}` para pasar texto a un componente (`EscapePropsBladeTest`);
  todo el copy en `lang/es/operaciones.php` en tuteo neutro.
- No unifiques vistas en un `_formulario` para las fichas: `PanelHomogeneoTest`
  nombra archivos (ver `tests/Unit/PanelHomogeneoTest.php` antes de renombrar).
- No uses `git stash` (hay uno ajeno) ni `reset --hard` (hook).

## Criterio de aceptación

- `./bin/verify` devuelve 0, con `PanelHomogeneoTest` ya exigiendo el arquetipo
  Detalle a las fichas (las de 125 y 126 perdonadas por la lista, ninguna otra).
- `grep -c 'confirm(' app/Dominios/Operaciones/Infraestructura/Http/Views/pages/trabajos/show.blade.php` = 0.
- Playwright headless (`runs/124-navegador.cjs`, molde `runs/122-barrido.cjs`;
  trampas en la memoria «Trampas del script Playwright de homogeneización»):
  las cuatro fichas (trabajo #1, #3; reparto #2, #4) en claro y oscuro,
  capturas a ruta absoluta `runs/124-capturas/`, HTTP 200, `.ag-panel__content`
  sin desborde, el modal de eliminar abre y se cancela. `/panel/ordenes/1` y
  `/panel/trabajos/1` iguales a ojo antes y después del renombre de CSS.

## Cierre obligatorio de cada etapa

`runs/124.estado` (`PARCIAL` / `OK` / `BLOQUEADA`), `runs/124.md` con qué se
hizo y qué falta, y al `OK` `runs/124.pr.md` (título en la primera línea, cuerpo
debajo). Commits agrupados por función, en español, imperativo, sin
`Co-Authored-By`.
