# Especificaciones de sistema de diseño — versión agnóstica de stack

> **Relación con `docs/diseno/sistema_diseno_panel.md` (no son el mismo documento, no se elimina ninguno de los dos):** este archivo es material de referencia GENÉRICO, importado de otro proyecto ("Studio"/INCOS) — describe patrones y valores de EJEMPLO, muchos de los cuales `agrocom-api` no implementa igual (paleta propia derivada de los logos de Agrocom, no la de ejemplo de acá; claves i18n en español, no en inglés como pide §10 de este documento; sin los 8 acentos/12 categóricos de §3.2-3.3, sin el patrón de listado de §6 ni el contrato de tabla responsive de §7 — ninguno de los dos existe todavía en el panel). `sistema_diseno_panel.md` es el catálogo REAL y vigente de lo que está construido en este repo (nombres de archivo, tokens y valores exactos) — ante cualquier conflicto entre los dos, ese es el que manda. Usá este documento como inspiración/checklist para funcionalidad que todavía no se construyó (tablas responsive, KpiCard, listados genéricos), no como descripción de lo ya implementado.
>
> Extraído del frontend "Studio" (proyecto INCOS). Este documento describe **principios, tokens con valores exactos y contratos de comportamiento**, sin depender de ninguna librería de componentes concreta, para poder replicarse en Laravel (Blade / Livewire / Alpine.js, o el stack que tu agente elija). Úsalo como material de referencia para un agente que va a construir la interfaz desde cero.

---

## 0. Cómo usar este documento

Cada sección tiene: (a) el **principio**, (b) los **valores literales** de referencia (puedes ajustarlos a tu marca, pero mantén la estructura), y (c) una **traducción a Laravel** de cómo aplicarlo. No es un port de componentes — es la especificación de las reglas y los números detrás de ellos.

---

## 1. Principios rectores (no negociables)

1. **Tokens al origen.** Ningún color, tamaño, sombra o duración se escribe suelto dentro de un componente. Todo sale de variables (custom properties CSS) generadas desde un único archivo/config fuente de verdad.
2. **Una responsabilidad por archivo.** Separar siempre: render (vista), lógica de estado (componente Livewire / controller), acceso a datos (repositorio/servicio), transformación pura (adaptador/mapper), estilos (CSS del componente).
3. **Atomic Design.** Átomos → moléculas → organismos → templates → páginas. Ningún componente "grande" sin descomponer.
4. **White-label desde el origen.** Colores de marca, tipografía, nombre, logo viven en un único archivo de configuración de marca — nunca hardcodeados dentro de un componente. Duplicar una marca/tenant debe ser "un archivo nuevo", no una búsqueda-y-reemplazo.
5. **Mock-first con contrato real.** Si se construye UI antes que el backend, los datos de prueba deben tener **exactamente la forma** de lo que el backend real devolverá. Conectar el backend después debe ser cambiar solo la fuente de datos, nunca tocar la vista.
6. **Motion motivado.** Cero animación decorativa. Cada patrón de movimiento tiene una razón documentada (jerarquía, feedback de estado, continuidad espacial). Ver §8.
7. **Container queries, no media queries**, para el reflow interno de componentes. Una tabla/card/toolbar reacciona al ancho de **su contenedor** (que cambia si un sidebar colapsa), no al ancho del viewport completo.
8. **Prohibido inventar breakpoints sueltos.** Una única escala de breakpoints es la fuente de verdad (§2.6). El CSS no puede leer `var()` dentro de la condición de una `@media`/`@container`, así que el número se repite literal — pero siempre debe pertenecer a esa escala, nunca a un valor inventado ad-hoc.

---

## 2. Tokens de diseño (valores de referencia)

### 2.1 Tipografía

| Token | Valor |
|---|---|
| size.xs | 11px |
| size.sm | 13px |
| size.base | 14px |
| size.md | 16px |
| size.lg | 18px |
| size.xl | 22px |
| size.xxl | 28px |
| weight.regular | 400 |
| weight.medium | 500 |
| weight.semibold | 600 |
| weight.bold | 700 |
| weight.extrabold | 800 |
| lineHeight.tight | 1.2 |
| lineHeight.normal | 1.5 |

La familia tipográfica **no es fija**: sale de la configuración de marca (`brand.fontFamily`), nunca hardcodeada en un componente.

