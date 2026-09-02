<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/arquetipo-formulario etapas=4 -->

# Tarea 31 — TE: el arquetipo formulario del panel, y la compuerta visual que lo sostiene

## Por qué esta tarea, y por qué antes del Sprint 7

`plan_sprints.md` Sprint 7 son siete HU (HU-22 a HU-27 y HU-45) y las siete son
**la misma pantalla con otros campos**: un ABM sobre tablas que ya existen,
migradas y auditadas. Sprint 8 en adelante suma otras tantas. En total quedan
25 pantallas por construir — `SecMenuSeeder` siembra 7 módulos y 33 ítems de
menú, y solo 8 tienen ruta.

Hoy no hay con qué construirlas. El catálogo tiene lo que necesitó el
dashboard (tarjetas de indicador, gráficos, tiras de alerta) pero **no tiene el
arquetipo formulario**: `molecules/form-section` es un `<fieldset>` sin chrome
ni grid, y no existen la cabecera de página, las pestañas, la barra de acciones,
ni las tarjetas del panel lateral. La prueba está a la vista en
`/panel/organizacion`, la única pantalla de formulario que existe: una columna
de 600px con la mitad derecha de la pantalla vacía.

Si las siete HU de Sprint 7 arrancan sin esto, cada una va a inventar su propio
formulario y vamos a terminar con siete formularios distintos — que es
exactamente lo que el catálogo Atomic Design existe para evitar.

**Esta tarea construye el arquetipo una vez para que las 25 pantallas
siguientes sean composición y no diseño.** No es una HU: no entrega
funcionalidad de negocio. Es la TE que las desbloquea a todas.

## La referencia de composición ya está aprobada

`docs/diseno/guia_pantalla_panel.md` §6.3 tiene la anatomía completa del
arquetipo, con las seis reglas y la tabla de las siete piezas que faltan. Sale
del canvas de Claude Design "Registro de la compañía", que el usuario aprobó
como formato. **Leé esa sección entera antes de escribir una línea** — es la
especificación de esta tarea, no una sugerencia.

Y leé también §7 de la misma guía, que es la parte que más fácil se rompe:
**del canvas se toma estructura y medidas, nunca la paleta ni las fuentes.**
El canvas trae `#f4f3ec`, `#1c6b2c` y "Public Sans" propios; el panel usa sus
tokens `--ag-*` derivados de los logos oficiales. La tabla de §7 tiene la
traducción hex→token y px→escala hecha. Un solo hex literal en el diff es un
hallazgo del verificador (CLAUDE.md invariante 11).

## Qué hay que entregar

### 1. Las siete piezas del catálogo (§6.3 de la guía)

En `resources/views/components/` + su CSS en `resources/css/components/`, con
el comentario de cabecera que dice qué es, qué NO es y por qué está en ese
nivel — la convención vigente de todo el catálogo.

| Pieza | Nivel | Qué |
|---|---|---|
| `form-section` | molecule | **Evolución, no alta.** Pasa de `<fieldset>` desnudo a tarjeta: superficie `--ag-color-surface-card`, borde `--ag-color-border-card`, radio `--ag-radius-lg`, header separado por borde que **es** `molecules/section-head` (barra + rótulo mono + contador de campos), y body con `grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr))`. Un campo ancho declara `grid-column: 1 / -1` |
| `page-header` | organism | h1 + bajada + slot de acciones a la derecha. Reemplaza el markup que hoy repiten `ag-dash__header` y `ag-organizacion__intro` |
| `tabs` | molecule | El CSS ya existe (`tabs.css`) y el dashboard lo usa a mano con `data-bs-toggle`. Falta el componente |
| `form-actions-bar` | organism | Barra pegajosa al pie con estado de guardado en texto y las mismas acciones de la cabecera |
| `summary-card` | molecule | Lista etiqueta→valor + acción al pie (la tarjeta "Suscripción" del canvas) |
| `progress-meter` | molecule | Porcentaje + barra + checklist de ítems cumplidos/faltantes (la tarjeta "Perfil completo") |
| `file-field` | molecule | Preview + nombre/peso + acciones reemplazar/quitar. Hoy es markup suelto en `organizacion.css` |

**Respetá §2 y §3 de la guía**: el nivel se decide por qué compone, no por
tamaño; todo componente fusiona `$attributes` en su raíz; ninguno nombra una
ruta, un modelo Eloquent ni un módulo de dominio — recibe datos ya resueltos.

