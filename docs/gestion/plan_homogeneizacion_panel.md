# Plan — homogeneización del panel (listados y formularios)

**Abierto: 20/9/2026.** Continúa la iniciativa del 17/9/2026 (PR #239, módulo
Comercial). Este documento es la **fuente del alcance** de las tareas 111 a 122
del ciclo automático: cada prompt de `prompts/` remite acá en vez de repetir la
receta.

## 1. Qué se pide

Desde el tag `v1.0.0` el diseño de los listados y de los formularios cambió, pero
solo en los módulos donde se fue trabajando. Hoy conviven dos generaciones de
pantalla. El pedido del dueño (20/9/2026) es llevar **todo** el panel al patrón
que ya tienen las pantallas de referencia:

| Referencia | Módulo | Qué aporta |
|---|---|---|
| Campaña, Cliente, Propiedad, Lote, Contrato, Cultivo | Campania / Comercial | listado, formulario, resumen relacionado, pasos de estado (Campaña, Contrato) |
| Orden de aplicación, Orden de trabajo | Operaciones | pasos con un permiso por transición, tabla de detalle dentro del formulario |
| Estadías en hacienda | Operaciones | franja de KPI en un listado |
| Cuadrillas | Personal | tabla de detalle con paginación dentro del formulario |

La receta escrita es `docs/diseno/guia_pantalla_panel.md` (§5.1, §6.2, §6.3 a
§6.3.5). Este plan no la reemplaza: dice **a qué pantallas** se aplica, **en qué
orden** y **cómo se comprueba**.

### 1.1. Fuera de alcance

- **Cuatro pantallas que el dueño excluyó por nombre:** `/panel/roles` (con su
  alta, edición y permisos), `/panel/organizacion`, `/panel/bitacora`,
  `/panel/dashboard`.
- **Las páginas de detalle (`show`)**: Orden de aplicación, Orden de trabajo,
  Trabajo, Cuadrilla, Reparto, Planilla, Rendición, Devengos, Desempeño de
  persona. Falta decidir si el arquetipo Detalle queda para objetos
  transaccionales especiales o se adopta en todo el sistema. No se tocan, ni se
  crean nuevas.
- **Lo que no es ni listado ni formulario de un objeto:** login, selección de
  rol, perfil, configuración, búsqueda global, mapa de propiedad.
- **El portal del cliente** (`Portal/`): otro público, otro layout y el scoping
  de la invariante 5. Si se homogeneiza, es una decisión aparte.
- **Reglas de negocio.** Ninguna tarea de este plan cambia una máquina de
  estados, un caso de uso de escritura, una migración o un permiso. Es capa de
  presentación: Blade, CSS de página, `lang/es`, y en el controlador solo lo que
  arma datos para pintar (`resumenRelacionado()`, `PasosDeEstado::armar()`).

## 2. Diagnóstico (medido el 20/9/2026 sobre `develop` en `903c4608`)

`✓` usa la pieza · `·` no la usa. «nativo» = cuántos `confirm()` de navegador
quedan; «viejo» = usa el `<form class="ag-filtros">` anterior.

### 2.1. Listados

| Pantalla | filter-panel | table-search | index-table | row-actions | confirm-modal | nativo | viejo |
|---|---|---|---|---|---|---|---|
| Comercial/cultivos | ✓ | ✓ | · | ✓ | · | 0 | no |
| Comercial/facturas | · | · | · | · | · | 0 | no |
| Comercial/reportes-comerciales | · | · | · | · | · | 0 | no |
| Distribucion/versiones-apk | · | · | · | · | · | 0 | no |
| Finanzas/anticipos | · | · | · | · | · | 1 | sí |
| Finanzas/combustible | · | · | · | · | · | 1 | sí |
| Finanzas/gastos | · | · | · | · | · | 1 | sí |
| Finanzas/planillas | · | · | · | · | · | 0 | no |
| Finanzas/rendiciones | · | · | · | · | · | 0 | sí |
| Inventario/repuestos | · | ✓ | · | · | · | 1 | no |
| Inventario/stock | · | ✓ | · | · | · | 0 | sí |
| Mantenimiento/baterias | · | ✓ | · | · | · | 1 | sí |
| Mantenimiento/fichas-dron | · | ✓ | · | · | · | 1 | no |
| Mantenimiento/generadores | · | ✓ | · | · | · | 1 | sí |
| Mantenimiento/ordenes | · | · | · | · | · | 0 | sí |
| Mantenimiento/planes | · | · | · | · | · | 1 | no |
| Mantenimiento/vehiculos | · | ✓ | · | · | · | 1 | sí |
| Operaciones/alertas | · | · | · | · | · | 0 | sí |
| Operaciones/drones | · | ✓ | · | · | · | 1 | no |
| Operaciones/pausas | · | · | · | · | · | 0 | sí |
| Operaciones/reparto-cuadrillas | · | · | · | · | · | 0 | no |
| Operaciones/reportes-tecnicos | · | · | · | · | · | 0 | sí |
| Operaciones/sesiones (validación) | · | · | · | · | · | — | — |
| Personal/bases | · | ✓ | · | · | · | 1 | no |
| Personal/personas | · | ✓ | · | · | · | 1 | no |
| Seguridad/usuarios | ✓ | ✓ | · | ✓ | · | 1 | no |
| Seguridad/dispositivos | · | · | · | · | · | 0 | no |