### 2.2 Espaciado (escala base 4px)

| Token | Valor |
|---|---|
| xxs | 2px |
| xs | 4px |
| sm | 8px |
| md | 12px |
| base | 16px |
| lg | 20px |
| xl | 24px |
| xxl | 32px |
| xxxl | 48px |

### 2.3 Radios de borde

| Token | Valor |
|---|---|
| xs | 4px |
| sm | 6px |
| base | 8px |
| md | 10px |
| lg | 14px |
| xl | 16px |
| pill | 999px |

### 2.4 Sombras / elevación (distintas por modo claro y oscuro)

```css
/* Modo claro */
--shadow-sm:   0 1px 2px rgba(0, 72, 48, 0.06);
--shadow-base: 0 2px 8px rgba(0, 72, 48, 0.09), 0 1px 2px rgba(0, 72, 48, 0.05);
--shadow-md:   0 10px 28px rgba(0, 72, 48, 0.14), 0 3px 8px rgba(0, 72, 48, 0.08);
--shadow-lg:   0 16px 44px rgba(0, 72, 48, 0.18);

/* Modo oscuro */
--shadow-sm:   0 1px 2px rgba(0, 0, 0, 0.4);
--shadow-base: 0 2px 8px rgba(0, 0, 0, 0.4), 0 1px 2px rgba(0, 0, 0, 0.3);
--shadow-md:   0 8px 24px rgba(0, 0, 0, 0.5), 0 2px 6px rgba(0, 0, 0, 0.3);
--shadow-lg:   0 12px 40px rgba(0, 0, 0, 0.6);
```

Nota: las sombras del modo claro usan el **tinte de la marca** (verde institucional, `rgba(0,72,48,…)`) en vez de negro puro — más coherente visualmente que una sombra gris genérica. Ajusta el tinte al color primario de tu marca.

### 2.5 Motion — duraciones y curvas

```css
--ease-out:   cubic-bezier(0.23, 1, 0.32, 1);
--ease-inout: cubic-bezier(0.77, 0, 0.175, 1);
--ease-drawer:cubic-bezier(0.32, 0.72, 0, 1);

--dur-press:   140ms;
--dur-hover:   160ms;
--dur-pop:     200ms;
--dur-swapOut: 160ms;
--dur-swapIn:  260ms;
--dur-drawer:  320ms;
--dur-bell:    350ms;
```

Regla: **ningún componente define su propia duración o curva literal** — todos consumen estos tokens.

### 2.6 Breakpoints (escala única, fuente de verdad)

| Token | Valor |
|---|---|
| xs | 480px |
| sm | 640px |
| md | 768px |
| lg | 1024px |
| xl | 1280px |

### 2.7 z-index

| Token | Valor |
|---|---|
| base | 1 |
| dropdown | 1050 |
| sticky | 100 |
| sidebar | 1100 |
| backdrop | 1090 |
| modal | 1200 |
| tooltip | 1300 |

### 2.8 Tamaños de control

| Token | Valor |
|---|---|
| control | 38px (alto estándar de inputs/botones) |
| touchTarget | 44px (mínimo de accesibilidad WCAG 2.5.5 para áreas táctiles) |

---

## 3. Paleta de color y sistema semántico

**Nunca uses colores "primitivos" sueltos en un componente.** La paleta se organiza en capas semánticas por modo (claro/oscuro), derivadas de una configuración de marca:

- `surface` — bgPage, bgPage2, chrome (fondo de barras/paneles), chromeInputBg, base, alt, hover.
- `text` — strong, secondary, muted, soft.
- `border` — base, strong, divider.
- `semantic` — success, warning, danger, info.
- `pill` — pares fondo/texto por tono (warn/ok/err/info/neutral), para badges.
- `nav`, `tooltip` — capas específicas de esos componentes.
- `misc` — onAccent (texto sobre un color de acento), onAccentSoft, backdrop.

### 3.1 Valores de referencia (ejemplo real, ajustable a tu marca)