Actualizá la tabla del catálogo en `docs/diseno/sistema_diseno_panel.md` §3 con
las altas y el cambio de `form-section`. El `diff` del checklist de la guía §8
tiene que dar vacío al cerrar.

### 2. `/panel/organizacion` reconstruida sobre el arquetipo

Es el caso de prueba: la pantalla que hoy muestra el problema es la que
demuestra que el arquetipo funciona. Dos columnas, secciones como tarjetas,
aside pegajoso con `progress-meter` y `summary-card`, `form-actions-bar` al
pie. Sigue siendo el mockup sin persistencia que ya es — **no le agregues
guardado real**, eso es multi-tenant y no existe (ADR 0002, extensión).

Al terminar, `resources/css/pages/organizacion.css` no puede tener un solo
`px` inventado: hoy tiene `max-width: 600px`, `60px` y `minmax(250px, …)`.
Verificalo con el grep del checklist de la guía §8.

### 3. La compuerta visual — esto es lo que de verdad acelera

`tests/Visual/` existe desde la tarea 07: Playwright, helpers de login y rol,
snapshots de `login`, `seleccionar-rol` y `dashboard` en claro y oscuro. **Y no
lo corre nadie.** `bin/verify` tiene dos etapas (`composer verify` y
`npm run build`); el gate visual no está en ninguna. Se construyó el arnés y
quedó desconectado.

Hacé dos cosas:

1. **Agregá `tests/Visual/organizacion.spec.ts`** con su par de snapshots
   claro/oscuro, siguiendo el patrón de los tres specs que ya existen (login
   real, no bypass de sesión; `esperarFuentes` antes de capturar).
2. **Sumá una etapa a `bin/verify`** que corra `npx playwright test`, con el
   mismo formato de `etapa "…"` que las dos existentes.

**No toques `.github/workflows/`.** Es zona congelada, y además los snapshots
son `-darwin`: en el runner Linux de CI fallarían todos por diferencia de
plataforma, no por regresión. El gate corre local, que es donde corre el ciclo
antes de commitear. Si más adelante hace falta en CI, es una tarea propia con
`descongela=github` y snapshots `-linux`.

## Criterio de aceptación

- `./bin/verify` = 0, **con la etapa de Playwright adentro y en verde**.
- Las 7 piezas existen, están en `sistema_diseno_panel.md` §3, y el `diff` del
  checklist de la guía §8 da vacío.
- `/panel/organizacion` está reconstruida sobre ellas y su spec visual pasa en
  claro y oscuro.
- `grep -nE '#[0-9a-fA-F]{3,8}|[0-9]+px' resources/css/pages/organizacion.css`
  no devuelve nada fuera del hairline `1px solid`, los breakpoints 768/1200 y
  los comentarios.
- `document.documentElement.scrollHeight - window.innerHeight` = 0 en
  `/panel/organizacion` (la regla de scroll de la guía §4.1 — es el bug que
  disparó todo esto, no lo reintroduzcas con un `position: absolute` sin
  ancestro posicionado).

## Puede tocar

`resources/views/components/**`, `resources/css/components/**`,
`resources/css/pages/organizacion.css`,
`app/Dominios/Seguridad/Infraestructura/Http/Views/pages/organizacion/**`,
`lang/es/seguridad.php`, `docs/diseno/sistema_diseno_panel.md`,
`tests/Visual/**`, `bin/verify`.

Fuera de alcance, a propósito: las otras siete pantallas del panel (se migran
al arquetipo cuando les toque su HU, no acá), el guardado real de organización,
y `.github/workflows/`.

## Lo que sigue

Con el arquetipo en `develop`, Sprint 7 se vuelve mecánico: HU-22 (clientes),
HU-23 (contratos), HU-24 (campos y lotes), HU-25 (órdenes), HU-26 (personas y
bases), HU-27 (drones) y HU-45 (usuarios). Cada una es una HU entera con su PR,
compuesta sobre estas piezas, y cada una suma su spec visual al gate — que es
lo que hace que la pantalla número 25 se vea como la número 1 sin que nadie las
compare a ojo.

Anotá eso en `docs/gestion/cola_tareas.md` al cerrar, para que la planificación
de la vuelta siguiente no tenga que redescubrirlo.