Ya conformes (no se tocan salvo el barrido final): Campania/campanias,
Comercial/{clientes, contratos, lotes, propiedades}, Operaciones/{estadias,
ordenes, ordenes-trabajo}, Personal/cuadrillas.

### 2.2. Formularios

| Formulario | form-layout | summary-card (edición) | step-arrow | flash en el form | controles crudos |
|---|---|---|---|---|---|
| Comercial/cultivos | ✓ | · | no aplica | ✓ | 0 |
| Comercial/facturas (solo alta) | · | no aplica | — | · | 0 |
| Finanzas/anticipos (solo alta) | · | no aplica | — | · | 1 |
| Finanzas/combustible (solo alta) | · | no aplica | — | · | 2 |
| Finanzas/gastos (solo alta) | · | no aplica | — | · | 4 |
| Finanzas/rendiciones (solo alta) | · | no aplica | — | · | 0 |
| Inventario/repuestos | · | · | no aplica | ✓ | 0 |
| Inventario/stock (solo alta) | · | no aplica | — | · | 0 |
| Mantenimiento/baterias | · | · | no aplica | ✓ | 0 |
| Mantenimiento/fichas-dron | · | · | no aplica | ✓ | 3 |
| Mantenimiento/generadores | · | · | no aplica | ✓ | 0 |
| Mantenimiento/ordenes (create + edit separados) | · | · | **falta** | solo edit | 1 |
| Mantenimiento/planes | · | · | no aplica | ✓ | 0 |
| Mantenimiento/vehiculos | · | · | no aplica | ✓ | 1 |
| Operaciones/drones | · | · | no aplica | ✓ | 0 |
| Operaciones/pausas (solo alta) | · | no aplica | — | · | 1 |
| Operaciones/trabajos (solo edición) | · | · | **falta** | ✓ | 0 |
| Personal/bases | · | · | no aplica | ✓ | 0 |
| Personal/personas | · | · | no aplica | ✓ | 1 |
| Seguridad/usuarios | · | · | no aplica | ✓ | 3 |

«Controles crudos» cuenta `<input>`, `<select>` y `<textarea>` escritos a mano en
vez del átomo del catálogo. Un `type="hidden"` es legítimo; el resto se revisa
uno por uno.

### 2.3. Objetos con máquina de estados

Solo llevan pasos (`step-arrow`) los objetos cuya máquina existe en
`Dominio/MaquinaEstados/Transiciones*.php`. Un catálogo con `activo` o con un
`estado` que es un simple dato (en servicio / en taller) **no** la tiene y no se
le inventa una.

| Objeto | Máquina | ¿Tiene ficha de edición? | Pasos |
|---|---|---|---|
| Campaña, Contrato, Orden de aplicación, Estadía, Cuadrilla | sí | sí | ya hechos |
| Orden de mantenimiento | `TransicionesOrdenMantenimiento` | sí | tarea 116 |
| Trabajo | `TransicionesTrabajo` | sí (`trabajos/edit`) | tarea 114 |
| Planilla, Rendición | sí | no (listado + detalle) | solo badge y acciones de fila con el tono del estado — tarea 119 |
| Alerta, Versión de APK | sí | no | ídem — tareas 113 y 121 |
| Sesión, Acta | sí | no | fuera: se validan desde pantallas propias |

## 3. Reglas que fijó el dueño para esta ronda

1. **Color de las acciones de fila.** Tres colores son fijos en todo el panel:
   **Ver = `info-outline`**, **Editar = `warning-outline`**, **Eliminar =
   `danger-outline`**. Toda otra acción es un cambio de estado y lleva **el tono
   del estado al que lleva** (`<tono>-outline`), el mismo de su badge y de su
   modal — el mapa vive una sola vez en `TONO_POR_ESTADO`.