| Token | Claro | Oscuro |
|---|---|---|
| surface.bgPage | `#e6ece8` | `#07140e` |
| surface.bgPage2 | `#d8e3dc` | `#0a1d14` |
| surface.chrome | `#fafcfb` | `#0c1812` |
| text.strong | `#0a1f15` | `#e8f0ea` |
| border.base | `#c8d6cc` | `#1d3528` |
| semantic.success | `#16a34a` | `#34d399` |
| semantic.warning | `#8a6308` | `#fbbf24` |
| semantic.danger | `#c0282a` | `#f87171` |
| semantic.info | `#3d6dd0` | `#60a5fa` |

**Regla de contraste:** `misc.onAccent` en modo claro suele ser blanco puro; en modo oscuro, en vez de blanco, se recalcula por modo (a veces conviene usar el `text.strong` del propio tema oscuro en vez de blanco, para no perder contraste AA contra acentos claros). No fijes "blanco" a ciegas — verifica contraste por modo.

### 3.2 Ocho colores de acento (para estados/etiquetas puntuales)

| Nombre | Claro | Oscuro |
|---|---|---|
| red | `#c0282a` | `#e8625f` |
| gold | `#8a6308` | `#e0a82e` |
| sky | `#3d6dd0` | `#6f9af0` |
| teal | `#0a757f` | `#33c2cf` |
| violet | `#7a4cb8` | `#b58ce8` |
| orange | `#b04f0d` | `#f0905a` |
| pink | `#b03a7a` | `#e88cc0` |
| lime | `#5f7c0f` | `#a8d04c` |

Se exponen como `--accent-red`, `--accent-gold`, etc.

### 3.3 Doce colores categóricos (para diferenciar entidades: carreras, categorías, series de gráficos)

Se derivan de los 8 acentos + la rampa de marca (§3.4) + 2 tonos extra de la marca con contraste garantizado por modo (no hardcodear más colores "sueltos" que estos dos). Se exponen como `--cat-0` … `--cat-11`.

**Regla de asignación:** cuando una entidad tiene un código conocido de antemano (ej. "carrera A" siempre es azul), fija el índice por código en una tabla de mapeo explícita para que el color sea consistente entre pantallas. Si no hay mapeo, usa un fallback determinista: `id % 12`. **Siempre referencia la variable CSS (`var(--cat-N)`)**, nunca el hex resuelto, para que el color cambie solo automáticamente al cambiar de tema.

### 3.4 Rampa de marca (10 pasos, 50→900)

El paso `500` es el color primario (`colorPrimary`). Ejemplo (verde institucional):

```
Claro: 50 #e3f2ea · 100 #bfe0cf · 200 #8ec9ad · 300 #56ad86 · 400 #1f9163
       500 #007848 (primario) · 600 #006048 · 700 #004830 · 800 #003a26 · 900 #002b1c
Oscuro: 50 #0a2218 · 100 #103224 · 200 #16513a · 300 #1f9163 · 400 #2eae79
        500 #3fc48d · 600 #2eae79 · 700 #1f9163 · 800 #16513a · 900 #103224
```

### 3.5 Mapeo a variables CSS runtime

Todo el paso de "aplicar tema" es escribir custom properties en `:root` a partir de la paleta activa:

```js
Object.entries(accents).forEach(([k, v]) => root.style.setProperty(`--accent-${k}`, v));
categorical.forEach((v, i) => root.style.setProperty(`--cat-${i}`, v));
root.setAttribute('data-theme', mode); // 'light' | 'dark'
```

Esto es JS vanilla — funciona igual con Alpine.js, Livewire o cualquier motor de plantillas.

---

## 4. Modo claro/oscuro sin parpadeo (flash) al cambiar de tema

**El problema:** si al cambiar de tema se recalculan estilos de forma asíncrona o se reinyecta una hoja de estilos completa, se ve un "flash" — porque las transiciones normales de hover/focus animan también el cambio de color global, de forma descoordinada entre elementos.

### Mecanismo de 3 capas (agnóstico de framework)

**Capa 1 — Todo por variable, nunca por clase recompilada.** Los componentes consumen `var(--color-x)`. Cambiar de tema = reescribir el *valor* de esas variables en `:root`, no regenerar CSS ni reinyectar una hoja de estilos.

**Capa 2 — Aplicar el cambio de forma síncrona, antes del primer paint.** El toggle de tema debe escribir `data-theme` y todas las custom properties en el mismo ciclo síncrono, antes de que el navegador pinte el frame. En un stack Blade/Alpine esto se logra con:
- Un `<script>` inline en el `<head>` (antes de cualquier CSS pesado) que fija el tema guardado en `localStorage` **antes** de que se pinte el body — evita el flash de tema incorrecto en la carga inicial.
- Para el toggle en caliente, ejecutar la escritura de variables de forma síncrona (no dentro de un `setTimeout`/microtask asíncrono).

**Capa 3 — Anular transiciones durante el cambio.** Al togglear:
1. Marcar `<html data-theme-switching>`.
2. Escribir todas las custom properties del nuevo modo.
3. Forzar un reflow síncrono (leer `document.documentElement.offsetHeight`) para que los nuevos valores se calculen ya con `transition: none`.
4. En el siguiente frame (`requestAnimationFrame`), quitar el atributo `data-theme-switching`.

```css
html[data-theme-switching] *,
html[data-theme-switching] *::before,
html[data-theme-switching] *::after {
  transition: none !important;
}
```

Resultado: el cambio de color aparece resuelto de golpe (sin animación de "salto"), y las transiciones normales de la interfaz (hover, focus) se reactivan inmediatamente después.

**Persistencia:** guardar la preferencia en `localStorage` bajo una clave propia (ej. `app-theme-mode`), leída por el script inline temprano del `<head>`.

---

## 5. Arquitectura de carpetas y capas — traducción a Laravel

| Origen (agnóstico) | Traducción a Laravel |
|---|---|
| Tokens de diseño (fuente única) | `resources/css/tokens/*.css` (o generados desde `config/design-tokens.php` si prefieres centralizarlos en PHP) |
| Capa de theming (aplicar tokens + mecanismo anti-flash) | `resources/css/theme/*.css` + `resources/js/theme.js` (vanilla, sin dependencias) |
| Átomos / moléculas / organismos / templates | `resources/views/components/{atoms,molecules,organisms,templates}/` — Blade components anónimos o de clase, composición incremental |
| Módulo de dominio (feature) | `app/Livewire/<Modulo>/` + `resources/views/livewire/<modulo>/`, o `Http/Controllers/<Modulo>` + vistas si no usas Livewire |
| — dentro de cada módulo: componentes locales | `.../components/` (organismos que solo tienen sentido en ese módulo) |
| — configuración declarativa (ej. columnas de un listado) | `.../config/*.php` (arrays de configuración, no lógica) |
| — acceso a datos | `.../data/` → Repositorios/Servicios |
| — transformación pura | `.../model/` → Adapters/Mappers (funciones puras, sin efectos secundarios) |
| Traducciones | `lang/<idioma>/<contexto>.php` (nativo de Laravel, ya es por-namespace) |
| Configuración de marca / white-label | `config/brand.php` |
| Datos mock / seed con shape real | `database/factories/` + seeders que respetan exactamente el shape de los API Resources reales |

**Convención de nombre por responsabilidad de archivo** (aplícala siempre): vista de render (`*.blade.php`) · lógica de estado (`*Livewire.php` / `*Controller.php`) · acceso a datos (`*Repository.php` / `*Service.php`) · transformación pura (`Adapt*.php`, `Build*.php`) · estilos (`*.css` por componente, nunca estilos globales sueltos por página).

---

## 6. Patrón de "página de listado" genérica (config + slots)

El objetivo: un **template reutilizable que nunca conoce el dominio** — no importa nada específico de un módulo — y arma toda la página de listado a partir de (a) una configuración declarativa y (b) "slots" de contenido específico inyectados desde fuera.

### 6.1 Orden de secciones (fijo, no negociable)

```
Encabezado (breadcrumbs + título)
  → Fila de KPIs
  → Fila de facetas (chips filtrables)
  → Estado de error (condicional)
  → Barra de filtros / búsqueda
  → Vista de datos (tabla o tarjetas)
```

### 6.2 Shape de la configuración a replicar