2. **Ninguna confirmación nativa.** `confirm()` de navegador se reemplaza por
   `molecules/confirm-modal`; un aviso que no confirma nada, por
   `molecules/info-modal`. Forms y modales van **fuera** de `row-actions` (el
   organism repite su slot dos veces).
3. **El formulario reutiliza los campos del catálogo**: `atoms/input` (también
   como grupo con prefijo/sufijo), `select` (con búsqueda), `date`, `datetime`,
   `time-range`, `textarea`, `switch`, `checkbox(-group)`, `radio-group`,
   `molecules/color-swatch-field`, `molecules/file-field`. Secciones con
   `molecules/form-section`; tablas de detalle con `molecules/index-table` +
   `molecules/pagination`, como Cuadrillas.
4. **Edición = resumen relacionado.** El aside muestra una tarjeta por cada
   objeto de relación más cercana (los que cuelgan de este y, si ayuda, el padre).
   Entre módulos, los datos llegan por `Contratos/` del módulo dueño — nunca con
   SQL ni modelos ajenos. Si el contrato de lectura todavía no existe, se crea en
   el módulo dueño dentro de la misma tarea (interfaz + DTO + implementación +
   binding); no se deja el aside con datos estáticos.
5. **Objeto con máquina de estados que todavía no está operativo.** Mientras el
   objeto no haya llegado a su estado operativo (abierta, activa, vigente,
   aprobada, en ejecución, en marcha…), el aside **no** muestra tarjetas vacías:
   muestra una sola sección informativa (`molecules/empty-state`) que dice que
   todavía no hay nada que resumir y qué paso hay que dar. Referencia viva:
   `campania::pages.campanias._formulario` (`aside_no_abierta_*`).
6. **KPI en el listado: a criterio.** La franja de hasta cuatro `stat-card` de
   Estadías se replica solo donde un resumen ayuda a decidir (montos por estado
   en Finanzas, stock bajo mínimo, órdenes de mantenimiento abiertas). Las cifras
   las resuelve el caso de uso del listado con el mismo filtro que la tabla.
   Ante la duda, no se agrega.

## 4. Receta común de cada tarea

Cada tarea del ciclo cubre un grupo de pantallas de **un** módulo, en su propia
rama, y hace esto en orden:

1. **Leer antes de escribir:** `docs/diseno/guia_pantalla_panel.md` §5.1, §6.2 y
   §6.3–§6.3.5; la pantalla de referencia más parecida (catálogo simple →
   `cultivos` + `clientes`; con máquina → `contratos` o `ordenes`; con KPI →
   `estadias`); y el `.blade.php` real de cada componente que se use (no asumir
   props).
2. **Listado:** `page-header` → flash → (KPI si corresponde) → `ag-table-toolbar`
   con `filter-panel` + `table-search` → `index-table` con columna de índice y
   columna de acciones de ancho `var(--ag-row-actions-width)` → `row-actions` →
   `pagination`. Vacíos con `empty-state` en sus dos variantes y sin botón.
   Badges con el tono de `TONO_POR_ESTADO`. Catálogos sin columna ni filtro de
   activo/inactivo.
3. **Formulario:** `_formulario` compartido por alta y edición; `form-layout`
   (main + aside), `form-section` con contador, `form-actions-bar`, flash pintado
   en el propio formulario, `store()`/`update()` vuelven a `edit()`. Aside solo
   en edición (§3.4 y §3.5 de este plan). Pasos bajo la cabecera si hay máquina.
4. **CSS de página:** se borra lo que el catálogo ya resuelve (tabla, filtros,
   layout propios). Cero color o medida literal (invariante 11).
5. **Copy:** todo en `lang/es/<modulo>.php`, tuteo neutro, pasado a los
   componentes enlazado (`:label="__('x')"`).
6. **Compuerta:** sacar las pantallas terminadas de
   `docs/diseno/panel_homogeneo_pendientes.txt` y correr `./bin/verify`
   (`PanelHomogeneoTest` deja de perdonarlas). `ArquitecturaModulosTest` se corre
   temprano si se tocó un `Contratos/`.
7. **Prueba en navegador** contra el compose (`localhost:8000`, `miguelo` /
   `0000`): Playwright headless, cada pantalla tocada en tema claro y oscuro,
   capturas a **ruta absoluta** `runs/NN-capturas/`; se comprueba que
   `.ag-panel__content` no desborde, que cada modal abra (también desde el menú
   «⋮») y que guardar vuelva a la ficha con su aviso. Los datos demo no se borran:
   lo que se cree para probar, queda.

## 5. Tareas del ciclo

Una tarea = una rama = un PR contra `develop`, integrado por `auto-merge` con CI
en verde. Corren en fila (`bin/ciclo`), cada una desde un `develop` al día.