- `breadcrumbs`, `título`/`subtítulo` (claves i18n + parámetros de interpolación)
- `fetch` (función/servicio que trae los datos crudos)
- `adapt` (función pura que transforma datos crudos → shape de UI)
- `getRowKey`
- `kpis[]`: `{ key, labelKey, icon, accent, select(row) }`
- `facets`: `{ accessor, idAccessor?, labelAccessor?, colorAccessor?, allLabelKey }`
- `search`: `{ fields[], placeholderKey }`
- `filters[]`: `{ key, type: 'segmented'|'select', options[], predicate }`
- `views`: qué vistas soporta (`['table','card']`)
- `columns` — factory que recibe el traductor y las acciones de fila, devuelve la definición de columnas
- `pageSize`, `pageSizeOptions`
- claves i18n para: contador de resultados, título/hint de estado vacío, título de error, "limpiar filtros", botón "nuevo"

### 6.3 Slots (dependen del feature, se inyectan desde fuera)

Acciones de cabecera · render de tarjeta (vista card) · acciones por fila · acciones masivas (bulk) · render custom de facetas · skeleton de carga custom.

### 6.4 En Laravel

Un Blade component genérico `<x-templates.list-page :config="$config">` con slots nombrados (`@slot('header-actions')`, `@slot('row-actions')`), o —si necesitas estado reactivo del lado servidor— un componente Livewire genérico `ListPage.php` que recibe el array de config por props y renderiza sub-vistas condicionalmente. El feature concreto solo aporta su propio array de config + sus propias vistas de slot; el template genérico **jamás** importa nada de un módulo específico.

---

## 7. Contrato de tabla responsive

**Problema:** con muchas columnas, la tabla rompe el layout en pantallas o **contenedores** angostos (el sidebar puede angostar el contenedor sin cambiar el viewport). Solución: colapsar columnas por ancho real de contenedor, medido en vivo — no por breakpoint de viewport fijo.

### 7.1 Reglas concretas

- Cada columna declara un **`minWidth`**.
- El ancho disponible del contenedor se mide en tiempo real (`ResizeObserver` vanilla sobre el elemento; en Alpine.js esto alimenta un `x-data` reactivo).
- Mientras la suma de `minWidth` de las columnas visibles exceda el ancho disponible, se oculta la columna ocultable **más a la derecha**. Dos columnas nunca se ocultan: la primera columna de datos y la columna de acciones.
- **Densidad** de la tabla según el ancho del contenedor (usando la escala única de §2.6, vía `@container`, nunca `@media`):
  - `>= 1024px` → `full`
  - `>= 768px` → `medium`
  - `< 768px` → `compact`
- Reservas fijas de layout a restar del ancho disponible: columna de selección `~48px`, columna de expandir `~48px`, columna de acciones variable por densidad (`140px` full / `96px` medium / `56px` compact).
- **Histéresis anti-parpadeo:** exigir `~20px` extra de margen antes de volver a *mostrar* una columna ya oculta (evita que un resize de 1px oculte/muestre en bucle), y una banda muerta de `~16px` alrededor de los umbrales de densidad.
- Las columnas ocultas **no desaparecen**: se listan en una fila expandible ("ver más campos") con un botón accesible propio (`aria-expanded`, `aria-controls`, `aria-label` con el conteo de campos ocultos).

### 7.2 Acciones de fila progresivas (data-driven)

Cada acción se declara como descriptor: `{ key, icon, labelKey, tone?, primary?: 1|2, danger?, divider?, onClick }`.

Reglas de promoción por densidad:

| Densidad | Botones sueltos visibles | Resto |
|---|---|---|
| full | acciones `primary` 1 y 2 | menú "⋮" |
| medium | solo acción `primary` 1 | menú "⋮" (incluye la 2) |
| compact | ninguno suelto | menú "⋮" con todas |

Todo esto es 100% replicable con CSS container queries + `ResizeObserver` vanilla + Alpine.js/Livewire para el estado — no depende de ninguna librería de componentes concreta.

---

## 8. Componentes de patrón reutilizables (anatomía de referencia)

Regla general para los tres: **cero hex/px hardcodeado dentro del componente** — todo por variable del sistema de tokens.

### 8.1 Píldora de estado semántico (StatusPill)

- Props: `tone: ok|warn|err|info|neutral` (default `neutral`), `icon?`, texto.
- Estructura: `<span class="pill pill--{tone}"><span class="pill__icon">…</span>{texto}</span>`.
- Estilos: `padding: 4px 10px`; `border-radius: var(--radius-pill)` (999px); `font-size: var(--font-size-xs)` (11px); `font-weight: var(--font-weight-semibold)` (600).
- Cada `tone` resuelve a un par de variables fondo/texto (`--pill-{tono}-bg` / `--pill-{tono}-fg`) que cambian por modo claro/oscuro.

### 8.2 Chip filtrable (faceta clickeable)

- Props: `label`, `count?`, `color` (variable CSS, no hex), `active: boolean`, `showDot: boolean`.
- Estructura: botón con `aria-pressed`, punto de color, label, badge de conteo.
- Estado activo: invierte a fondo sólido del color asignado + texto "sobre acento" (`--color-on-accent`).

### 8.3 Tarjeta de indicador (KpiCard)

- Props: `label`, `value`, `icon`, `accent` (variable CSS), `sub?`.
- Anatomía: dos formas circulares decorativas de fondo (grande `140px` opacidad `0.13`, pequeña `80px` opacidad `0.22`, ambas del color `accent`); riel lateral de `4px` del acento; cabecera con icono `40×40` + label; valor numérico con **animación de conteo ascendente** (formateado con separador de miles local); subtítulo opcional.
- Hover: elevación sutil (`translateY(-3px)`), el icono escala `×1.1`, el valor escala `×1.025`.

---

## 9. Motion / animación

**Principio:** una sola fuente de curvas/duraciones (§2.5) — ningún componente inventa su propia curva o tiempo. Cada patrón tiene un propósito documentado (motion motivado).

| Patrón | Comportamiento | Propósito |
|---|---|---|
| Entrada de superficie/sección | fade + traslado vertical 8px→0, `200ms`, ease-out | primera aparición al montar |
| Revelado de fila expandida (contenido residual) | **solo** fade (sin traslado), delay `260ms` | evita "doble salto" al competir con el crecimiento nativo de la fila |
| Lista/grid en cascada (stagger) | retardo entre hijos `~40ms` | jerarquía de entrada |
| Intercambio de cuerpo completo (skeleton ↔ vacío ↔ datos) | fade+traslado+escala, entrada `260ms` / salida `160ms` (asimétrico) | salir más rápido que entrar se percibe más fluido |
| Intercambio puntual (ej. toolbar de selección ↔ contador) | micro-desplazamiento vertical `4px`, sin escala, `~160ms` | feedback de cambio de estado sin distraer |

**Accesibilidad:** honrar `prefers-reduced-motion` del sistema operativo tanto a nivel CSS global (`@media (prefers-reduced-motion: reduce)` neutraliza duraciones/transforms) como en la lógica JS (verificar el media query antes de animar valores como contadores — mostrar el valor final directo si está activo).

---

## 10. Internacionalización (i18n)

- Un archivo por **idioma + contexto/namespace** (nunca un diccionario monolítico). En Laravel esto es nativo: `lang/<idioma>/<contexto>.php`.
- **Las claves (nombres de array) se escriben en inglés**, aunque el valor/texto esté en español — así el código que llama a la traducción (`__('enrollments.kpi.total')`) queda estable si cambia el idioma de trabajo del equipo.
- Interpolación de variables por marcador (`:var` en Laravel, nativo — no reinventar el mecanismo).
- Fallback: si una clave no resuelve, mostrar la clave misma como texto — ayuda a detectar traducciones faltantes en desarrollo.

---

## 11. Checklist de gobernanza (aplicar siempre, en cada pantalla nueva)

- [ ] ¿Todo color/tamaño/sombra/duración sale de una variable del sistema de tokens? (cero hex/px sueltos en componentes)
- [ ] ¿El componente nuevo está en el nivel correcto de Atomic Design y no mezcla render/lógica/datos en un mismo archivo?
- [ ] ¿El listado/tabla nuevo declara `minWidth` por columna y usa el contrato de densidad (§7), en vez de un responsive ad-hoc?
- [ ] ¿El reflow interno usa container queries (no media queries) sobre el contenedor real?
- [ ] ¿Cualquier breakpoint literal en CSS pertenece a la escala única (§2.6)?
- [ ] ¿Toda animación nueva tiene un propósito documentado y respeta `prefers-reduced-motion`?
- [ ] ¿Los datos mock/seed tienen exactamente la forma que tendrá la respuesta real del backend?
- [ ] ¿Las claves i18n están en inglés, agrupadas por contexto, con los textos en el idioma de destino?