| Id | Rama | Alcance | Etapas | Crítica | Modelo |
|---|---|---|---|---|---|
| 111 | `feature/compuerta-panel-homogeneo` | `PanelHomogeneoTest` + lista de pendientes + variantes de botón que falten + «Ver» en `info-outline` en las referencias | 2 | no | `sonnet` |
| 112 | `feature/panel-personal` | Bases, Personas | 3 | no | `sonnet` |
| 113 | `feature/panel-drones-pausas` | Drones, Pausas, Alertas | 3 | no | `sonnet` |
| 114 | `feature/panel-trabajos-reparto` | Trabajo (edición con pasos), Reparto de cuadrillas (listado), Reportes técnicos, Validación de sesiones (solo listado) | 4 | **sí** | `opus` |
| 115 | `feature/panel-equipos-mantenimiento` | Baterías, Generadores, Vehículos, Fichas de dron | 4 | no | `sonnet` |
| 116 | `feature/panel-ordenes-mantenimiento` | Órdenes de mantenimiento (pasos + aviso de estado), Planes | 4 | no | `opus` |
| 117 | `feature/panel-inventario` | Repuestos, Stock | 3 | no | `sonnet` |
| 118 | `feature/panel-registros-finanzas` | Anticipos, Combustible, Gastos | 3 | no | `sonnet` |
| 119 | `feature/panel-liquidacion-finanzas` | Planillas y Rendiciones (listados + alta de rendición); Devengos es un detalle y queda afuera | 3 | **sí** | `opus` |
| 120 | `feature/panel-comercial-restante` | Cultivos (tabla + resumen), Facturas, Reportes comerciales | 3 | no | `sonnet` |
| 121 | `feature/panel-usuarios-dispositivos` | Usuarios, Dispositivos, Versiones de APK | 3 | no | `sonnet` |
| 122 | `feature/cierre-panel-homogeneo` | Lista de pendientes vacía, CSS muerto, guía y estado del proyecto al día | 2 | no | `sonnet` |

**Por qué ese orden.** La 111 va primero porque convierte «quedó igual que
Comercial» en un comando con exit code: sin ella cada tarea se cerraría por
criterio propio. Después, de lo más simple a lo más delicado: catálogos sin
máquina (112, 113), la primera pantalla con pasos (114), Mantenimiento e
Inventario, y al final Finanzas —que muestra dinero— y Seguridad. La 122 no
depende de decisiones: barre lo que quedó.

**Modelo por tarea.** El ciclo ya reparte por fase (`sonnet` para implementar,
verificar, corregir y planificar; `haiku` para arreglar un CI en rojo; Fable en
ninguna). Encima de eso, `modelo=opus` en la implementación de las tres tareas
donde hay más criterio que receta: la 114 y la 116 escriben un presentador de
pasos sobre una máquina de estados, y la 119 pinta dinero. Las demás aplican la
receta sobre catálogos y la compuerta de la 111 las frena si se desvían. Ninguna
baja a `haiku`: una pantalla «reportada como hecha» que no lo está es justo el
fallo que un modelo más chico agrava.

**Críticas.** La 114 toca la pantalla de validación de sesiones (invariante 4) y
la ficha de un Trabajo; la 119 pinta montos de planilla y devengo. Ninguna
cambia su lógica, pero llevan verificación independiente y quedan anotadas en
`runs/revision-pendiente.txt` para la revisión posterior sobre `develop`.

## 6. Cómo se ejecuta

```
bin/ciclo --estado      # nada corriendo, cola 111…122
bin/ciclo --fondo       # arranca; una tarea a la vez, cada una en su rama
bin/ciclo --detener     # parada ordenada
```

Al cerrar la 122 la cola queda vacía y la sesión de planificación, con el plan
de sprints agotado, escribe `runs/DETENER` — es el final esperado.

Una tarea `BLOQUEADA` no corta la fila: deja su pregunta en `runs/NN.md` y el
ciclo sigue con la próxima, porque ninguna depende de otra salvo de la 111.

## 7. Seguimiento

| Id | Estado | PR |
|---|---|---|
| 111 | **hecha** (20/9/2026) | #254 |
| 112 | **hecha** (20/9/2026) | |
| 113 | **hecha** (20/9/2026) | |
| 114 | **hecha** (20/9/2026) | |
| 115 | **hecha** (20/9/2026) | |
| 116 | **hecha** (20/9/2026) | |
| 117 | pendiente | |
| 118 | pendiente | |
| 119 | pendiente | |
| 120 | pendiente | |
| 121 | pendiente | |
| 122 | pendiente | |

Cada tarea actualiza su fila al cerrar con `OK`.
