# Sistema de diseño del panel — tokens y catálogo Atomic Design (HU-02)

**Mantiene:** agente `design-ui`. **Depende de:** ADR 0002 (AdminLTE + Atomic Design), ADR 0004 (rol activo, ADR extendido 27/8/2026), ADR 0013 (multi-idioma UI), CLAUDE.md invariante 11 (ningún color hardcodeado).

Este documento es el catálogo vigente de tokens y componentes de presentación del panel. No es un ADR (no fija arquitectura, ADR 0002 ya lo hizo) — es el inventario vivo de qué existe, en qué nivel de Atomic Design, y qué falta. Se actualiza cada vez que `design-ui` agrega o cambia un token o un componente.

> **Tampoco es la receta para construir una pantalla.** Está escrito como bitácora ("quinta vuelta", "novena vuelta") y sirve para buscar el VALOR exacto de algo o entender por qué se decidió así. Si lo que vas a hacer es armar una pantalla nueva —dónde va cada archivo, con qué se compone un tablero / un listado / un formulario, qué verificar antes de cerrar— eso está en **`docs/diseno/guia_pantalla_panel.md`**.

## 1. Tokens CSS

Ubicación: `resources/css/tokens/`. Import único desde `resources/css/app.css` vía `resources/css/tokens/index.css`.

### 1.1. Dos capas, deliberadamente separadas

1. **Primitivos** (`tokens/primitives/`) — valores literales (hex). Ningún componente los usa directamente.
   - `brand.css`: la identidad **Industrial AgTech**, idéntica a la de la app móvil (`agrocom-field`, `docs/diseno/tokens/tokens.json`, claves `color.light` y `color.dark`). Es el único archivo con hex de marca. Nombres `--ag-brand-<tema>-<rol>` (`--ag-brand-light-primary`, `--ag-brand-dark-on-amber-container`…). Un valor nuevo de la app móvil se copia aquí tal cual.
   - `base.css`: escala neutra (grises), tonos categóricos violeta/magenta (`distintivo`), y las escalas de espaciado, tipografía, radio, elevación y transición. No tiene nada de marca.
2. **Semánticos** (`tokens/semantic/theme-light.css`, `theme-dark.css`) — los tokens que referencian los componentes (`--ag-color-bg`, `--ag-color-text`, `--ag-color-primary`…), resueltos sobre los primitivos. El tema activo se resuelve reasignando **estos** tokens vía el atributo nativo de Bootstrap 5.3 `[data-bs-theme="light"|"dark"]` en el `<html>`. Cada archivo declara exactamente los mismos nombres.

Regla de importación: fuera de `tokens/semantic/` ningún componente referencia `--ag-brand-*` ni un hex. La compuerta es `tests/Unit/TokensColorTest.php`.

### 1.2. Paleta de marca — Industrial AgTech

Regla 60 / 30 / 10: superficies neutras, grafito para la estructura (riel de módulos), verde para la acción principal y ámbar para lo que pide atención.

| Rol | Claro | Oscuro |
|---|---|---|
| bg | `#F3F5F1` | `#17251C` |
| surface | `#FFFFFF` | `#293832` |
| surface2 | `#E9EEE7` | `#34443B` |
| border | `#DDE4DC` | `#405247` |
| text | `#202A2E` | `#EDF3EC` |
| text2 | `#68756D` | `#AAB8AC` |
| primary (verde) | `#347A36` | `#62B66A` |
| primary-hover | `#28632C` | `#79CA80` |
| primary-container / on | `#E1EEDD` / `#22562A` | `#2E4A35` / `#A8DCAC` |
| graphite (grafito) | `#29343A` | `#34443B` |
| amber (ámbar) | `#F5A623` | `#FFB52E` |
| on-amber | `#29343A` | `#17251C` |
| amber-container / on | `#FDF0D3` / `#7A4B00` | `#4A3A17` / `#FFD488` |
| info | `#2785C7` | `#50A5D8` |
| info-container / on | `#E2F0FA` / `#14577F` | `#1E3A4C` / `#A6D4F0` |
| error | `#DC4545` | `#FF6868` |
| error-container / on | `#FCE6E6` / `#A12727` | `#4A2326` / `#FFB4B4` |

Los tonos violeta y magenta de `base.css` no pertenecen a la marca: son categóricos (sin carga de bueno o malo) para distinguir más valores de los que cubren los estados.

### 1.3. Verificación de contraste (WCAG 2.1 AA, 4,5:1 texto normal, 3:1 no-texto)

Calculado con la fórmula de luminancia relativa de WCAG sobre los valores finales de los tokens. Las mezclas son `color-mix(in srgb)` entre primitivos de marca.

Tema claro:

| Combinación | Ratio | Resultado |
|---|---|---|
| `--ag-color-text` sobre `--ag-color-surface-card` / `--ag-color-bg` | 14,7:1 / 13,4:1 | AAA |
| `--ag-color-text-muted` (text2 80 % + text 20 %, `#5A6660`) sobre tarjeta / bg / surface2 | 6,0:1 / 5,5:1 / 5,1:1 | AA |
| `--ag-color-text-faint` (text2 puro) sobre tarjeta | 4,8:1 | AA; reservado a íconos apagados y placeholders |
| Blanco sobre `--ag-color-primary` | 5,3:1 | AA (botón primario, relleno de éxito) |
| `--ag-color-primary-emphasis` sobre `--ag-color-bg` | 4,8:1 | AA (enlaces, ítem activo) |
| Blanco sobre `--ag-color-primary-hover` | 7,2:1 | AAA |
| `--ag-color-accent-contrast` (grafito) sobre `--ag-color-accent` (ámbar) | 6,3:1 | AA (botón de acento) |
| `--ag-color-accent-emphasis` (on-amber-container) sobre blanco | 7,4:1 | AAA (ámbar usado como texto) |
| `-strong` sobre `-subtle`: success / warning / info / danger | 7,2 / 6,6 / 6,7 / 6,2 :1 | AAA / AA+ (chips tenues, alertas) |
| `--ag-color-danger` (plano) sobre tarjeta / bg | 5,3:1 / 4,8:1 | AA (texto y borde de `danger-outline`) |
| `--ag-color-warning` (plano) sobre tarjeta / bg | 5,1:1 / 4,7:1 | AA |
| `--ag-color-info` (plano) sobre tarjeta / bg | 5,1:1 / 4,7:1 | AA |
| Blanco sobre `--ag-color-success-contrast-fill` | 5,3:1 | AA |
| Blanco sobre `--ag-color-danger-contrast-fill` | 5,3:1 | AA |
| Blanco sobre `--ag-color-warning-contrast-fill` | 5,1:1 | AA |
| Blanco sobre `--ag-color-info-contrast-fill` (y `primary-2`, `distintivo-3`) | 5,1:1 | AA |
| Blanco sobre `--ag-color-alert-contrast-fill` | 7,4:1 | AAA |
| Blanco sobre `--ag-color-distintivo-1-contrast-fill` / `-2-` | 4,6:1 / 4,9:1 | AA |
| `distintivo-1-strong` / `-2-strong` sobre su `-subtle` | 8,2:1 / 7,9:1 | AAA |
| `--ag-color-neutral-strong` sobre `--ag-color-neutral-subtle` | 5,1:1 | AA |
| `--ag-color-focus-ring` (info) sobre tarjeta / bg | 4,0:1 / 3,6:1 | Pasa 3:1 no-texto |
| `--ag-color-text-rail` sobre `--ag-color-bg-rail` (riel, ambos temas) | 7,1:1 | AAA |
| `--ag-color-text-rail-active` sobre `--ag-color-bg-rail-active` | 4,7:1 | AA |

Tema oscuro:

| Combinación | Ratio | Resultado |
|---|---|---|
| `--ag-color-text` sobre tarjeta / bg | 10,9:1 / 14,1:1 | AAA |
| `--ag-color-text-muted` (text2) sobre tarjeta / bg / surface2 | 6,0:1 / 7,7:1 / 5,0:1 | AA+ |
| `--ag-color-primary` sobre tarjeta / bg | 4,9:1 / 6,4:1 | AA |
| `--ag-color-primary-contrast` (on-primary) sobre `--ag-color-primary` | 6,9:1 | AA |
| `--ag-color-primary-hover` sobre tarjeta | 6,2:1 | AA (enlace de autenticación sobre bg: 8,0:1) |
| `--ag-color-accent` (ámbar) sobre tarjeta / bg | 7,0:1 / 9,0:1 | AAA |
| `--ag-color-accent-contrast` sobre `--ag-color-accent` | 9,0:1 | AAA |
| `-strong` sobre `-subtle`: success / warning / info / danger | 6,3 / 7,9 / 7,5 / 8,0 :1 | AA+ |
| `--ag-color-danger` (plano) sobre tarjeta / bg | 4,9:1 / 6,3:1 | AA |
| `--ag-color-info` (plano) sobre tarjeta / bg | 5,2:1 / 6,8:1 | AA |
| `distintivo-1-strong` / `-2-strong` sobre su `-subtle` | 4,6:1 / 4,9:1 | AA |
| `--ag-color-alert` sobre tarjeta | 7,3:1 | AAA |

Los rellenos `-contrast-fill` son **constantes entre temas** y llevan texto o ícono blanco encima; nunca se usan como texto o borde directo sobre una superficie que cambia de tema (para eso están los tokens planos). Warning y danger como relleno son versiones oscurecidas del ámbar y el rojo de marca (mezcla con `on-amber-container` y `on-error-container`) para llegar a 4,5:1 con blanco; el ámbar y el rojo puros se reservan a íconos, bordes y superficies donde el texto encima es grafito.

Regla operativa: **un chip tenue usa siempre el par `-subtle` (fondo) + `-strong` (texto)**; un chip sólido usa `-contrast-fill` + blanco. El token crudo (`--ag-color-success`) no se usa como texto sobre `-subtle`.

### 1.4. Inventario de tokens semánticos

| Token | Uso |
|---|---|
| `--ag-color-bg`, `--ag-color-bg-elevated`, `--ag-color-bg-chrome` | Fondo de página; fondo de tarjeta o input; fondo de sidebar y topbar |
| `--ag-color-bg-auth`, `--ag-color-text-muted-auth` | Superficie y texto secundario del panel de formulario de `auth-layout`; alias de `bg` y `text-muted` |
| `--ag-color-text`, `-text-muted`, `-text-faint`, `-text-inverse` | Texto principal, secundario, decorativo (íconos apagados) y texto sobre fondos de color |
| `--ag-color-border`, `-border-strong`, `-border-card`, `-border-row` | Divisores, bordes de controles, borde de tarjeta y de fila |
| `--ag-color-primary(-hover\|-contrast\|-emphasis\|-subtle\|-border-subtle)` | Verde de marca: botones, foco, estado activo, borde tenue de chips activos |
| `--ag-color-bg-nav-active` | Fondo sólido del ítem de menú activo |
| `--ag-color-accent(-hover\|-contrast\|-contrast-fill\|-emphasis\|-subtle\|-border-subtle\|-link)` | Ámbar de marca: CTA secundario y resaltados; `-emphasis` y `-link` son los usables como texto |
| `--ag-color-success\|danger\|warning\|info(-subtle\|-strong\|-border)` | Estados; `-subtle` es el fondo tenue, `-strong` el texto sobre ese fondo, el plano sirve de texto o borde sobre la tarjeta |
| `--ag-color-success\|danger\|warning\|info\|alert\|distintivo-1\|2\|3\|primary-2-contrast-fill` | Relleno sólido de chips, KPI y pasos, constante entre temas, con texto blanco |
| `--ag-color-primary-2`, `--ag-color-distintivo-3` | Alias del tono informativo con familia propia (`-subtle`, `-strong`, `-border`) para consumidores que distinguen la categoría |
| `--ag-color-alert(-subtle\|-strong\|-border)` | Rojo oscuro de máxima severidad (estado «cerrada», conflicto); distinto de `danger` |
| `--ag-color-distintivo-1\|2(-subtle\|-strong\|-border)` | Tonos categóricos violeta y magenta, sin carga de estado |
| `--ag-color-neutral-subtle`, `-neutral-strong` | Chip «sin estado» |
| `--ag-color-avance-bajo\|medio\|alto\|cumplido\|excedido` | Los cinco tramos de color de una barra de avance; alias de danger, warning, info, success y primary-emphasis |
| `--ag-color-surface-card`, `-bg-table-head`, `-bg-row-hover`, `-bg-input-chrome` | Tarjeta, cabecera de tabla, hover de fila, relleno del buscador y de las píldoras inactivas |
| `--ag-color-track`, `--ag-color-bar-unassigned` | Pista de las barras y barra «sin causa asignada» |
| `--ag-color-bg-rail`, `-bg-rail-active`, `-text-rail`, `-text-rail-active` | Riel de módulos y header móvil: grafito en ambos temas; el activo usa el verde claro del tema oscuro |
| `--ag-color-scrim-strong`, `-scrim`, `-text-on-scrim`, `-text-on-scrim-muted`, `-chip-border`, `-chip-text` | Overlay y texto sobre la fotografía de `auth-layout`; constantes entre temas (nunca usar `-text-inverse` sobre la foto) |
| `--ag-color-wordmark-accent`, `--ag-color-chip-icon` | Ámbar del «Agro» del wordmark «DronAgro» y verde del ícono del chip de `auth-layout`; constantes entre temas |
| `--ag-color-focus-ring` | Contorno de foco de teclado |
| `--ag-color-overlay` | Velo de modales |
| `--ag-space-1`…`--ag-space-8` | Espaciado, escala de 8 px |
| `--ag-font-family-base\|mono\|display` | Tipografía; ver §7.3 |
| `--ag-font-size-*`, `--ag-line-height-*`, `--ag-font-weight-*` | Escala tipográfica; pesos 400, 500, 700 y 800 |
| `--ag-radius-sm\|md\|lg\|pill` | Radio de borde |
| `--ag-shadow-sm\|md\|lg\|selected\|overlay-mapa` | Elevación |
| `--ag-transition-fast\|base`, `--ag-ease-emphasized\|drawer` | Duraciones y curvas de easing |
| `--bs-body-font-family`, `--bs-body-bg`, `--bs-body-color`, `--bs-border-color`, `--bs-primary(-rgb)`, `--bs-secondary(-rgb)`, `--bs-success`, `--bs-danger`, `--bs-warning`, `--bs-info` | Variables nativas de Bootstrap 5 reasignadas a los tokens de arriba, para que `.btn-primary`, `.alert-success` y AdminLTE hereden la marca sin overrides por componente |

Los componentes que necesitan un tono nuevo lo piden como token semántico en ambos temas; nunca declaran un color propio.

## 2. Mezcla Bootstrap / Material — criterio por tipo de componente

| Qué | Sistema base | Por qué |
|---|---|---|
| Grid, contenedores, utilidades de espaciado/flex, breakpoints responsivos, offcanvas (sidebar en mobile) | Bootstrap | Es la base de AdminLTE (ADR 0002 punto 2); reinventar un grid propio no aporta nada |
| Botones, inputs, cards, formularios | Material (forma "outlined"/pill, elevación, radios de borde mayores) montado sobre marcado propio — no se usa `.form-control`/`.btn` de Bootstrap para no heredar su estética por defecto, que es justo lo que ADR 0002 pide reemplazar | Ver `resources/css/components/input.css`, `button.css` |
| Iconografía | Material Symbols (ligadura de texto, variable font) | Set único, permisivo, reemplaza a los íconos de Bootstrap Icons/FontAwesome que trae AdminLTE por defecto |
| Tipografía | **Manrope** (interfaz y titulares) / **JetBrains Mono** (cifras tabulares y metadatos), las mismas familias de la app móvil, servidas desde el bundle con `@fontsource` (sin CDN). No se hereda el Roboto por defecto de Material: ADR 0002 pide el comportamiento de Material para inputs, cards e íconos, no su tipografía de fábrica. Manrope da buena legibilidad en tablas y formularios densos; JetBrains Mono da cifras de ancho fijo para hectáreas, montos y códigos | Una sola identidad tipográfica entre panel y app; ver `--ag-font-family-base`/`--ag-font-family-mono` |
| Color / theming | Ninguno de los dos "de fábrica" — tokens propios (`--ag-color-*`) que además reasignan las variables nativas de Bootstrap 5.3 (`--bs-*`) | CLAUDE.md invariante 11 |

## 3. Catálogo de componentes

| Nivel | Componente | Archivo | Estado |
|---|---|---|---|
| Atom | `icon` | `resources/views/components/atoms/icon.blade.php` | Implementado |
| Atom | `logo` | `resources/views/components/atoms/logo.blade.php` | Implementado |
| Atom | `input` | `resources/views/components/atoms/input.blade.php` | Implementado |
| Atom | `button` | `resources/views/components/atoms/button.blade.php` | Implementado |
| Molecule | `role-selector-item` | — | **Renombrado** a `role-card` (28/8/2026, quinta vuelta). El archivo con este nombre ya no existe. |
| Molecule | `theme-toggle` | `resources/views/components/molecules/theme-toggle.blade.php` | Implementado |
| Molecule | `grafico` | `resources/views/components/molecules/grafico.blade.php` | Implementado (ver §19) |
| Molecule | `lienzo-bloque` | `resources/views/components/molecules/lienzo-bloque.blade.php` | Implementado (ver §23) |
| Template | `lienzo` | `resources/views/components/templates/lienzo.blade.php` | Implementado (ver §23) |
| Molecule | `menu-item` | `resources/views/components/molecules/menu-item.blade.php` | Implementado |
| Organism | `collapsible-menu-group` | `resources/views/components/organisms/collapsible-menu-group.blade.php` | Implementado |
| Organism | `login-form` | `resources/views/components/organisms/login-form.blade.php` | Implementado |
| Organism | `sidebar-nav` | — | **Renombrado** a `module-sidebar` (28/8/2026, quinta vuelta: el sidebar plano pasó a ser el nivel 2 del layout de tres niveles). El archivo con este nombre ya no existe. |
| Organism | `topbar` | `resources/views/components/organisms/topbar.blade.php` | Implementado |
| Template | `panel-layout` | `resources/views/components/templates/panel-layout.blade.php` | Implementado |
| Template | `auth-layout` | `resources/views/components/templates/auth-layout.blade.php` | Implementado |
| Template | `legal-layout` | `resources/views/components/templates/legal-layout.blade.php` | Implementado. Cáscara pública de texto largo (términos y privacidad): cabecera, columna de lectura (`--ag-size-lectura`) y pie. Estilos en `resources/css/components/legal-layout.css`. Ver `docs/politicas/legal.md` |
| Atom | `badge` | `resources/views/components/atoms/badge.blade.php` | Implementado (2026-08-28) |
| Atom | `switch` | `resources/views/components/atoms/switch.blade.php` | Implementado (2026-08-28) |
| Molecule | `stat-card` | `resources/views/components/molecules/stat-card.blade.php` | Implementado (2026-08-28) |
| Molecule | `plan-card` | `resources/views/components/molecules/plan-card.blade.php` | Implementado (2026-08-28) |
| Molecule | `form-section` | `resources/views/components/molecules/form-section.blade.php` | Implementado (2026-08-28); **evolucionado** de `<fieldset>` desnudo a tarjeta con header `section-head` + grid de dos columnas (2/9/2026, tarea 31 — ver §14) |
| Molecule | `role-card` | `resources/views/components/molecules/role-card.blade.php` | Implementado (2026-08-28, quinta vuelta) |
| Molecule | `section-head` | `resources/views/components/molecules/section-head.blade.php` | Implementado (2026-08-28, sexta vuelta parte 2) |
| Molecule | `alert-strip` | `resources/views/components/molecules/alert-strip.blade.php` | Implementado (28/8/2026, sexta vuelta parte 2 — ver §10.3). Faltaba en esta tabla; se agrega en la sincronización del 2/9/2026. |
| Organism | `module-rail` | `resources/views/components/organisms/module-rail.blade.php` | Implementado (28/8/2026, quinta vuelta — nivel 1 del layout, §7.1). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Organism | `module-sidebar` | `resources/views/components/organisms/module-sidebar.blade.php` | Implementado (28/8/2026, quinta vuelta — nivel 2 del layout, §7.1; sucesor de `sidebar-nav`). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Organism | `module-drawer` | `resources/views/components/organisms/module-drawer.blade.php` | Implementado (28/8/2026, quinta vuelta — offcanvas de módulos en móvil, §7.2). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Organism | `mobile-topbar` | `resources/views/components/organisms/mobile-topbar.blade.php` | Implementado (28/8/2026, quinta vuelta — header oscuro <768px, §7.2). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Atom | `tema-inicial` | `resources/views/components/atoms/tema-inicial.blade.php` | Implementado (9/9/2026 — script inline anti-parpadeo del `<head>`, ver §20). No dibuja nada: es el único componente sin salida visual del catálogo. |
| Template | `panel-shell` | `resources/views/components/templates/panel-shell.blade.php` | Implementado (28/8/2026, quinta vuelta — cáscara `<html>` compartida por todas las páginas del panel, con el tema persistido del usuario). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Organism | `page-header` | `resources/views/components/organisms/page-header.blade.php` | Implementado (2/9/2026, tarea 31 — arquetipo formulario, ver §14) |
| Molecule | `tabs` | `resources/views/components/molecules/tabs.blade.php` | Implementado (2/9/2026, tarea 31 — el CSS ya existía desde el rediseño del dashboard, faltaba el componente). 23/9/2026 (tarea 136): prop `variant` (`underline` por defecto, `segmented`) — la segmentada es la misma mecánica de `tab` de Bootstrap con la piel de un selector (caja con borde, opción activa en el par «seleccionado» `-primary-subtle`/`-primary-emphasis`), para alternar vistas del MISMO dato dentro de una tarjeta sin recargar; primer uso: diario/semanal/mensual del resumen de vuelos del encargado. |
| Organism | `form-actions-bar` | `resources/views/components/organisms/form-actions-bar.blade.php` | Implementado (2/9/2026, tarea 31) |
| Molecule | `summary-card` | `resources/views/components/molecules/summary-card.blade.php` | Implementado (2/9/2026, tarea 31). 19/9/2026: el slot `action` admite varios botones — el contenedor (`.ag-summary-card__action`) es una columna con `gap`, un solo botón se ve igual que antes. 23/9/2026: la etiqueta no se encoge (`flex-shrink: 0`) y el valor puede partirse (`min-width: 0` + `overflow-wrap: anywhere`) — un correo o una dirección largos, sin espacios, estrechaban la etiqueta hasta partirla en una palabra por línea y se salían de la tarjeta (visto en «Mi perfil») |
| Molecule | `progress-meter` | `resources/views/components/molecules/progress-meter.blade.php` | Implementado (2/9/2026, tarea 31) |
| Molecule | `file-field` | `resources/views/components/molecules/file-field.blade.php` | Implementado (2/9/2026, tarea 31 — antes markup suelto en `organizacion.css`). 20/9/2026 (tarea 118): al elegir un archivo el campo muestra también su nombre y su peso (`resources/js/molecules/file-field.js`), no solo la vista previa de una imagen — un PDF no tiene vista previa y sin esto no había ninguna señal de que el campo lo tomó (primer uso con un comprobante: `finanzas::pages.gastos.create`). Al quitar la elección vuelve lo que había. Además `.ag-file-field` es `position: relative`: el input nativo (visually-hidden, `position: absolute`) no tenía ancestro posicionado y, con el campo bajo el pliegue de un formulario largo, estiraba el documento y la ventana scrolleaba (guía §4.1; en Clientes no se notaba porque el campo queda arriba). |
| Template | `portal-layout` | `resources/views/components/templates/portal-layout.blade.php` | Implementado (3/9/2026, tarea 55 — cáscara del portal del cliente, header de una fila con 3 links fijos en vez del layout de tres niveles de `panel-layout`, ver §4.10). Faltaba en esta tabla; se agrega en la corrección de la tarea 55. |
| Atom | `select` | `resources/views/components/atoms/select.blade.php` | Implementado (8/9/2026, tarea 76 — ver §16) |
| Atom | `date` | `resources/views/components/atoms/date.blade.php` | Implementado (8/9/2026, tarea 76 — ver §16) |
| Atom | `checkbox` | `resources/views/components/atoms/checkbox.blade.php` | Implementado (8/9/2026, tarea 76 — ver §16) |
| Atom | `checkbox-group` | `resources/views/components/atoms/checkbox-group.blade.php` | Implementado (8/9/2026, tarea 76 — ver §16) |
| Atom | `radio-group` | `resources/views/components/atoms/radio-group.blade.php` | Implementado (8/9/2026, tarea 76 — ver §16) |
| Atom | `textarea` | `resources/views/components/atoms/textarea.blade.php` | Implementado (8/9/2026, tarea 76 — ver §16) |
| Atom | `datetime` | `resources/views/components/atoms/datetime.blade.php` | Implementado (11/9/2026 — reemplaza `<x-atoms.input type="datetime-local">` nativo en `pausas` inicio/fin, el mismo problema que ya se había resuelto para `atoms/date` sin hora). Única excepción del catálogo que envuelve una librería de terceros (flatpickr) en vez de construirse desde cero: un selector de hora accesible a mano es mucho más trabajo que el calendario de días de `atoms/date`, y esto solo tiene dos usos hoy — decisión explícita del usuario. Retematizado completo a tokens en `resources/css/components/datetime.css` (CLAUDE.md invariante 11); CSS estructural de flatpickr importado como vendor en `app.css`, mismo criterio que Bootstrap. Progressive enhancement distinto al resto del catálogo: el control real es `<input type="text">`, no un `type="datetime-local"` nativo oculto (ese tipo exige el formato exacto `YYYY-MM-DDTHH:mm` incluso seteado por JS) — sin JS el campo es texto plano editable a mano, que el backend acepta igual (`RegistrarPausaRequest` valida solo `'date'`, sin formato estricto). 24h siempre (`H:i`), nunca a.m./p.m. — mismo criterio que la columna HORA del dashboard. Carga diferida vía `data-ag-datetime` (mismo patrón que dashboard-charts/dashboard-map), no entra en el bundle de pantallas que no lo usan. |
| Atom | `time-range` | `resources/views/components/atoms/time-range.blade.php` | Implementado (19/9/2026 — primer uso: horario por lote en `contratos/_lotes-tabla`, reemplazaba a los dos `<input type="time">` nativos; el 21/9/2026 el horario por lote salió del contrato y hoy lo usa `pausas/create`). Rango horario en UNA casilla compacta (~14 rem) con un POPUP de reloj anclado a ella (Popover API: capa superior sin recortes, cierra con clic afuera o Esc, sin fondo oscuro; debajo de la casilla o encima si no entra). El reloj es de **12 horas con «a. m.»/«p. m.» explícitos** junto a cada hora en la cabecera —un dial de 1 a 12 sin período no dice si 3:00 es de la madrugada o de la tarde—; dial de una sola vuelta (minutos con marcas cada 5 y precisión de 1 al arrastrar); elegir una hora pasa sola a sus minutos, luego a la hora de fin y a sus minutos; **el período de la hora de fin se elige solo** para que quede después del inicio (10:00 a. m. → «3» = 3:00 p. m.) mientras no se toque a mano. Modo de texto que acepta «6:30 a. m.», «6 pm» o «18:30»; valida que ambas horas estén y que el fin sea posterior. Lo que se guarda es siempre `H:i` en 24 horas (el que espera `date_format:H:i`); la casilla muestra «10:00 a. m. – 3:30 p. m.». Props `nameStart`, `nameEnd`, `id` (único), `valueStart`, `valueEnd`, `label`, `help`, `error`, `invalid`, `disabled`, `required` (20/9/2026, tarea 113: asterisco junto al rótulo, el de `atoms/input`, y `required` en los dos nativos; segundo consumidor: la hora de la pausa en `pausas/create`). Mejora progresiva de `atoms/date`: los dos nativos son los controles reales y se ocultan con `--enhanced`; sin Popover API no se mejora y quedan los nativos. Cualquier código puede deshabilitarlo cambiando `disabled` en los nativos (el componente lo observa). Las filas que nacen después de cargar la página se inicializan con `initTimeRanges(raiz)` (idempotente) y se clonan de un `<template>` con `__INDICE__` en `name` e `id`. Tokens: cabecera `--ag-color-primary` con texto `--ag-color-gray-0` (constante, como el paso actual de `step-arrow`), dial `--ag-color-bg-input-chrome`. Verificado en claro y oscuro y a 360 px. |
| Molecule | `paginador-cliente` (JS + `.ag-paginador`) | `resources/js/shared/paginador-cliente.js`, `resources/css/components/pagination.css` | Implementado (19/9/2026 — usos: modal de lotes de un contrato y su tabla de lotes, la tabla de lotes de una orden de aplicación en su formulario y en su detalle, 20 por página). Gemelo en el navegador de `molecules/pagination` para listas que YA están completas en la página: mismo markup de `.pagination`/`.page-link` de Bootstrap, con el resumen «Lotes 1–20 de 33» a la izquierda. Solo dibuja los controles (`crearPaginador(contenedor, {porPagina, alCambiar})`, `actualizar(total, pagina)`, `rango()`); quien lo usa oculta las filas de otras páginas con `hidden` —nunca las quita ni deshabilita sus campos— para que lo marcado y lo que se envía con el formulario se conserve. Textos por `data-label-*` del contenedor (`ui.paginador.*`). Se oculta solo si todo cabe en una página. Para una tabla cuyas filas ya están pintadas, `paginarFilas(contenedor, {filas, porPagina})` arma el paginador y oculta las filas de otras páginas (`actualizar(pagina)` cuando las filas cambian): es lo que usan las órdenes. |
| Molecule | `empty-state` | `resources/views/components/molecules/empty-state.blade.php` | Implementado (15/9/2026 — tercera repetición del patrón "tarjeta grande, no hay NADA que mostrar" (`dashboard/_sin-secciones`, `personas/desempeno`), promovido al catálogo per `guia_pantalla_panel.md` §5.1. Complementa a `alert-strip` (banda delgada, "el filtro no trae nada") sin depender de él ni de `.ag-card` (local a `dashboard.css`, no compartida) — tarjeta propia con los tokens que ya usaba `personas.css`. |
| Atom | `color-swatch-picker` | `resources/views/components/atoms/color-swatch-picker.blade.php` | Implementado (16/9/2026 — paleta curada de color de `Propiedad`, Comercial, ver §21). Independiente de `atoms/radio-group` (mismo mecanismo nativo, marcado propio: un atom no compone otro del catálogo). Sin cambios en esta pieza en la segunda vuelta (16/9/2026, §21.4): ahora vive DENTRO de `molecules/color-palette-modal` en vez de suelta en la fila del formulario — sin conectar todavía al formulario real de Propiedad, lo arma `frontend`. |
| Molecule | `color-swatch-field` | `resources/views/components/molecules/color-swatch-field.blade.php` | Implementado (16/9/2026, segunda vuelta — control compacto de una fila (swatch + hex + botón "Cambiar") que reemplaza a los círculos sueltos en el formulario, ver §21.4). Sin conectar todavía a ningún formulario. |
| Molecule | `color-palette-modal` | `resources/views/components/molecules/color-palette-modal.blade.php` | Implementado (16/9/2026, segunda vuelta — modal Bootstrap que aloja `atoms/color-swatch-picker`, pareja de `color-swatch-field`, ver §21.4). Sin conectar todavía a ningún formulario. |
| Molecule | `index-table` | `resources/views/components/molecules/index-table.blade.php` | Implementado (17/9/2026 — tarea "panel homogéneo": clientes/propiedades/campanias/lotes/contratos.css declaraban la misma tabla de listado (borde/cabecera/hover/colapso mobile) letra por letra, solo cambiando el nombre de clase por página — ver `guia_pantalla_panel.md` §6.2. `columns` (prop) viaja como custom property inline (`--ag-index-table-columns`), mismo mecanismo que `progress-meter`. Molecule y no organism: no orquesta nada propio, la cabecera/filas son contenido del llamador — mismo criterio que `form-section`. Una fila con `hidden` (paginador del navegador) se oculta: `.ag-index-table__row[hidden]` lo declara porque el `display: grid` de la fila le gana al `[hidden]` del navegador. 20/9/2026 (tarea 118): dos variantes de celda para las cifras — `.ag-index-table__cifra` (mono, a la derecha, `tabular-nums`; su encabezado lleva `.ag-index-table__cifra-head`) para montos y cantidades, y `.ag-index-table__mono` (mono, a la izquierda) para una fecha o un código. En móvil, sin cabecera, la cifra vuelve a alinearse a la izquierda. Nacieron de Anticipos, Combustible y Gastos, que las repetían en tres CSS de página. |
| Molecule | `form-layout` | `resources/views/components/molecules/form-layout.blade.php` | Implementado (17/9/2026, misma tarea) — layout main + aside pegajoso del arquetipo Formulario (§6.3 regla 4/§6.3.1), antes declarado idéntico en las mismas cinco páginas (`.ag-clientes-form__layout/__main/__aside`, etc.). Slot por defecto = main; slot nombrado `aside` (opcional, se puede envolver en `@if` en el sitio de la llamada). |
| Molecule | `timeline` | `resources/views/components/molecules/timeline.blade.php` | Implementado (17/9/2026 — arquetipo Detalle §6.4, primer consumidor `ordenes/show.blade.php`). Lista vertical de eventos reales (punto de color + título + meta fecha·autor, ya resueltos por el llamador) — nunca inventa un evento que el controlador no pudo reconstruir desde columnas reales. |
| Molecule | `confirm-modal` | `resources/views/components/molecules/confirm-modal.blade.php` | Implementado. Modal de confirmación standalone: el llamador pone el `<form>` afuera (fuera de `row-actions` y de cualquier otro form) y el botón "Confirmar" lo envía por su atributo `form`. **Slot por defecto opcional (19/9/2026, ADR 0022):** campos que viajan con la confirmación (p. ej. el motivo de pausar, o causa + motivo de cancelar una orden). Se dibujan entre el mensaje y los botones y NO están dentro del form: cada control se asocia con `form="<formId>"` (los átomos `textarea`/`select`/`input` lo dejan pasar a su control), así el navegador los valida y los envía con el botón, sin JS. Sin slot, se ve igual que siempre. Un modal de cancelación usa `cancel-label` = "Cerrar" para no chocar con un "Cancelar" de acción. **`tone` con toda la paleta de `atoms/badge` (19/9/2026):** además de danger/success/warning/info/neutral acepta alert, distintivo-1/2/3 y primary-2 (círculo del ícono en `-subtle`/`-strong` de ese tono), para que un modal que confirma un cambio de ESTADO lleve el color de ese estado; el botón de confirmar sigue en `primary` (o `danger`): el color de estado no compite con el CTA. |

| Molecule | `info-modal` | `resources/views/components/molecules/info-modal.blade.php` | Implementado (19/9/2026 — primer uso: contratos, aviso de aplicación abierta). Hermano de `confirm-modal` para cuando una acción todavía NO se puede hacer: mismo aspecto (reusa `ag-confirm-modal__*`, mismos tonos) pero sin `<form>` ni botón de confirmar. Props `id`, `title`, `message`, `closeLabel`, `tone` (default `warning`), `modalIcon` (default `info`); slot por defecto para un detalle y slot `actions` para enlaces previos al botón de cerrar (p. ej. "Ir a la orden"). No decide nada: lo abre un botón con `data-bs-toggle="modal"`, como cualquier modal. El servidor sigue rechazando la acción aunque alguien se salte la pantalla. |
| Molecule | `view-toggle` | `resources/views/components/molecules/view-toggle.blade.php` | Implementado (17/9/2026 — alterna lista/grilla de un listado, primer consumidor `ordenes/index.blade.php`). Dos links GET a `?vista=lista\|grilla`, sin JS propio ni estado de cliente — mismo criterio "sin Livewire" del resto del panel. |
| Molecule | `step-arrow` | `resources/views/components/molecules/step-arrow.blade.php` | Implementado (19/9/2026 — consumidores `campanias/edit`, `contratos/edit` y `ordenes/edit` + `ordenes/show`, vía `_formulario` + `_cambio-estado` / `_orden-modales`). La máquina de estados de un objeto como pasos con forma de flecha, COMPARTIDO: los pasos los arma `Compartido\Infraestructura\Http\PasosDeEstado::armar()` leyendo la tabla de transiciones de la propia máquina (`completed`/`current`/`next`/`pending`/`blocked`), y `PasosDeEstado::ayuda()` compone el párrafo (`help`) con el texto que el objeto define en su `lang` por estado (`<modulo>.<objeto>.estado_ayuda.<valor>`, el porqué de avanzar) más el cierre genérico de `ui.pasos.*`. Una máquina con desvíos y salidas (contrato) usa además las opciones de `armar()` `recorridos`, `pistas` e `iconos` y el paso de cierre de `ayuda()` — todas opcionales, ver `guia_pantalla_panel.md` §6.3.4. Un paso `next` es un botón que abre el modal de la página (de confirmación, o `info-modal` si una regla de negocio lo impide); puede quedar antes del actual (reanudar una pausa): manda la tabla de transiciones. El componente no envía ni decide nada. Tema: el paso actual lleva relleno sólido del color del estado con texto `--ag-color-gray-0` (constante, como `atoms/badge`; `--ag-color-text-inverse` se invierte en oscuro y NO sirve sobre un `-contrast-fill`), salvo el tono `neutral`, que va solo con contorno de 2px, y salvo cuando es un estado FINAL (la máquina ya no ofrece ningún paso `next` ni `pending`; en una línea recta es el último): ahí se dibuja como procesado (color suave + check —o el `icon` del paso—, igual que un paso completado) y solo conserva `aria-current`. Un paso recorrido lleva el color suave de SU estado. Tamaño: ocupa todo el ancho de su fila (col-12) y consulta el ancho de su propio contenedor (`container: ag-step-arrow`), no el del viewport — compacto bajo 48rem y apilado sin puntas bajo 32rem; pensado para 3 a 5 pasos (el contrato dibuja 5). |
| Molecule | `state-transition` | `resources/views/components/molecules/state-transition.blade.php` | Implementado (19/9/2026 — consumidores `contratos/_estado-transicion`, `cuadrillas/_cambio-estado`, `estadias/_cambio-estado` y el diálogo «Finalizar» de `estadias/index`). La ficha «estado actual → estado destino» que va dentro del `confirm-modal` de un cambio de estado: dos `atoms/badge` con el tono de cada estado y una flecha (`atoms/icon`) en medio, para ver antes de confirmar cuál cambio se está haciendo (`guia_pantalla_panel.md` §6.3.4). Nació al aparecer el mismo marcado en tres pantallas —era CSS de página de Contratos (`.ag-contratos-estado__transicion`), que se retiró—: un patrón repetido es del catálogo. Es molecule porque compone dos átomos; no es el modal, no decide ninguna transición y no conoce enums ni archivos de idioma: recibe `fromLabel`/`toLabel` ya traducidos, `fromTone`/`toTone` (los mismos tonos del badge del listado y del paso de `step-arrow`) y `label` (nombre accesible del grupo). Sin color propio: los colores los ponen los badges; la flecha va en `--ag-color-text-muted`. |

Por qué solo los átomos estaban implementados en el pase anterior: era el límite de alcance fijado para la primera entrega de HU-02 (tokens + piezas de más bajo nivel, sin lógica de negocio). Este pase (27/8/2026) implementa el resto del catálogo, a pedido explícito de HU-02 (el usuario vio un prototipo interactivo aparte y pidió la construcción real). Decisiones de composición que no estaban 100% cerradas en la especificación de §4 y se resolvieron acá:

- **`role-selector` (§4.1) se partió en un único molecule reutilizable, `role-selector-item`** (el ítem, no la lista+botón que describía el catálogo original) — porque el mismo ítem se usa en dos contenedores distintos (la pantalla de selección inicial tras el login, que arma `frontend`, y el popover de "cambiar de rol activo" de `topbar`/`sidebar-nav`, implementado acá). Cada contenedor arma su propia lista con un `@foreach`; no hay una molécula "lista" intermedia. El prop `variant` (`pick`|`switch`) decide si el ítem expone `aria-pressed` (selección aún no confirmada) o `aria-current` (rol ya activo).
- **`topbar` tiene su propio `theme-toggle`**, además del que vive en el pie de `sidebar-nav` (el catálogo §4.7 ya lo pedía; se hace explícito acá porque en mobile, con el sidebar cerrado, es la única forma de cambiar de tema sin abrir el menú).
- **Mecánica de interacción, revisada respecto al catálogo original**: con Bootstrap 5.3 (JS incluido) ya instalado por `frontend`, `sidebar-nav` usa el `offcanvas` nativo de Bootstrap para el colapso en mobile (`.offcanvas.offcanvas-lg`, breakpoint `lg` = 991.98px) y `collapsible-menu-group` usa su `collapse` nativo — cero JS propio para esos dos casos, tal como pedía el catálogo. El colapso a icon-rail en desktop (no existe en Bootstrap) y el `theme-toggle` (presentación pura, sin backend) sí tienen JS propio mínimo: `resources/js/organisms/sidebar-nav.js` y `resources/js/molecules/theme-toggle.js`, mismo patrón de delegación de eventos que `resources/js/atoms/input.js`. El popover de cambio de rol usa el `dropdown` nativo de Bootstrap (Popper incluido): cada disparador (topbar y sidebar) ancla su propio popover, no uno compartido.
- **Reduced motion**: nunca `transform: none !important` de forma general — eso rompería estados codificados en `transform` (el thumb del `theme-toggle`, el `translateX` del sidebar offcanvas). Bajo `prefers-reduced-motion: reduce` solo se acorta `transition-duration`/`animation-duration`; el valor final de `transform` por estado sigue aplicando.
- **Nada de blobs/gradientes de color decorativos en `auth-layout`**: la profundidad la da la foto real (`public/images/drone-hero.jpg`, split-screen en desktop, imagen-arriba en tablet, solo formulario en mobile) + `--ag-shadow-lg` sobre el panel del formulario — no un adorno abstracto encima.
- **Dos tokens nuevos** en `tokens/primitives/base.css`: `--ag-ease-emphasized` y `--ag-ease-drawer` (curvas cubic-bezier propias, solo la curva — la duración se compone en cada sitio de uso), para las animaciones de entrada/expansión de este pase en vez del `ease-in-out` genérico que ya usaban `--ag-transition-fast`/`base`.
- **`lang/es/seguridad.php`** (nuevo): copy fijo de login y selección/cambio de rol — ver §5. `lang/es/ui.php` sumó `sidebar.*` (abrir/cerrar/colapsar/expandir menú, genérico de cualquier pantalla) y `footer.copyright`.
- Accesibilidad añadida más allá del contrato original: `role="switch"` + `aria-checked` dinámico en `theme-toggle`; `aria-label` de los botones de colapsar sidebar y mostrar/ocultar contraseña cambia según el estado (no queda fijo); `aria-pressed`/`aria-current` en los botones de rol.

**Refinamiento editorial de `auth-layout` (segunda vuelta, 27/8/2026)** — el usuario vio un prototipo de referencia (herramienta externa, no en el repo) con 3 direcciones y pidió la "1a: editorial cálido"; se adoptó la IDEA (wordmark arriba + headline grande en serif abajo sobre la foto, formulario en tarjeta clara/elevada), nunca su paleta (no sale de los logos de Agrocom) ni sus campos (nuestro login es username-only, sin recuperación ni alta pública — `login-form` no cambió):

- **`--ag-font-family-display`** (`tokens/primitives/base.css`): tipografía de titulares grandes. Resuelve a Manrope, la misma familia de `--ag-font-family-base`; el titular se distingue por peso y tamaño. Ver §7.3.
- **`--ag-color-scrim-strong`, `--ag-color-scrim`, `--ag-color-text-on-scrim`** (nuevos, `tokens/semantic/theme-{light,dark}.css`, **mismo valor en los dos archivos a propósito**): overlay oscuro (vignette, sin color de marca) y texto sobre `drone-hero.jpg`. Constantes entre temas porque la foto no reasigna por `[data-bs-theme]` — usar `--ag-color-text-inverse` ahí sería un bug real en tema oscuro (resuelve a gris-900, casi invisible sobre la foto ya oscurecida). Ver estimación de contraste en §1.3 (ídem en ambos temas, por diseño).
- **El wordmark es texto, no el átomo `logo`**: al momento de esta decisión, `logo-light.jpeg`/`logo-dark.jpeg` traían fondo sólido horneado (JPEG, sin transparencia); sobre una foto se hubiera visto un recuadro blanco/negro. Se resolvió como `<p>` en `--ag-font-family-display` reutilizando la clave `ui.logo.alt` (sin agregar un string nuevo) — el logo de `login-form` no se duplicó ni se quitó, sigue siendo el único wordmark visible en mobile (donde la foto se oculta). **Actualización (tercera vuelta):** el asset ya es `public/logo.png` transparente (ver nota más abajo, "Asset de logo transparente") — la limitación técnica desapareció, pero el wordmark sigue en texto porque el mockup pide la bicromía tipográfica "AGRO"/"COM", no el isotipo cuadrado.
- **Headline** (`seguridad.auth.headline`, nuevo en `lang/es/seguridad.php`): `clamp(2.25rem, 1.2rem + 2.2vw, 3rem)`, `line-height: 1.12`. Solo se renderiza en tablet/desktop (≥768px, cuando la foto es visible) — en mobile no hay riesgo de que se vea desproporcionado o recortado porque directamente no se pinta (`display:none` heredado del contenedor de la foto).
- Verificado explícitamente que el ajuste funciona en **ambos temas**, no solo en el default: como los tres tokens nuevos son constantes entre temas, el contraste del wordmark/headline sobre la foto es idéntico en claro y oscuro (no dependía de la corrección). La tarjeta de `login-form` (`--ag-color-bg-elevated` + `--ag-color-text`/`-muted`) ya pasaba AA en claro (§1.3 original); se sumó a la tabla la verificación equivalente en oscuro (~15:1 texto principal, ~8:1 texto secundario — ambas AAA). El panel de la foto no "se aplana" contra el resto de la UI oscura porque el vignette deja visible el centro de la foto (color/textura reales del cultivo) — la distinción no depende de un salto de luminosidad con el fondo circundante, sino de que ahí hay una fotografía y no una superficie plana.

**Rediseño de login (tercera vuelta, HU-02)** — el usuario aprobó un mockup de referencia (`docs/login.html`, sección "Turno 3", ids `#3a` claro / `#3b` oscuro) para un tercer refinamiento visual del login. Esta vuelta es ajuste de tokens + extensión de átomos compartidos + re-arquitectura de `login-form` en TABS; no toca arquitectura de negocio (contratos, `SesionController`, `IniciarSesionRequest`), solo presentación:

- **`--ag-color-accent-emphasis`** y **`--ag-color-accent-link`**: el ámbar de marca no llega a AA como texto sobre blanco, así que el ámbar usable como texto es `on-amber-container` en tema claro. Ver §1.3.
- **`--ag-color-bg-auth`, `--ag-color-text-muted-auth`, `--ag-color-accent-link`** (nuevos tokens semánticos, `theme-{light,dark}.css`): fondo propio del panel de formulario de login (reemplaza `--ag-color-bg-elevated` ahí, quita la tarjeta blanca elevada de vueltas anteriores), su variante de texto muted (el muted global falla AA sobre el nuevo fondo tintado en claro), y un ámbar seguro para texto de enlace. Ver §1.3/§1.4 para la verificación de contraste completa y §1.4 para el detalle de cada token.
- **`--ag-color-wordmark-accent`, `--ag-color-chip-icon`** (tokens semánticos, mismo valor en `theme-{light,dark}.css` a propósito): constantes entre temas para el branding tipográfico de `auth-layout` (wordmark bicromía "AGRO"/"COM" + chip de ícono). Criterio idéntico a `--ag-color-scrim*`: la foto no reasigna por tema, así que lo que va montado sobre ella tampoco. Usan el ámbar y el verde de marca.
- **`atoms/input` — prop `variant`** (`'boxed'` default | `'line'`): variante de línea editorial (sin caja, solo `border-bottom`, label uppercase) para el nuevo diseño de login. Mismo Blade, mismo marcado/accesibilidad — el modificador es una clase CSS (`ag-input--line`) resuelta en `resources/css/components/input.css`, no un componente nuevo. El resto del panel sigue usando `'boxed'` (default) sin cambios. El color muted propio de auth (`--ag-color-text-muted-auth`) es responsabilidad de quien consume el átomo (el organism/template de login), no del átomo compartido — este no conoce "auth" como concepto.
- **`atoms/input` — prop `helpTone`** (`null` default | `'accent'` | `'alert'`, 19/9/2026): colorea el texto de ayuda con el acento de marca o el de alerta (`ag-input__help--accent` / `--alert`, ya definidos en `input.css`) para una ayuda que es una SUGERENCIA o un aviso y no una aclaración neutra — sin pasarlo, el gris de siempre. Sirve para el primer pintado; la ayuda que cambia de tono según lo escrito (`contratos-form.js`, "Adelanto Solicitado"; `lotes-generar.js`, hectáreas por lote) alterna las mismas dos clases desde JS con sus plantillas en `data-plantilla-ayuda*` del propio input. Texto siempre sobre `--ag-color-accent-link`/`--ag-color-alert-strong` (contraste medido 5,11:1 y 9,82:1 en claro, 10,35:1 y 7,84:1 en oscuro sobre la tarjeta).
- **`atoms/input` — prop `suffix`** (`null` default, 20/9/2026, tarea 115): unidad del valor ("km", "h", "kg", "ciclos") al final del control, DENTRO del mismo borde, en `--ag-font-family-mono` y `--ag-color-text-muted` (`ag-input__suffix`, en `input.css`). Reemplaza al paréntesis en el rótulo ("Kilometraje (km)") y a un texto suelto al lado del campo: la unidad es parte del campo. Es texto de lectura —no recibe foco ni viaja en el POST— y su id entra en `aria-describedby`. Primeros usos: ciclos de batería, horas de generador y kilometraje de vehículo (Mantenimiento). Verificado en claro y oscuro y a 390 px.
- **`atoms/button` — prop `iconPosition`** (`'start'` default | `'end'`): permite el ícono después del texto (p. ej. `arrow_forward` al final de "Iniciar sesión"). Sin cambios de CSS (el `gap` del flex ya aplica en cualquier orden de hijos); 100% compatible con los consumidores existentes, que no pasan la prop y siguen viendo el ícono al inicio.
- **`atoms/button` — variantes `<tono>-outline` (20/9/2026, tarea 111)**: la acción de fila que cambia de estado lleva el tono del estado al que lleva (plan de homogeneización §3.1), así que hace falta una variante por cada tono de los `TONO_POR_ESTADO` del repo: `neutral`, `success`, `info`, `warning`, `danger`, `alert` y `distintivo-2`. Las seis últimas ya existían; se agregó `neutral-outline` (`--ag-color-neutral-strong` en texto y borde, `-subtle` al pasar el mouse: el mismo par que el chip neutral). Ver, Editar y Eliminar son fijos (`info-`, `warning-` y `danger-outline`) y `PanelHomogeneoTest` los exige en las filas.
- **`molecules/theme-toggle` — rediseño completo del marcado/CSS interno**: el switch track+thumb deslizante se reemplaza por una pastilla "segmented" de dos celdas fijas (`light_mode`/`dark_mode`), la del tema activo con fondo elevado + color de acento. Sigue siendo UN control accesible (`role="switch"`, `aria-checked`) — no dos botones independientes — y `resources/js/molecules/theme-toggle.js` no cambia una línea (solo lee/escribe `data-bs-theme` y sincroniza `aria-checked`, ninguna de esas dos cosas depende del marcado interno). Este cambio aplica a los TRES consumidores actuales (login vía `auth-layout`, topbar, pie de `sidebar-nav`) a propósito: es el mismo molecule, no hay bifurcación visual por pantalla.
- **`templates/auth-layout` — redesarrollo completo**: header (logo + theme-toggle), footer (copyright + versión) ahora viven en el template (no en `login-form`) para que `seleccionar-rol.blade.php` los herede sin duplicación. Panel visual: 55%/45% split-screen foto/formulario en desktop (quita el `max-width` fijo de antes, flex ratios en su lugar para mayor flexibilidad); ~240px altura en tablet; solo formulario en mobile. Editorial: wordmark tipográfico en dos colores (texto "AGRO" + ámbar "COM"), chip verde con ícono `flight_takeoff` arriba a la derecha, regla de gradiente 64×3px verde→ámbar, headline con subheadline de 14.5px al 80% opacidad. Panel: fondo `--ag-color-bg-auth` (tintado, no blanca elevada), sin `box-shadow`.
- **`organisms/login-form` — estructura de tabs rediseñada**: dos tabs (Ingreso / Recuperar acceso) que togglean entre dos paneles. **Panel "Ingreso"**: contiene el `<form>` real de autenticación (username/password inputs en `variant="line"`, checkbox "Recordarme", link "¿Olvidaste tu contraseña?" que cambia de tab, botón submit con ícono al final). **Panel "Recuperar acceso"**: presentacional (fuera de alcance, backend no existe todavía), SIN `<form>` envuelto (esto es deliberado: `resources/js/pages/login.js` busca `[data-ag-login-form] form`, que sigue resolviendo a UN ÚNICO form porque no hay `<form>` en el panel "recuperar"). Inputs de `variant="line"`. Línea "¿No tenés cuenta?" al pie (siempre visible, fuera de tabs). Botones de cambio de tab (`[data-ag-login-switch-tab]`) estilizados sin fondo/borde, con colores de enlace (`--ag-color-accent-link` para "¿Olvidaste?", `--ag-color-primary-emphasis` para "Volver"). **Claves nuevas** en `lang/es/seguridad.php`: `auth.tagline|subheadline|sin_alta_publica`, `login.recordarme|olvido_password` (actualizar `boton_ingresando` a "Verificando acceso…"), `recuperar.*` (titulo|subtitulo|boton_enviar|volver).
- **`resources/js/organisms/login-form.js` — nuevo archivo**: maneja los tabs con patrón de delegación de eventos vanilla (cero frameworks nuevos, consistente con `theme-toggle.js`/`sidebar-nav.js`). Funciona: activar tab por click, roving tabindex (ArrowRight/ArrowLeft navega entre tabs), botones de cambio de tab (`[data-ag-login-switch-tab]`) movilizan el foco al tab correspondiente. Guard contra `DOMContentLoaded` para no romper otras páginas. Importado en `resources/js/app.js` (line de `import './organisms/login-form.js';` agregada).

**Ajustes puntuales de login (cuarta vuelta, HU-02, PR #15)** — el usuario probó el PR y pidió una ronda de retoques acotados, sin reabrir arquitectura (ni ADR 0002, ni tabs, ni el motor de sync/backend). Todos los cambios son de tokens/CSS/JS de presentación dentro del catálogo ya existente:

- **`--ag-color-bg-auth` retocado en ambos temas** (`tokens/semantic/theme-{light,dark}.css`, mismo mecanismo `color-mix()` sobre primitivos existentes, ningún hex nuevo):
  - Claro: `--ag-color-bg-auth` es alias de `--ag-color-bg` (`#F3F5F1`), el mismo fondo del resto del panel.
  - Oscuro: `--ag-color-bg-auth` es alias de `--ag-color-bg` (`#17251C`).
  - Contraste reverificado en los dos temas (ver tabla actualizada en §1.3): todos los pares texto/fondo de la superficie auth se mantienen en AA o mejor, con un caso a vigilar — `--ag-color-text-muted` (el global, gris-600) sobre el nuevo fondo claro da ~4.5:1, al filo del umbral (no una mejora sólida); sigue sin ser el token que se usa ahí, `--ag-color-text-muted-auth` (gris-700, ~7.9:1 AAA) sigue siendo el que corresponde.
- **Logo del header de `auth-layout` +15%** (`resources/css/components/auth-layout.css`): override local `.ag-auth-layout__header .ag-logo { --ag-logo-height: 2.875rem; }` (2.5rem × 1.15), aprovechando que `atoms/logo` ya expone esa custom property. `--ag-logo-height`/`.ag-logo--md` global NO cambia — sidebar-nav/topbar siguen en 2.5rem.
- **Tab "Recuperar acceso" — campo de correo, no de usuario** (`organisms/login-form.blade.php`): `name="username_recuperar"` (`type="text"`, ícono `person`) reemplazado por `name="email_recuperar"` (`type="email"`, ícono `mail`, `autocomplete="email"`). Nueva clave `seguridad.recuperar.campo_email` = "Correo electrónico"; `seguridad.recuperar.subtitulo` reescrito para hablar de correo en vez de usuario.
- **Bug de alineación del label en el tab "Recuperar" — corregido**: causa raíz confirmada, `.ag-login-form`/`.ag-login-form__panel` traen `text-align: center` global; el panel "Ingreso" lo neutralizaba porque todo su contenido vive dentro de `<form class="ag-login-form__form">` (esa clase trae `text-align: left`), pero el panel "Recuperar" no tenía ese wrapper (a propósito, sin `<form>` real). Fix: el input + el botón "Enviar solicitud" del panel "Recuperar" se envuelven en un `<div class="ag-login-form__form">` (reutiliza la clase existente por su CSS, NO agrega una etiqueta `<form>`) — `resources/js/pages/login.js` sigue viendo un único `<form>` real en toda la pantalla (el de ingreso), su selector `[data-ag-login-form] form` no se ve afectado (es un selector de tag, no de clase).
- **Más aire entre título/subtítulo e inputs, en ambos tabs**: el `<h1>` + `<p class="subtitle">` de cada panel se agrupan en un nuevo `<div class="ag-login-form__header">` con gap propio y chico (`--ag-space-2`); el gap del panel (`.ag-login-form__panel`, antes uniforme entre TODOS los hijos) sube a `--ag-space-6` para el aire mayor hacia el form/inputs. Antes un único `gap: var(--ag-space-4)` no distinguía "aire dentro del bloque de texto" de "aire hacia el formulario".
- **Íconos un poco más grandes, overrides locales (sin tocar nada global)**:
  - `resources/css/components/input.css`: `.ag-input__icon` (prefijo del campo) y `.ag-input__toggle .ag-icon` (mostrar/ocultar password) suben de `--ag-font-size-sm` (heredado de `.ag-icon--sm` del átomo) a `--ag-font-size-base` (1rem).
  - `resources/css/components/theme-toggle.css`: `.ag-theme-toggle__icon` (que ya definía su propio `font-size` aislado, sin depender de `--ag-icon--sm`) sube de `1rem` a `--ag-font-size-lg` (1.125rem).
  - `--ag-icon--sm`/`.ag-icon--sm` global (icon.css) NO cambia — lo usa el resto del panel (sidebar, topbar, menú, botones).
- **Bug real: doble ícono de "ojo" en el input de password — corregido**: no era un bug de Blade/JS (ambos ya renderizaban un solo ícono), sino los controles nativos de "revelar contraseña" que Chrome/Edge/Safari inyectan en `<input type="password">`, superpuestos al botón custom `.ag-input__toggle`. Fix en `input.css`: `::-ms-reveal`/`::-ms-clear` ocultos y `::-webkit-textfield-decoration-container` con `visibility: hidden`. El botón custom sigue siendo el único control visible/funcional.
- **Galería de 3 imágenes en el panel visual de `auth-layout`** (reemplaza la foto estática única): `public/images/drone-hero.jpg` (se mantiene, slide 1) + `drone-hero-2.jpg`/`drone-hero-3.jpg` (nuevas, misma fotógrafa/serie de Pexels, ver `public/images/CREDITS.md`), con crossfade cada 6s (`var(--ag-ease-emphasized)`) e indicadores (dots) para navegación manual. Estructura Blade: `.ag-auth-layout__slides` (3× `.ag-auth-layout__slide`, cada uno imagen+scrim+headline/subheadline propios, crossfadeados como unidad vía `opacity`) + `.ag-auth-layout__top` (wordmark/chip, **fijo, fuera del loop**, no cambia por slide) + `.ag-auth-layout__dots`. Primera imagen `loading="eager"`, las otras dos `loading="lazy"` (criterio de rendimiento: la primera es la que se ve al entrar, las otras se difieren). Bajo `prefers-reduced-motion: reduce` el auto-avance se apaga en JS (es un timer que mueve contenido solo, no un estado codificado en `transform` que deba preservarse) — la navegación manual sigue funcionando, con el crossfade acortado vía CSS (mismo criterio de "nunca anular, solo acortar duración" del resto del sistema). Copy nuevo: `seguridad.auth.galeria` (array de 3 `{headline, subheadline}` en `lang/es/seguridad.php` — reemplaza los strings sueltos `auth.headline`/`auth.subheadline`; los nombres de archivo de imagen NO viven en el lang file, los arma `templates/auth-layout.blade.php` porque no son copy) + `auth.galeria_aria_label`/`auth.galeria_dot`. JS nuevo: `resources/js/templates/auth-layout.js` (mismo patrón vanilla de delegación/inicialización que `theme-toggle.js`), importado en `resources/js/app.js`.

**Mockup de dashboard admin — catálogo nuevo + mejoras de chrome (2026-08-28)** — hay una presentación de aprobación de proyecto próxima; se pidió un mockup visual de alta fidelidad del panel de admin (dashboard con métricas, topbar con más jerarquía/notificaciones, sidebar pulido, footer mejorado, y una pantalla nueva de "Registro de la compañía" que muestra la visión de pivotar a SaaS multi-tenant — sin implementar tenancy real). Este pase es SOLO el catálogo de componentes y las mejoras a los organisms/template compartidos que ya eran de `design-ui` (`topbar`, `sidebar-nav`, `panel-layout`); el ensamblado de las pantallas concretas con datos mock queda para `frontend`:

- **`atoms/badge`** (nuevo): pill de estado, variantes `success|warning|info|danger|neutral|accent`. Decisión de contraste deliberada: el texto de las variantes de estado es SIEMPRE `--ag-color-text` (nunca el token crudo del estado, p. ej. `--ag-color-warning`, como color de texto) — se verificó que amarillo-sobre-amarillo-pálido da ~1.4:1 (falla catastrófica) y que azul/rojo rondan el límite de AA (~4.4-4.8:1) en vez de pasarlo con margen. `--ag-color-text` sobre cualquier `-subtle` (tinte pálido en claro, overlay de baja opacidad sobre superficie oscura en oscuro) pasa AAA en los dos temas sin excepción — el color de marca de cada estado se conserva en un punto decorativo (`.ag-badge__dot`, no-texto, exigencia de contraste más laxa) o en el ícono si se pasa uno, nunca en el texto. La variante "accent" reutiliza el par ya establecido y verificado en el sistema (`--ag-color-accent-subtle`/`--ag-color-accent-contrast`, el mismo que ya usan `.ag-role-badge` y `.ag-menu-item__badge`). Nuevo token semántico `--ag-color-neutral-subtle` (`surface2` en ambos temas) para la variante "neutral", que no tenía un `-subtle` propio en el sistema.
- **`molecules/stat-card`** (nuevo): tarjeta de métrica de dashboard — ícono + valor grande (`--ag-font-family-mono`, cifras tabulares, mismo criterio que el resto del sistema) + label + variante de acento (mismo set que `badge`, aplicado a una barra superior decorativa con el color crudo del estado + un chip de ícono con el mismo criterio bg-subtle/texto-seguro de `badge`) + tendencia opcional (texto ya formateado + ícono `trending_up`/`trending_down`/`trending_flat`, color éxito/peligro/muted independiente de la `variant` de la tarjeta). El grid de 4-5 tarjetas por dashboard es Bootstrap y lo arma quien componga la pantalla (`frontend`) — la molécula no se envuelve en su propio grid.
- **`atoms/switch`** (nuevo): toggle on/off de FORMULARIO real (`<input type="checkbox">`, se envía con el form) — deliberadamente distinto de `molecules/theme-toggle` (ese es presentación pura sin `name`/`checked`, atado al tema, con JS propio). Sin JS: el estado visual (track+thumb) se resuelve 100% en CSS con `:checked` + combinador de hermanos.
- **`molecules/plan-card`** (nuevo): tarjeta seleccionable tipo radio para elegir un plan de suscripción (pensada para 2-3 planes uno al lado del otro). `<input type="radio">` real, visualmente oculto pero accesible (la asociación `<label for>` nativa ya cubre el requisito de "role correcto, navegable por teclado" sin ARIA adicional) — la tarjeta completa (el `<label>`) es el target de click, elevación Material (`shadow-sm` → `shadow-lg`) al seleccionar, sin JS propio (mismo mecanismo `:checked` que `atoms/switch`). Prop `highlightedLabel` opcional compone `atoms/badge` (variante "accent") para marcar un plan destacado — quien arme el grupo de 2+ tarjetas es responsable del contenedor `role="radiogroup"` (mismo criterio de responsabilidad que `role-selector-item`, que tampoco arma su lista contenedora).
- **`organisms/topbar` — jerarquía visual + dropdown de notificaciones**: el lado derecho se reagrupa en dos bloques (`.ag-topbar__group`) separados por un divisor sutil — "utilidades" (notificaciones + tema) vs. "identidad" (badge de rol + selector de rol + usuario) — en vez de una fila plana de íconos. Nueva prop `notifications` (list de `{icon, title, time, unread}`, default `[]`): dropdown nativo de Bootstrap (mismo mecanismo que el popover de cambio de rol, popover propio `.ag-notifications-popover` porque el contenido es distinto), badge de contador de no leídas sobre la campana (`9+` si supera 9), degrada bien con `[]` mostrando el estado vacío. Dos claves nuevas en `lang/es/ui.php`: `ui.topbar.notifications`/`ui.topbar.no_notifications` — el CONTENIDO de cada notificación no se traduce acá (lo arma `frontend`, ya traducido, mismo criterio que el resto del catálogo). `templates/panel-layout` reenvía la prop `notifications` a `topbar` sin tocarla.
- **`organisms/sidebar-nav` — pulido de jerarquía/agrupación, sin cambios de props**: barra de acento a la izquierda del ítem de menú activo (`.ag-menu-item.is-active::before`, refuerza el estado más allá del tinte de fondo, visible también en icon-rail colapsado) y guía vertical a la izquierda de los ítems anidados de un `collapsible-menu-group` (`border-inline-start`, conecta visualmente hijos con su cabecera). Ambos cambios son solo CSS sobre `menu-item.css`/`collapsible-menu-group.css` — la estructura de props (`menu`/`roles`/`rolActivoId`/`id`/`collapsed`) y la mecánica de offcanvas/collapse/icon-rail no cambiaron.
- **`templates/panel-layout` — footer rediseñado**: de un único `<span>` con el copyright a tres piezas (`ui.logo.alt` en peso medio + separador `&bull;` + `ui.footer.copyright`) con más aire vertical (`--ag-space-5` en vez de `--ag-space-4`). Reutiliza las DOS claves de traducción que ya existían — ninguna clave nueva, nada de datos dinámicos reales (versión, build, git — explícitamente fuera de alcance).
- Ningún componente de este pase necesitó JS propio: `badge`/`stat-card` son presentación pura; `switch`/`plan-card` resuelven su estado con `:checked` nativo; el dropdown de notificaciones reutiliza el `dropdown` de Bootstrap ya cargado (mismo patrón que el popover de rol).

**Ensamblado de páginas + auditoría de tokens/arquitectura (2026-08-28, mismo pase)** — `frontend` ensambló `pages/dashboard`, `pages/organizacion/index` y el logout de `topbar` sobre el catálogo de arriba. Una revisión posterior (pedida explícitamente por el usuario: "verificar arquitectura, clean code, Atomic Design y separación de tokens") encontró y corrigió lo siguiente:

- **`molecules/form-section`** (nuevo, este pase): agrupador `<fieldset>` (sin chrome nativo) + `<legend>` como título + grid de una columna para el contenido — existe porque `pages/organizacion/index.blade.php` repetía el mismo `<fieldset style="...">` suelto 4 veces (una por sección del formulario mock). Props: `title` (requerido). Sin lógica, sin átomos propios (por eso molecule y no organism) — mismo criterio de "si un patrón se repite en una página, es un componente del catálogo, no markup suelto" (CLAUDE.md, sección de agentes).
- **Nueva convención `resources/css/pages/`** (nuevo, este pase): CSS que NO es catálogo Atomic Design (no lo mantiene `design-ui`, no es reutilizable entre pantallas) pero tampoco debe vivir como `style=""` inline en el Blade de una página — es el layout propio de ESA página (grids, secciones) una vez que deja de caber en un one-liner razonable. `resources/css/pages/index.css` importa un archivo por página (`dashboard.css`, `organizacion.css`), importado a su vez desde `app.css` después de `components/index.css`. Mismo criterio de tokens que el resto del sistema (cero hex/px suelto que ya tenga equivalente en la escala — ver hallazgo siguiente). El ensamblado original de `frontend` (25 `style=""` inline entre las dos páginas) se migró íntegro acá.
- **Corregido, mismo pase**: dos usos de valores crudos que duplicaban un token ya existente en vez de referenciarlo — `font-size: 1.125rem` → `var(--ag-font-size-lg)`, `font-size: 0.875rem` → `var(--ag-font-size-sm)`, `font-weight: 500` → `var(--ag-font-weight-medium)` (los tres, en los títulos de sección y el label del logo de `organizacion`). Los anchos de layout puntuales (`max-width: 600px` del form, `minmax(280px|250px, 1fr)` de los grids, `60px` del preview de logo) NO se tokenizaron — no hay (ni debe inventarse acá) un token de "ancho de contenedor" en este sistema, mismo criterio ya aplicado a `login-form`'s `max-width: 380px`.
- **`DashboardController` — "órdenes por estado" atado al vocabulario real, sin acoplar el módulo**: los 4 estados (`emitida|vigente|consumida|vencida`) son los valores reales de `App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion` — pero el controlador NO importa ese enum: Operaciones todavía no tiene `Contratos/`, y ADR 0003 exige viajar entre módulos por `Contratos/` o eventos de dominio, nunca alcanzando directo el `Dominio/` ajeno. Se optó por strings literales documentados (riesgo aceptado y explícito: si el enum cambia algún día, este mock queda desincronizado) antes que crear el acoplamiento prohibido. Las 4 etiquetas se movieron a `lang/es/operaciones.php` (nuevo — primer archivo de idioma de ese módulo), no a `seguridad.php`, para que la futura pantalla real de órdenes las reutilice sin duplicar claves.
- **Dos bugs reales encontrados por verificación en navegador (Playwright), pre-existentes desde la implementación original de HU-02 — ninguno introducido por el catálogo de este pase**:
  1. `organisms/sidebar-nav` era invisible en desktop: `.offcanvas`/`.offcanvas-start` de Bootstrap traen `position:fixed; visibility:hidden; transform:translateX(-100%)` incondicionales (sin media query), y el override responsivo `.offcanvas-lg` de Bootstrap a `min-width:992px` nunca los revierte — solo toca `background-color`/bordes. `sidebar-nav.css` ya sabía que había que "restituir con la misma especificidad" (lo hacía para `background-color`) pero le faltaba hacerlo para `transform`/`visibility`. Corregido en el mismo bloque `@media (min-width: 992px) { .ag-sidebar.offcanvas-lg {...} }`.
  2. Todos los links del menú apuntaban al nombre de ruta crudo (`href="panel.dashboard"`) en vez de la URL resuelta: `sidebar-nav.blade.php`/`collapsible-menu-group.blade.php` pasaban `data_get($item, 'ruta', ...)` directo a `menu-item`, que documenta esperar la URL YA resuelta. Corregido con `\Route::has($ruta) ? route($ruta) : $ruta` en ambos puntos de desempaquetado (el `Route::has()` guarda la tolerancia que el propio `SecMenuSeeder` ya documentaba para rutas sembradas antes de existir).
- **`organisms/login-form`** (bug encontrado antes de este pase, mismo día): no fusionaba `$attributes` en su `<div>` raíz — el `data-ag-login-form` que `pages/login.blade.php` le pasa para que `resources/js/pages/login.js` intercepte el submit se descartaba en silencio, y el login hacía un POST nativo (el navegador termina mostrando el JSON crudo de `SesionController::store`). Corregido con `{{ $attributes->class(['ag-login-form']) }}`, mismo patrón que `topbar`/`sidebar-nav`; el `max-width`/`margin-inline` que estaba inline se movió a `login-form.css`.

### 3.1. Convención de ubicación

`resources/views/components/{atoms|molecules|organisms|templates}/<nombre>.blade.php` → invocable como `<x-atoms.button>`, `<x-molecules.theme-toggle>`, etc. (resolución automática de Blade por subcarpeta). `pages/` no es una carpeta de componentes: son las vistas reales bajo `Infraestructura/Http/` de cada módulo (ADR 0008) — no le corresponde a `design-ui`.

El CSS sigue la misma frontera: `resources/css/components/` es el catálogo (un archivo por componente, mantenido acá); `resources/css/pages/` (nuevo, 2026-08-28) es CSS de UNA página concreta que ya no entra en un `style=""` razonable — no es catálogo, no es reutilizable, y NO le corresponde a `design-ui` mantenerlo (mismo criterio que `pages/` en Blade), pero sí queda documentado acá una vez porque el criterio de "cuándo migrar de inline a archivo" es del sistema de diseño, no de cada página.

## 4. Especificación de moléculas/organismos/templates pendientes

### 4.1. `role-selector` — **molecule**

Justificación de nivel: agrupa átomos (`icon` + `button`, o una lista de opciones) en una sola unidad funcional sin orquestar otras moléculas — no compone `menu-item` ni ningún otro molecule, así que no califica como organism.

- Composición: lista de opciones (una por rol vivo del usuario) + `button` atom para confirmar.
- Base: `list-group`/`form-check` de Bootstrap para la estructura de lista; hover/selección con elevación y `--ag-color-primary-subtle` de fondo (Material).
- Props: `roles` (colección `{id, name}`), `action` (URL del POST), `csrf`. Sin lógica: no decide a qué ruta postea ni valida — eso es `backend`/`frontend` (ADR 0004 extensión, punto 3).
- Texto: cero hardcodeado; nombres de rol vienen de `sec_role.name` (dominio, no se traduce, ADR 0013 punto 3), copy alrededor ("Elegí con qué rol continuar") va en `lang/es/<módulo-de-la-pantalla>.php`.

### 4.2. `theme-toggle` — **molecule**

- Composición: `icon` (sol/luna) + un control tipo switch (marcado propio, no `.form-switch` de Bootstrap, para controlar la estética Material) + `label` opcional.
- Comportamiento que sí es del átomo/molécula (presentación pura, sin llamar al backend): alternar el atributo `data-bs-theme` del `<html>` y despachar un evento de navegador `agrocom:theme-changed` con el nuevo valor (`"light"|"dark"`).
- Lo que **no** hace esta molécula: persistir la preferencia. Quien la use (un componente Livewire de `frontend`) escucha `agrocom:theme-changed` y hace el POST/guardado contra la columna de preferencia del usuario (ver §6 — esa columna todavía no existe).
- Tokens: `--ag-color-*` estándar; ícono activo usa `--ag-color-primary-emphasis`.

> **Estado actual (29/8/2026):** esta entrada describe el diseño original de
> esta HU. Pasó por un segmented control de 3 estados (§7.7, obs. #9) y
> volvió a un botón único de 2 estados en la octava vuelta — ver §12 para el
> marcado, tokens y comportamiento vigentes.

### 4.3. `menu-item` — **molecule** (el ítem hoja; ver `collapsible-menu-group` para el caso con submenú)

- Composición: `icon` + label + badge opcional (contador) + indicador de estado activo.
- Props: `label`, `icon`, `href`, `active` (bool), `permission` (código `sec_permission`, informativo — el filtrado real ya lo hizo quien arma el árbol de menú, esta molécula no consulta `sec_*`).
- Base: estructura de lista de Bootstrap (`nav-link`), color/hover Material (`--ag-color-primary-subtle` de fondo cuando `active`, `--ag-color-primary-emphasis` en texto/ícono).

### 4.4. `collapsible-menu-group` — **organism**

Justificación de nivel: orquesta múltiples `menu-item` (molecules) bajo un header colapsable con estado propio (abierto/cerrado) — combinación de moléculas con lógica de coordinación, no una unidad atómica simple.

- Composición: un `menu-item` como cabecera (con ícono de expandir/colapsar) + lista anidada de `menu-item`.
- Props: `label`, `icon`, `items` (colección de props de `menu-item`), `open` (bool inicial).
- Base: `collapse` de Bootstrap para la mecánica de expandir/colapsar (accesible, `aria-expanded` incluido gratis); estética Material en los ítems internos.

### 4.5. `login-form` — **organism**

- Composición: `logo` + dos `input` (usuario, contraseña) + `button` (submit) + slot de error general (credenciales inválidas).
- Props/slots: `action`, mensaje de error general opcional. **No implementa la autenticación** — eso es `frontend`+`backend` (login por `username`, guard `interno`, ya resuelto en ADR 0004).
- Base: `form` + grid de Bootstrap para estructura; `input`/`button` Material ya definidos en §3.

### 4.6. `sidebar-nav` — **organism**

- Composición: `logo` (variante colapsada/expandida) + lista de `menu-item`/`collapsible-menu-group` + pie con `theme-toggle` y el disparador del selector de rol.
- Props: recibe el árbol de menú **ya resuelto** (filtrado por `sec_menu`/`sec_permission` del rol activo, ADR 0004) como prop — no consulta `sec_*` directamente, para no mezclar presentación con la resolución de permisos (ADR 0008: los organisms son adaptadores delgados de presentación, la regla vive en `Aplicacion/`).
- Base: `offcanvas`/flex de Bootstrap para el colapso responsivo; fondo `--ag-color-bg-chrome`, texto/activo con `--ag-color-primary-emphasis`.

### 4.7. `topbar` — **organism** (no estaba en la lista original del pedido, pero el layout de §4.8 lo necesita)

- Composición: nombre de usuario + badge del rol activo + `button` que abre `role-selector` (modal/dropdown) + `theme-toggle`.
- Es donde vive, visualmente, "cambiar de rol activo sin volver a loguearse" (CA de HU-02) — el disparador, no la lógica: la acción real (`POST /panel/rol-activo` o el nombre que fije `backend`, ADR 0004 extensión punto 4) la implementa `frontend`.

### 4.8. `panel-layout` — **template**

- Composición: `sidebar-nav` + `topbar` + `<main>` (slot de contenido) + footer.
- Es el layout que ADR 0002 llama "layout base (sidebar + content + footer)".

### 4.9. `auth-layout` — **template**

- Composición: `logo` + tarjeta centrada + slot de contenido (usado tanto por la pantalla de login como por la de selección de rol, que comparten esta misma cáscara visual sin sidebar).
- Es el layout que ADR 0002 llama "layout de autenticación".

### 4.10. `portal-layout` — **template** (3/9/2026, tarea 55)

Justificación de nivel: análogo a `panel-layout` (orquesta `logo`, `theme-toggle` y el botón de cerrar sesión bajo una cáscara común), pero deliberadamente NO es `panel-layout` con un menú vacío — una cuenta de portal no tiene `sec_user_role` ni `sec_menu` que resolver, así que el layout de tres niveles (riel de módulos + sidebar) no aplica.

- Composición: header de una sola fila (`logo` + 3 links de navegación fijos, hardcodeados en el template porque no salen de `sec_menu` — avance/actas/reportes — + `theme-toggle` + botón de cerrar sesión reutilizando `data-ag-logout`/`resources/js/organisms/topbar.js`) + `<main>` (slot) + footer de copyright.
- Props: `userName` (nullable, de `AutorizacionPortalCliente::cascara()`), `vistaActual`.
- Persistencia de tema: recibe `temaUrl` desde `panel-shell` (ver §3, fila `panel-shell`) apuntando a `portal.preferencias.tema` en vez de `panel.preferencias.tema` — mismo `theme-toggle`, distinto guard.
- Es el layout que ADR 0002 punto 6 llama "reutiliza el mismo layout con un guard de autenticación separado, sin acceso a los menús internos".

## 5. Idioma (ADR 0013)

Convención de catálogo de claves, definida acá (era un pendiente explícito de ADR 0013):

- **`lang/es/ui.php`** — strings genéricos del sistema de diseño, reutilizables en cualquier pantalla (alt del logo, labels de acciones genéricas, textos de accesibilidad de los átomos). Ya creado con las claves que usan los átomos de §3.
- **`lang/es/<módulo-o-pantalla>.php`** — texto específico de una pantalla de negocio (copy del login, nombres/descripciones de rol tal como se presentan, textos del selector de rol). Lo crea quien construye esa pantalla (`frontend`), seleccionando la clave por módulo (p. ej. `seguridad.php` para login/selector de rol, que vive conceptualmente en el módulo `Seguridad`).
- Regla dura (ya en CLAUDE.md por extensión de este ADR): ningún átomo/molécula/organismo tiene un string de UI literal — todo pasa por `__()`. Los cuatro átomos implementados ya cumplen esto (`ui.logo.alt`, `ui.input.show_password`, `ui.input.hide_password`).

## 6. Pendiente — no es trabajo de `design-ui`, queda para quien corresponda

- ~~Instalar Bootstrap/AdminLTE + Material Symbols + Manrope/JetBrains Mono vía npm~~ — hecho por `frontend`: `bootstrap@5.3.8`, `material-symbols`, `@fontsource/manrope` y `@fontsource/jetbrains-mono` en `package.json`, importados desde `resources/css/app.css`; `bootstrap` (JS) importado completo en `resources/js/app.js` (`window.bootstrap`). `livewire/livewire` (composer) sigue pendiente.
- ~~Reemplazar el scaffold Tailwind de `resources/css/app.css`~~ — hecho por `frontend`; los tokens/componentes de `design-ui` sobrevivieron el cambio sin tocarlos.
- **Wiring de los JS nuevos de este pase en `resources/js/app.js`** (archivo reservado a `frontend`, `design-ui` no lo toca): falta agregar `import './molecules/theme-toggle.js';` y `import './organisms/sidebar-nav.js';`, mismo patrón que la línea ya existente `import './atoms/input.js';`. Sin este import, el switch de tema y el colapso a icon-rail del sidebar quedan sin comportamiento (el resto de la interacción nueva — offcanvas, collapse, dropdown — ya funciona porque depende de `window.bootstrap`, que `app.js` ya expone). → `frontend`.
- **Columna de preferencia de tema por usuario**: resuelta como `sec_user_preferencia.tema` (enum `TemaPreferencia::Claro|Oscuro`), ya implementada por `backend` en `app/Dominios/Seguridad/`. Pendiente real: el mapeo `claro|oscuro` ↔ `light|dark` (el que persiste el backend vs. el que consume `data-bs-theme`/`theme-toggle`) lo hace quien conecte el evento `agrocom:theme-changed` a la persistencia (Livewire) — no es responsabilidad de la molécula. → `frontend`.
- ~~Middleware `ResolverRolActivo` y la ruta de cambio de rol activo~~ — implementado por `backend` (`app/Dominios/Seguridad/Infraestructura/Http/Middleware/ResolverRolActivo.php`, `RolActivoController.php`).
- **Implementación de las moléculas/organismos/templates de §4** — hecho en este pase (27/8/2026). Ver tabla de arriba y las notas de composición.
- **Ensamblar las páginas concretas** (`pages/login`, `pages/seleccionar-rol`, el dashboard de cada rol) usando `auth-layout`/`panel-layout`, conectar el submit de `login-form` y los triggers de `role-selector-item`/popovers de rol a `SesionController`/`RolActivoController` (ambos responden JSON, pensados para fetch/Livewire, no submit clásico con redirect). → `frontend`.
- ~~Asset de logo transparente~~ — resuelto (rediseño de login, tercera vuelta): los dos JPEG (`logo-light.jpeg`/`logo-dark.jpeg`, fondo sólido horneado blanco/negro) se reprocesaron a un único `public/logo.png` con canal alfa real (recorte del isotipo + wordmark, extracción de alfa por chroma-key con limpieza de ruido de compresión — filtro de mediana + erosión + blur suave sobre el canal alfa, no sobre el color). Ya no hace falta variante por tema: `atoms/logo` renderiza un solo `<img>`, `logo.css` perdió la regla `[data-bs-theme]` que alternaba `display`. El wordmark editorial de `auth-layout` sigue en texto (no en esta imagen) por decisión de diseño del mockup (bicromía tipográfica "AGRO"/"COM"), no ya por la limitación técnica — ver notas de §3.
- ~~Instalar la fuente de `--ag-font-family-display` vía npm~~ — resuelto: usa Manrope, ya instalada para la interfaz (§7.3).

## 7. Quinta vuelta (28/8/2026) — layout de tres niveles del panel

Rediseño del panel completo sobre las maquetas aprobadas del mockup
el mockup de login (`4a` escritorio, `5a` tablet, `5b` móvil,
`5c` selección de rol). Tres decisiones del pedido gobiernan todo el pase:
el **layout** es el de las maquetas; los **módulos del menú** salen de la
especificación (`docs/especificacion/marketplace.md`), no del mockup; y
los **hex del mockup nunca se copian** — cada color se tradujo a un token
semántico (invariante 11).

### 7.1. Los tres niveles

| Nivel | Componente | Qué es |
|---|---|---|
| 1 | `organisms/module-rail` | Riel de módulos, 74px (64px tablet), superficie grafito **en ambos temas** (`--ag-color-bg-rail*`, constantes — chrome de marca, mismo criterio que `--ag-color-scrim*`). Solo los módulos que el rol activo puede ver; engranaje al pie → `panel.organizacion.index`. |
| 2 | `organisms/module-sidebar` | Ítems del módulo activo, 252px, solo ≥1200px: nombre en display 22px + descripción (`sec_menu.descripcion`, clave i18n nueva), lista de `menu-item` (radio 9, activo verde negrita sobre tenue, badge de pendientes en mono ámbar), pie "Cambiar de rol" (link a la pantalla 5c con `?cambiar=1`). |
| 3 | `tabs.css` (skin del `tab` nativo de Bootstrap) | Pestañas dentro del contenido (Resumen / Sesiones / Pausas en el dashboard), subrayado ámbar 2px en la activa. |

Módulos sembrados (`SecMenuSeeder`, vocabulario de la especificación):
**Operación** (4.3), **Comercial** (4.1 + cap. 9), **Recursos** (4.2),
**Mantenimiento** (4.5), **Financiero** (4.4 + cap. 11), **Reportes**
(cap. 9/10) y **Seguridad** (4.6 + cap. 14). Las tres pantallas existentes
siguen en el árbol (pedido explícito): Inicio → Operación › Programación
(misma fila migrada, id estable), Usuarios y Organización → Seguridad.
`panel-layout` normaliza el árbol (resuelve `route()`, marca activo por
ruta actual, cuelga los badges demo) — `ObtenerMenuPorRolActivo` sigue sin
saber de rutas resueltas.

### 7.2. Breakpoints (768 / 1200)

| Rango | Maqueta | Qué cambia |
|---|---|---|
| ≥1200 | 4a | Riel 74px + sidebar 252px + header 62px completo (breadcrumb · buscador flexible `flex:1 1 300px` con ⌘K · chip campaña · período · campana · toggle de tema · usuario con rol activo debajo). Tabla de sesiones en grilla de columnas. |
| 768–1199.98 | 5a | Riel 64px; el sidebar desaparece → banda de módulo (nombre + **píldoras horizontales** `flex:0 0 auto; white-space:nowrap`, nunca el ítem vertical de `width:100%`); header compacto 58px; KPIs 2×2; tabla → lista de dos líneas. |
| <768 | 5b | Web, no app: sin barra inferior ni FAB. Header oscuro (`mobile-topbar`: hamburguesa → `module-drawer` offcanvas con los módulos colapsables, logo, `ROL · CAMPAÑA` en mono lima, campana, avatar) + banda de breadcrumb con lupa; KPI protagonista (cifra 2.5rem) + 2 secundarios; sesiones como fichas; pie mono `AGROCOM SRL · año` + versión. Objetivos táctiles ≥44px. |

El chrome no scrollea, el contenido sí: `.ag-panel` fija `100dvh` y cada
columna flex interna lleva `min-height: 0` (sin eso el hijo flex nunca
encoge y el scroll interno no aparece); las tarjetas que no deben encogerse
van `flex: 0 0 auto`.

### 7.3. Tipografía — dos familias, una por contexto

Las mismas de la app móvil, servidas desde el propio bundle (`@fontsource/manrope` y `@fontsource/jetbrains-mono`, importadas en `resources/css/app.css`; sin CDN):

- **Manrope** (400, 500, 600, 700, 800) para la interfaz, los subtítulos y los titulares. `--ag-font-family-base` y `--ag-font-family-display` resuelven a Manrope; el titular se distingue por peso (700 a 800) y tamaño, no por una familia aparte. Está cableada a `--bs-body-font-family` para que nada caiga al stack del sistema.
- **JetBrains Mono** (400, 700) para cifras y metadatos: KPI de `stat-card`, horas, drones, `ROL · CAMPAÑA`, valores de leyenda. `--ag-font-family-mono`. Las cifras llevan `font-variant-numeric: tabular-nums` para alinear en columna.

El título de pantalla (`--ag-title-page-*`) usa peso 800. Los íconos son Material Symbols **Rounded** (`material-symbols/rounded.css`, clase `material-symbols-rounded` en `atoms/icon`).

### 7.4. Tokens del layout de tres niveles

Semánticos (mismo nombre en ambos temas, valores reasignados desde `brand.css`):

| Token | Uso |
|---|---|
| `--ag-color-bg` | Fondo del contenido del panel; `--ag-color-bg-auth` es su alias |
| `--ag-color-bg-rail`, `-bg-rail-active`, `-text-rail`, `-text-rail-active` | Riel de módulos, **constantes entre temas** (grafito, mezcla de grafito con blanco al 10 %, blanco al 70 %, verde claro) |
| `--ag-color-surface-card`, `--ag-color-border-card` | Tarjeta del panel (KPI, tablas, fichas) y su borde, separados del chrome |
| `--ag-color-bg-table-head`, `--ag-color-border-row`, `--ag-color-bg-row-hover` | Cabecera mono de tabla, borde de fila, hover de fila |
| `--ag-color-bg-input-chrome` | Relleno del buscador del header y de las píldoras inactivas |
| `--ag-color-*-strong` | Texto de los chips de estado sobre su propio `-subtle`; cada par verificado AA en §1.3 |
| `--ag-color-primary-border-subtle` | Borde tenue verde (chip de campaña, píldora o filtro activo) |
| `--ag-color-text-faint` | Decorativo (íconos apagados, barras); no se usa para texto de lectura |
| `--ag-color-track`, `--ag-color-bar-unassigned` | Pista de las barras de pausas y barra «sin causa asignada» |

### 7.5. Contraste

Los ratios de cada combinación están en §1.3. Regla operativa: **un chip de estado usa siempre el par `-subtle` (fondo) + `-strong` (texto)**, nunca el token crudo como texto; el ámbar como texto sobre superficie clara es siempre `--ag-color-accent-emphasis` o `--ag-color-warning-strong`, porque el ámbar puro no llega a AA sobre blanco.

### 7.6. Catálogo — altas y bajas

Altas: `organisms/module-rail`, `organisms/module-sidebar`,
`organisms/module-drawer`, `organisms/mobile-topbar`,
`molecules/alert-strip` (variantes accent/danger — ventana volable y alerta
de RC), `molecules/role-card` (tarjeta 5c con chips de permisos y badge
"ÚLTIMO USADO"), `tabs.css`, `templates/panel-shell` (esqueleto HTML único
de las páginas del panel, con `data-bs-theme` desde la preferencia
persistida). Reescritos: `templates/panel-layout` (tres niveles),
`organisms/topbar` (header 62px), `molecules/stat-card` (anatomía KPI de la
maqueta), `atoms/badge` (pares subtle/strong, sin dot).

Bajas: `organisms/sidebar-nav` (+ su JS de icon-rail),
`molecules/role-selector-item` y el Livewire `RoleSwitcher` con sus
popovers — el cambio de rol ahora es siempre la pantalla 5c (link "Cambiar
de rol" del sidebar/menú de usuario, con `?cambiar=1`).

### 7.7. Selección de rol y preferencias (backend que acompaña)

- `sec_menu.descripcion` (clave i18n de la bajada del módulo) y
  `lang/es/menu.php` nuevos.
- `sec_user_preferencia.rol_preferido_id` ("Entrar siempre con este rol":
  login y middleware lo activan solos y el selector solo reaparece con
  `?cambiar=1`) y `ultimo_rol_id` (badge "ÚLTIMO USADO", lo registra
  `ElegirRolActivo`, único punto de activación). Ninguno gobierna permisos
  y ambos se revalidan como "rol vivo" antes de usarse (ADR 0004 intacto).
- `PresentadorRol`: nombre legible/ícono/chips por slug (metadata de
  presentación en `seguridad.rol.meta.*`, con fallback al vocabulario crudo
  de la base — no traduce dominio, ADR 0013).
- El tema ahora PERSISTE: `POST panel.preferencias.tema` (oyente de
  `agrocom:theme-changed` en `theme-toggle.js`) + `panel-shell` renderiza
  `data-bs-theme` desde la preferencia.
- Datos de demo de las maquetas centralizados en
  `Http/Demo/DatosDemoPanel` (KPIs, 5 sesiones, 5 causas de pausa, 3 ítems
  de stock, ventana volable, badges del menú, campaña/período/versión) —
  marcado MOCK, nunca hardcodeado en vistas; usuario demo multirol
  `miguelo`/`0000` en `AdminPlataformaSeeder`.

## 8. Reglas fijas de pulido UI (sexta vuelta, 28/8/2026)

Reglas derivadas de la sesión de login + `seleccionar-rol` del 28/8/2026 y
de las vueltas siguientes, para aplicar por defecto en cualquier componente
nuevo del catálogo en vez de redescubrirlas por prueba y error en cada
pantalla. La lista crece: cada vez que una regla se descubre mirando una
pantalla ya hecha, se anota acá.

1. **Tipografía display: `font-weight` siempre explícito.**
   `.ag-login-form__title` no lo declaraba (heredaba el 500 de Bootstrap
   Reboot); `.ag-role-select__title` sí, pero en
   `--ag-font-weight-regular` (400) a 2rem — un peldaño más chico/liviano
   que el login pese a compartir `auth-layout`. Igualados a 38px +
   `--ag-font-weight-bold` explícito en ambos. Regla: todo consumidor de
   `--ag-font-family-display` declara `font-weight` con el token, nunca
   depende del peso por defecto de Bootstrap para headings.

   **Corolario (7/9/2026), el que faltaba escribir:** un `<h1>` SUELTO no
   consume la display en absoluto — se queda con el `font-family` base y el
   peso 500 de Reboot, y al lado de una pantalla con `page-header` se lee
   como otra tipografía. Siete pantallas habían quedado así (trabajos,
   detalle y evidencias de trabajo, validación de sesiones, alertas,
   versiones del APK, dispositivos). Regla: el título de una pantalla del
   panel se declara con `<x-organisms.page-header :title="…" />`; un `<h1>`
   propio solo si lleva clase con la receta `--ag-title-page-*` (el caso del
   dashboard y de las dos pantallas de `auth-layout`). Con compuerta
   automática en `tests/Unit/PulidoNavegacionPanelTest.php`.
2. **Color de "estado seleccionado" vs. contenido informativo repetido —
   mismo eje, nunca ámbar para lo segundo.** En `role-card`, se probó
   ámbar de marca en los chips de permisos (dos intentos, incluyendo
   también el ícono) y ambos se revirtieron por feedback del usuario.
   Regla fija: el estado de selección de una tarjeta (ícono, borde) y
   cualquier chip informativo que se repite fila a fila comparten el
   mismo eje gris↔verde (nunca ámbar); para no repetir el mismo verde del
   borde/ícono (`--ag-color-primary`), el chip interno usa el par
   `success` (`--ag-color-success-strong`/`-subtle` + borde
   `--ag-color-primary-border-subtle`). El ámbar de marca queda reservado
   para acentos editoriales puntuales que NO se repiten por fila (badge
   "ÚLTIMO USADO", link "¿Olvidaste tu contraseña?").
3. **Todo chip/pill lleva borde del mismo tono que su texto** — no solo
   fondo tenue + texto (se veía "plano, sin relieve" sin él).
4. **Un chip/contenedor de texto sobre un fondo ya tintado
   (`--ag-color-bg-auth` u otro) necesita borde propio** para no
   fundirse — el `-subtle` solo no alcanza cuando la superficie base ya
   tiene color.
5. **Hover de una acción secundaria en texto plano (sin botón/fondo por
   defecto) necesita fondo sutil + transición**, no solo cambio de color
   de texto.
6. ~~**Transición nativa entre navegaciones del mismo flujo**:
   `@view-transition { navigation: auto; }` declarado una sola vez en
   `app.css` (transversal, no por template).~~ **CORREGIDA (7/9/2026,
   grabación de pantalla del usuario navegando el menú).** "Transversal"
   fue el error: `@view-transition` es una regla de DOCUMENTO y no se acota
   por selector, así que en el bundle que cargan todas las pantallas
   aplicaba a TODA navegación same-origin — cada clic del menú del panel,
   que no es "el mismo flujo" y nunca la pidió. Ahí el fundido sostenía
   ~250 ms en pantalla el estado a medio cargar de la página entrante, y
   volvía bien visible el salto de layout de los íconos (regla 10 de abajo).
   Regla vigente: la declaración vive en `resources/css/transicion-vista.css`,
   una entrada de Vite aparte que incluyen solo las dos páginas del salto
   login → selección de rol (`pages/login.blade.php` y la prop
   `transicion-de-vista` de `templates/panel-shell`). El bloque
   `prefers-reduced-motion` sobre `::view-transition-*` viaja con ella.
   Cuando una regla es de documento, "declararla una sola vez" y
   "declararla para todos" son la misma cosa — hay que elegir a quién se le
   carga, no dónde se escribe.
7. ~~**Cifra grande de KPI: mono, nunca la display.**~~ **SUPERADA — ver §10
   (auditoría visual externa, obs. #7).** El mono abría demasiado el
   tracking de una cifra de varios dígitos ("Bs 18.490"). Regla vigente:
   toda cifra grande de KPI/métrica usa `--ag-font-family-base` +
   `font-variant-numeric: tabular-nums` (dígitos de ancho fijo, sin el
   tracking abierto del mono) — la display sigue reservada para titulares
   editoriales, eso no cambió. Los usos MONO CHICOS (horas, drones,
   `ROL · CAMPAÑA`, valores de leyenda) no cambian, siguen en mono.
8. ~~**Variantes de una misma franja/alerta comparten anatomía (gradiente +
   borde izquierdo).**~~ **SUPERADA — ver §10 (auditoría visual externa,
   obs. #3).** El gradiente diagonal se veía como un artefacto de render en
   tema oscuro (un filo duro donde el degradado cortaba, no un degradado
   limpio). Regla vigente: las variantes de `alert-strip` comparten
   anatomía de superficie PLANA (`background: var(--ag-color-{variante}-subtle)`)
   + borde izquierdo 3px — el principio de "una sola anatomía, el tono
   cambia el color" se mantiene, solo cambió CUÁL es esa anatomía.
9. **Título de card: siempre sans/bold/`font-size-sm`.** (Auditoría visual
   externa, §10.6 — regla nueva, no superada.) Las tres cards del dashboard
   (Programación de hoy, Pausas por causa, Stock) ya lo cumplían antes de
   esta vuelta; se fija acá para que todo consumidor nuevo de
   `.ag-card__title` lo herede sin tener que redescubrirlo por prueba y
   error.
10. **Todo ícono de fuente reserva su caja (7/9/2026).** `atoms/icon` fija
    `width: 1em` + `display: inline-block`. Material Symbols declara
    `font-display: block`: hasta que la fuente aplica, el navegador maqueta
    el `<span>` con la LIGADURA como texto corriente ("calendar_month",
    "assignment") en la fuente de respaldo — invisible, pero midiendo
    decenas de px cada una. Ese ancho fantasma empujaba el `min-content` de
    `module-sidebar` por encima de su `flex: 0 0 252px` (medido sobre la
    grabación: 252 → 307 → 252 px), y en CADA navegación del panel los
    ítems del menú "nacían" corridos a la derecha y saltaban a la izquierda
    al cargar la fuente. 1em es el avance EXACTO de todos los glifos de la
    fuente (960/960 unidades), así que reservarlo no recorta ninguno; y
    nada de `overflow: hidden`, porque el ink del glifo va de -0,095em a
    1,002em sobre la línea base y se comería el borde inferior. Regla: un
    ícono por ligadura sin caja reservada es un salto de layout garantizado
    en cada carga, no un detalle de la primera. Con compuerta automática en
    `tests/Unit/PulidoNavegacionPanelTest.php`.

11. **Un campo en una fila de controles va sin su margen de apilado
    (8/9/2026).** `.ag-input`, `.ag-select`, `.ag-textarea`, `.ag-date`,
    `.ag-switch` y `.ag-checkbox` declaran `margin-bottom: var(--ag-space-4)`
    porque su caso normal es un formulario apilado. En una barra de filtros
    —o en cualquier fila con `align-items: flex-end` y un botón— ese margen
    es un borde fantasma: el botón se alinea contra él y queda 16px por
    debajo del campo. `components/filter-bar.css` lo anulaba nombrando solo
    `.ag-input`, así que cuando la tarea 76 migró los 70 selects del panel al
    átomo `atoms/select` (raíz `.ag-select`, otra clase) el bug volvió entero
    en las 29 pantallas con filtros; y como cada `pages/*.css` fijaba su
    `min-width` nombrando también `.ag-input`, los desplegables quedaron
    además más angostos que su propia etiqueta. Regla: la fila anula el
    margen de TODOS los átomos de campo y les fija el mismo `min-width`, no
    del que hoy se use.

    **Compuerta retirada (22/9/2026, tarea 126).** La regla en sí sigue
    vigente para cualquier fila de controles en línea nueva (ver
    `resources/css/pages/reparto-cuadrillas.css` para un ejemplo vivo), pero
    dejó de tener gate automático: `.ag-filtros`/`components/filter-bar.css`
    —el archivo contra el que `tests/Unit/PulidoNavegacionPanelTest.php`
    comparaba cada átomo— se borró al quedar sin una sola pantalla que lo
    usara (Desempeño de persona, la última,
    pasó a `filter-panel`). `filter-panel` apila sus campos verticalmente
    dentro del desplegable, así que el margen de apilado ahí es el
    comportamiento CORRECTO, no el bug — no hay un archivo equivalente que
    proteger con una compuerta general. Un campo nuevo en una fila en línea
    se revisa a mano contra esta regla.

12. **Una fila repetible (contactos, ventanas, lotes) COMPARTE la clase
    `.ag-form-section__body`, nunca redeclara su propio grid de dos columnas
    (14/9/2026).** El primer síntoma fue de ancho: cuatro páginas
    (`clientes`, `contratos`, `siembra`, `campos`) tenían cada una su propio
    `.ag-‹página›-form__‹fila›` con `grid-template-columns: repeat(auto-fit,
    minmax(‹piso›, 1fr))` copiado a mano — `.ag-clientes-form__contacto`
    (tarea 33) es el original, y el resto se escribió citándolo como molde
    ("mismo patrón que clientes/_contacto-fila.blade.php") sin extraer nunca
    un componente. Cuando el grid de `.ag-form-section__body` ganó el techo
    en 50% que lo cap a dos columnas (PR #180,
    `minmax(max(‹piso›, calc(50% - var(--ag-space-4) / 2)), 1fr)`), el PR
    solo tocó `components/form-section.css` — las cuatro copias de página
    quedaron atrás, y en pantallas anchas sus 2 a 4 campos reales (tipo/
    nombre/teléfono, código/hectáreas, hora_inicio/hora_fin, cultivo/
    hectáreas/fechas) quedaban angostos a un ancho fijo en vez de repartirse
    en dos columnas parejas. El usuario lo reportó mirando `clientes/crear`
    y `lotes/crear` a la vez —`lotes/_formulario.blade.php` reusa el mismo
    partial y clase de `campos/_lote-fila.blade.php`, así que el bug se veía
    ahí sin que esa página tuviera ninguna fila repetible propia.

    Parchear las cuatro copias agregando el `calc(50%` a cada una habría
    dejado el problema de fondo intacto: es exactamente la duplicación que
    Atomic Design existe para evitar (ADR 0002) — cuatro implementaciones
    del mismo patrón visual que solo coinciden porque alguien copió bien, y
    que un quinto formulario puede volver a divergir sin que nada avise.
    Corrección real: el elemento raíz de la fila lleva la clase
    `ag-form-section__body` ADEMÁS de su clase propia — la hoja de la página
    ya NO declara `display: grid` ni `grid-template-columns` para esa fila,
    solo lo que es genuinamente distinto de la tarjeta (el separador entre
    una fila y la siguiente, algún `align-items` puntual como en
    `.ag-siembra-form__lote`). El piso de 12rem que tenían las cuatro copias
    (contra los 14rem del componente) también desaparece: era otra
    divergencia de la misma copia manual, no una decisión de diseño por
    página. Con DOS compuertas automáticas en
    `tests/Unit/PulidoNavegacionPanelTest.php`: una recorre `resources/css/`
    y rechaza cualquier selector con "form" en el nombre que declare su
    propio `auto-fit`+`minmax(` (con o sin el techo — ya no hace falta
    declararlo nunca más en una página), y la otra recorre las filas
    repetibles (`pages/**/_*-fila.blade.php`) y exige que su elemento raíz
    lleve la clase `ag-form-section__body`.

13. **Un popover/dropdown-menu con `display: block !important` (animado por
    opacity/transform, no por el `display` nativo) fija `position: fixed`
    en su propia regla base, no solo la que Popper aplica al abrir
    (16/9/2026).** Los cinco popovers del panel que usan esta técnica
    (`.ag-notifications-popover`, `.ag-user-menu` en `topbar.css`,
    `.ag-row-actions__menu`, `.ag-filter-panel__menu`,
    `.ag-color-swatch-field__menu`) la comparten para poder animar con
    transición en vez del salto de `display: none → block` — pero al forzar
    `display: block` SIEMPRE, el menú queda maquetado en el DOM incluso
    cerrado/invisible (`opacity: 0; visibility: hidden`), y antes del primer
    `show()` de Bootstrap (que es cuando recién se crea el Popper y se le
    aplica `position: fixed` inline, ver `app.js`) el menú vive con el
    `position: absolute` por defecto de Bootstrap. Un elemento
    `position: absolute` SÍ cuenta para el `scrollWidth`/`scrollHeight` del
    ancestro con overflow (`.ag-panel__content`, que por CSS solo declara
    `overflow-y: auto` pero la spec fuerza el otro eje también a `auto`
    cuando no es `visible` en ambos) aunque esté invisible — deja un
    desborde LATENTE, invisible hasta que algo lo revela.

    Se encontró en Propiedades: con la grilla de 23 swatches y el botón
    "Cambiar" cerca del borde derecho del formulario, ese desborde latente
    rondaba ~200px. Al elegir un color, el navegador enfoca el
    `<input type="radio">` real (visualmente oculto pero interactivo) y
    dispara su scroll-into-view nativo sobre `.ag-panel__content`, que salta
    al máximo scroll posible — exactamente el tamaño del desborde latente —
    y ese `scrollLeft` NO se resetea al cerrar el popup: la página queda
    corrida a la izquierda con una franja vacía a la derecha. Mismo
    mecanismo con el que Cliente/Contrato ya se habían topado antes (ahí en
    el eje vertical, con `filter-panel`/`row-actions`), aunque el fix
    anterior (pre-instanciar con `strategy: 'fixed'` en `app.js`, 15/9/2026)
    solo corrige la posición MIENTRAS el Popper está activo — no evita el
    desborde latente del estado cerrado, que es una causa distinta y
    anterior en el tiempo.

    Regla: todo popover/dropdown-menu de este catálogo que use
    `display: block !important` para animarse declara `position: fixed`
    en su propia regla base (no solo en la config de Popper) — así queda
    excluido del cálculo de overflow del contenedor en TODO momento, abierto
    o cerrado, y no solo mientras Popper lo reposiciona. Sin compuerta
    automática todavía (es un caso de `tests/Visual/`, que no corre —
    ver skill `verificacion`, "Qué NO cubre la cascada"); verificado a mano
    con Playwright contra el compose real, los 5 popovers, antes y después
    del fix.

## 9. Sexta vuelta — parte 2 (28/8/2026): rediseño del dashboard

Ejecuta el plan de rediseño del dashboard: anexo A y fases 1 a 7 (queda solo la
Fase 8, auditoría final, que es este mismo cierre).
Verificado en navegador (Playwright, claro/oscuro/móvil, usuario
`miguelo`) antes de cerrar cada fase.

- **Fase 1 — sidebar (nivel 2)**: collapse/expand nuevo (botón hamburguesa
  en `.ag-module-sidebar__header`, JS propio
  `resources/js/organisms/module-sidebar.js`, estado en localStorage —
  72px colapsado, oculta título/descripción/labels/badges). Badge de
  `menu-item` separado en `{numero, texto}`: el pill solo pinta el número,
  el texto completo se resuelve como tooltip nativo de Bootstrap
  (`data-bs-title`, inicializado globalmente en `app.js` — Bootstrap NO
  auto-inicializa tooltips como sí hace con collapse/dropdown/offcanvas).
  `DatosDemoPanel::badgesMenu()` cambió de forma
  (`array<string, array{numero, texto}>`) — mismo criterio que tendrá el
  caso de uso real. Nuevo token `--ag-color-accent-border-subtle` (borde
  del badge, regla §8.3/§8.4: un chip sobre el chrome ya tintado necesita
  borde propio).
  **Trampa encontrada**: `data_get($menuBadges, "{$label}.numero")` NO
  funciona — `$label` ya es la clave completa (p. ej.
  `"menu.operacion.items.programacion"`) y `data_get()` interpreta sus
  puntos como un path anidado. Acceso directo al array
  (`$menuBadges[$label]['numero']`), nunca `data_get()` sobre una clave que
  ya trae puntos.
- **Fase 2 — header**: dos bloques CSS explícitos
  `.ag-topbar__left`/`.ag-topbar__right` (antes dependía solo del `flex:1`
  del buscador). Derecha, en el orden pedido: usuario, tema, notificación,
  período, campaña.
- **Fase 7 — contraste sistémico en oscuro**: `--ag-color-bg-elevated`,
  `--ag-color-bg-chrome` y `--ag-color-surface-card` eran EL MISMO valor
  (el verde oscuro de fondo) — contra `--ag-color-bg`
  (`bg-auth`) la diferencia real era de ~2 unidades de RGB, imperceptible
  (confirmado con captura real, no solo cálculo). Reemplazado por una
  escala de elevación tipo Material dark theme (overlay de blanco creciente
  sobre `--ag-color-bg-auth`, no mezclas de marca independientes que
  convergían al mismo tono): chrome 4%, tarjeta 8%, cabecera de
  tabla/hover/input 12%, borde de tarjeta 20%, borde de fila 16%, pista de
  progreso 6% (deliberadamente por DEBAJO de la tarjeta — es un inset, no
  una superficie que deba "flotar"). El riel (`--ag-color-bg-rail`,
  constante entre temas) no se tocó. Solo `theme-dark.css` — `theme-light.css`
  no tenía la queja y no se tocó.
- **Fase 3 — KPI cards + sectorización**: molecule nuevo `section-head`
  (barra 4px + rótulo uppercase + contador mono, §2.1 de la referencia).
  `stat-card` gana prop `state` (success|warning|danger|info|null,
  independiente de `footTone`): colorea el contenedor del ícono (34×34,
  antes el ícono flotaba sin contenedor) y, SOLO para warning/danger, pinta
  una barra izquierda de 4px — success/info/null quedan sin barra, mismo
  criterio que la referencia (no todo estado necesita gritar). Datos mock:
  `DatosDemoPanel::kpis()` gana la clave `estado` por KPI.
- **Fase 4 — gráfica mock**: molecule nuevo `donut-chart` (anillo hueco
  `conic-gradient` + leyenda, CERO librería — §2.3 de la referencia).
  Reutiliza los tonos categóricos ya existentes (success/warning/info/
  neutral, mismo vocabulario que `variante` en las filas de sesiones) — sin
  paleta propia. Dato: `DatosDemoPanel::distribucionSesiones()`,
  consistente con el KPI "Sesiones validadas 42/48" (42 validadas + 6
  repartidas entre los otros tres estados). Ubicado antes de "Programación
  de hoy"/Pausas/Stock, pedido explícito.
- **Fase 5 — orden de alertas**: el aviso de RC (`danger`, el más crítico)
  pasó del pie de la página al inicio, antes que la franja de ventana
  volable (`accent`) — criterio: `danger` siempre antes que `accent`/
  `warning`.
- **Fixes de feedback directo (durante Fase 3/5)**: (a) la cifra de
  `stat-card` pasó de `--ag-font-family-display` a `--ag-font-family-mono`
  — competía con el titular "Operación de hoy" (misma fuente); (b)
  `alert-strip--danger` pasó de recuadro completo (border 1px, radio 12px
  parejo) a la MISMA anatomía que `--accent`/`--warning` (gradiente + borde
  izquierdo 3px + esquinas 0/10/10/0) — dos variantes de un componente
  deben compartir estructura, solo cambia el tono. Ambas reglas quedaron
  fijadas en §8.7/§8.8.
- **Fase 6 — detalle de Sesiones/Pausas** (decisión confirmada: enriquecer
  los TABS existentes, no rutas propias — no toca `SecMenuSeeder`).
  - Tab Sesiones: columna RC nueva (`operaciones.sesion.rc_estado`:
    capturado|sin_evidencia|no_aplica — "no_aplica" es una sesión que
    todavía no vuela, no una tercera variante de falla) + drill-down: cada
    fila es un `<button>` (`.ag-table__row--clickable`) que abre un
    offcanvas nativo de Bootstrap (`.ag-session-detail`, `offcanvas-end`,
    cero JS propio) con orden/mezcla/preparado por/condiciones/pausas de
    la sesión/captura del RC — cumple la promesa que ya traía
    `sesiones_nota` (existía como texto desde la quinta vuelta, sin panel
    real detrás). Nuevo parcial `_detalle-sesion.blade.php`. `_tabla-sesiones`
    gana el prop opcional `$conRc` (default false, el tab Resumen sigue
    igual que siempre) y `.ag-table--detallado` (7ª columna).
  - Tab Pausas: tabla nueva de eventos individuales (bajo el agregado por
    causa) — `_tabla-eventos-pausas.blade.php`, `.ag-table--eventos` (4
    columnas), dato `DatosDemoPanel::pausasEventos()`.
- **Fixes de feedback directo (post Fase 6)**: `alert-strip` pasó de 2
  variantes (`accent`/`danger`, distinta anatomía cada una) a las 4
  variantes semánticas del catálogo (`warning`/`danger`/`success`/`info`,
  MISMA anatomía) — "accent" era el nombre de marca de lo que siempre fue
  visualmente un warning, se renombró. El gradiente de las 4 ahora sostiene
  el color al 100% hasta el 55% del ancho antes de apagarse hacia
  `--ag-color-bg-table-head` (antes el color se apagaba desde el borde
  mismo y se leía como un filo fino, no como el protagonista de la
  franja).

## 10. Auditoría visual externa (28/8/2026) — respuesta a 9 observaciones

Cierra la Fase 8 (auditoría final) del plan de rediseño del dashboard,
que había quedado pendiente, y la amplía con una revisión de diseño externa sobre
el panel completo (tokens de color, contraste AA, alertas, botones, gráfica
de distribución, grilla, tipografía, elevación en oscuro, detalles menores).
Rama `feature/auditoria-visual`, plan completo en el historial de la sesión.
Tres observaciones de la revisión no aplicaban (ya resueltas por trabajo
previo, no se tocó código): la rampa neutra ya estaba teñida de la marca,
no en grises fríos, el padding de KPI vs. tarjeta de distribución ya era el
mismo (`var(--ag-space-4)`), y el azul de "En vuelo" ya era
`--ag-color-info` del catálogo semántico.

### 10.1. Sistema de color

Los valores y contrastes del sistema de color viven en §1.2 y §1.3. Reglas de consistencia:

1. **`success` ≠ `primary`.** El éxito usa el verde de marca como relleno pero su texto tenue es `on-primary-container`, distinto del `-emphasis` de enlaces y estados activos.
2. **Patrón completo por estado.** Cada estado expone `-subtle`, `-strong`, `-border` y `-contrast-fill`, de modo que `atoms/badge` puede dar borde a los cuatro por igual (regla §8 punto 3).
3. **El relleno sólido de botón no depende del tema.** `.ag-button--danger` usa `-danger-contrast-fill` (5,3:1 con blanco), nunca el rojo plano del tema oscuro, que está calibrado para texto.

### 10.2. Botón "Resolver" en oscuro (obs. #2)

`.ag-button--danger` usaba `background: var(--ag-color-danger)` — en
oscuro ese token es un rojo claro, calibrado para TEXTO sobre superficie
oscura, no para relleno sólido (blanco sobre ese rosa claro se leía
"deshabilitado"). Nuevo `--ag-color-danger-contrast-fill` (constante entre
temas, mismo criterio que `--ag-color-accent-contrast-fill`, usa
un rojo de marca oscurecido) como `background` del botón. Verificado en navegador,
ambos temas.

### 10.3. Alertas (obs. #3) — superó la regla §8.8 anterior

- Degradado diagonal → superficie plana (`background: var(--ag-color-{variante}-subtle)`)
  + borde izquierdo 3px, misma anatomía para las 4 variantes. El degradado
  se veía como un artefacto de render en oscuro (filo duro, no un
  degradado limpio). §8.8 marcada como superada, ver ahí.
- `.ag-alert-strip__body` pasó de `flex:1` a `flex:0 1 auto; max-width:75ch`
  — antes se estiraba para llenar todo el ancho disponible, alejando el
  botón de acción del texto en monitores anchos. El fondo de la franja
  sigue ocupando el 100% del ancho (`.ag-alert-strip` es block-level).
- La alerta de "ventana volable" (antes un segundo `alert-strip` warning de
  igual peso visual que el aviso de RC, bloqueante) baja a una tira
  compacta (`.ag-dash__ventana-chip`) junto a `.ag-dash__subtitle` —
  `alert-strip` sigue existiendo como componente para RC y Pausas, no se
  reemplazó.

### 10.4. Un solo botón sólido sobre el pliegue (obs. #4)

Nueva variante `atoms/button` `danger-outline` (mismo patrón que
`outline`: transparente + borde + hover con `-subtle`, coloreado con
`danger`). El botón "Resolver" de la alerta de RC pasa de `variant="danger"`
sólido a `variant="danger-outline"`. La acción de "ventana volable" ya no
es un `atoms/button` (ver 10.3) — quedó como link de texto
(`.ag-dash__link`), inherentemente sin relleno. Resultado: "Programar
sesión" es el único botón sólido/primario visible sobre el pliegue;
"Exportar" sigue outline.

### 10.6. Cifras

`.ag-stat-card__value` usa `--ag-font-family-mono` (JetBrains Mono) con `font-variant-numeric: tabular-nums`: el número es el protagonista y los dígitos de ancho fijo se alinean en columna. El título de card (`.ag-card__title`) es sans, bold, `font-size-sm`.

### 10.7. Grilla y alineación (obs. #6)

- `.ag-topbar__left` pasa de `flex:1 1 auto` a `flex:0 0 auto`;
  `.ag-topbar__right` gana `margin-left:auto` para anclarse al extremo —
  antes `__left` se estiraba de más y dejaba un vacío antes del bloque
  derecho. El buscador (que vive dentro de `__left`) ya no crece con el
  espacio sobrante del header; su `flex-basis`/`max-width` suben de 300/400
  a 480px para no quedar angosto ahora que su ancho es prácticamente fijo.
- `.ag-dash__grid` pasa de `1.55fr 1fr` a `2fr 1fr` (más cerca del 8/4 de
  12 columnas pedido), `align-items:start` sin cambios.

### 10.8. Elevación en tema oscuro (obs. sobre contraste general) — SIN CAMBIOS

Reverificado con capturas reales (Playwright, dashboard, `camila.rojas`,
oscuro) antes de tocar cualquier valor: el sistema de overlays de la Fase 7
del rediseño anterior (4%/8%/12%/20% sobre `--ag-color-bg-auth`) sigue
siendo suficiente tras todos los cambios de esta vuelta — borde + superficie
distinguen la tarjeta del fondo con margen claro en la captura. No se tocó
`theme-dark.css` para esto. `.ag-stat-card` ya aplica
`border: 1px solid var(--ag-color-border-card)`.

### 10.9. Detalles menores (obs. varias + pedidos directos del usuario, 28/8/2026)

- **Theme-toggle de 3 vías** (Claro/Oscuro/Sistema). El switch binario
  (`role="switch"`) no representa 3 opciones mutuamente excluyentes — pasa
  a `role="radiogroup"` con 3 `role="radio"` (un `<button>` por celda). El
  estado activo ya no se lee de `[data-bs-theme]` (solo conoce claro/oscuro
  RESUELTOS, nunca "sistema") sino de un atributo propio,
  `[data-ag-theme-preference]` en `<html>` (inicial server-side en
  `panel-shell.blade.php`, mismo valor que `$tema`). "Sistema" se resuelve
  vía `matchMedia('(prefers-color-scheme: dark)')` y se re-resuelve en vivo
  si cambia la preferencia del SO mientras esté activo. Persistencia: el
  enum de `sec_user_preferencia.tema` (Claro|Oscuro) NO se tocó — "sistema"
  se guarda solo en `localStorage` de ese navegador (decisión explícita:
  no coordinar con backend en esta vuelta). Nueva clave `ui.theme.system`.
- `module-sidebar__desc` gana `-webkit-line-clamp:2` — sin tope, una
  descripción larga hacía crecer el bloque de cabecera en varias líneas.
- `module-rail`: tooltips nativos del navegador (`title`) → tooltip de
  Bootstrap (`data-bs-toggle="tooltip"` + `data-bs-title`, inicializado
  globalmente en `app.js`), mismo patrón que ya usaba el badge de
  `menu-item`.
- **`menu-item` (nivel 3, pedido directo del usuario durante esta sesión):**
  el elemento raíz (`<a>`/`<button>`) suma el mismo tooltip de Bootstrap con
  el label resuelto — con `module-sidebar` colapsado (icon-rail angosto,
  Fase 1 del rediseño anterior) el label/badge se ocultan y sin esto no
  había forma de saber a qué ítem corresponde cada ícono sin expandir.
  Irrelevante-pero-inofensivo cuando el sidebar está expandido.
- Chips de estado de la tabla de sesiones (escritorio, columna Estado):
  badge relleno → punto de color + texto plano (`.ag-table__dot`, mismo
  patrón que ya usaba la tabla de eventos de pausas — se completó con los
  tonos `success`/`warning` que faltaban). Acotado a esa columna de esa
  tabla; `atoms/badge` no cambió para sus otros usos (KPI, alertas, columna
  RC de la misma tabla).
- Columna HA de la tabla de sesiones: `text-align:right` (header + celdas)
  — una cifra alineada a la izquierda se compara peor que a la derecha.
- **Feedback de "presionado/abierto" en los popups del header (pedido
  directo del usuario durante esta sesión):** avatar de usuario y campana
  de notificaciones ya abrían un dropdown real de Bootstrap, pero el estado
  `:hover` y el estado realmente ABIERTO (`[aria-expanded='true']`) se
  veían idénticos — sin forma de notar a simple vista que el popover seguía
  desplegado. Ahora `[aria-expanded='true']` suma un anillo interior propio
  (`box-shadow: inset 0 0 0 1.5px var(--ag-color-primary-border-subtle)`)
  encima del fondo/color que ya traía, y ambos controles suman un
  `:active` con `transform: scale()` para el instante de presionado
  (respetando `prefers-reduced-motion`). El selector de período
  (`.ag-topbar__period`) no tenía NINGÚN estilo de interacción — hoy no
  abre un dropdown real (fuera de alcance, decisión explícita del usuario:
  solo refuerzo visual, no funcionalidad nueva) pero suma `:hover`/`:active`/
  `:focus-visible` con el mismo lenguaje. El chip de campaña
  (`.ag-topbar__campaign`) se dejó como estaba — es informativo, no un
  control clicable. **Los dos dejaron de existir el 9/9/2026 — ver §18.**

### 10.10. Regla nueva en §8

Título de card (`.ag-card__title`, sans/bold/`font-size-sm`) se fijó como
regla 9 de §8 — ver ahí.

## 11. Superficies del tema oscuro y tipografía display

### 11.1. Tema oscuro

La superficie estructural es neutra y la identidad vive en los acentos. `--ag-color-bg` es el lienzo verde oscuro de marca (`#17251C`); el chrome (`--ag-color-bg-elevated`, `-bg-chrome`) y la tarjeta (`--ag-color-surface-card`) son `surface` (`#293832`), y los rellenos de tabla, hover e input son `surface2` (`#34443B`). El ítem de menú activo usa el token sólido `--ag-color-bg-nav-active` (sin alpha, para que se vea igual sobre cualquier fondo). El verde de marca se reserva a botones, badges, enlaces y al ítem activo.

### 11.2. Tipografía display

`--ag-font-family-display` resuelve a Manrope, la misma familia de la interfaz; ver §7.3. Los consumidores (`auth-layout`, `login-form`, `module-sidebar`, `panel-layout`, `page-header`, `seleccionar-rol`) declaran `font-weight` explícito, así que el titular se ajusta por peso sin tocar componentes.

## 12. Octava vuelta (29/8/2026) — `theme-toggle`: de segmented control a botón único

Pedido explícito sobre `molecules/theme-toggle`, componente cubierto en
§4.2 y extendido a 3 estados en la auditoría visual externa (§7.7, obs. #9).
Dos cambios, misma vuelta:

**Se retira "sistema".** El enum del backend (`sec_user_preferencia.tema`)
siempre fue Claro|Oscuro únicamente — "sistema" vivía enteramente en
localStorage, con un atributo propio `data-ag-theme-preference` en `<html>`
para distinguir "preferencia elegida" de "tema resuelto" (necesario solo
porque "sistema" resuelto a oscuro debía pintar la celda "sistema" como
activa, no la celda "oscuro"). Sin "sistema" esa distinción no existe:
`[data-bs-theme]` vuelve a ser la única fuente de verdad, tanto para los
tokens de color como para el propio control. Se retiran también la
entrada `ui.theme.system` (`lang/es/ui.php`) y el atributo
`data-ag-theme-preference` de `panel-shell.blade.php`.

**De 3 celdas con pastilla contenedora a un solo botón-ícono.** Motivo
concreto, no solo preferencia estética: el contenedor (`.ag-theme-toggle__control`,
`background: var(--ag-color-bg)`) dependía de compartir superficie con el
chrome que lo rodea (topbar/sidebar, `--ag-color-bg-chrome`) — cierto hasta
la séptima vuelta (§11.1), donde `-bg` y `-bg-chrome` dejan de compartir
superficie a propósito en oscuro. Resultado no anticipado: el contenedor
del toggle pasó a verse como un parche verde suelto en el topbar oscuro
(`--ag-color-bg`, ahora con tinte, sentado sobre `--ag-color-bg-chrome`,
ahora neutro) — visible en captura real, señalado explícitamente ("en mi
tema claro dentro dashboard su contenedor no se nota en relación al estilo
del oscuro"). Se retira el contenedor en vez de perseguirle un tono
correcto: ahora es un ícono suelto, mismo lenguaje que
`.ag-topbar__icon-btn` (topbar.css) — transparente en reposo, wash de
acento en hover/foco, sin fondo permanente.

Con un solo botón (ya no 3 radios), el ícono visible es el de la PRÓXIMA
preferencia — la que se aplica al presionar, no la actual — para que el
control se lea como una acción ("tocá esto para pasar a oscuro") y no como
un indicador de estado. Los dos `<x-atoms.icon>` (`dark_mode`/`light_mode`)
quedan siempre en el DOM; `theme-toggle.css` muestra solo uno según
`[data-bs-theme]` en `<html>` — mismo criterio de "un atributo global
decide todo" que ya regía, sin JS de sincronización entre instancias
(topbar + auth-layout).

**Color de acento por tema (pedido explícito, no una regla nueva de §8):**
a diferencia de `.ag-topbar__icon-btn` (siempre primary/verde), el hover/
active de `theme-toggle` usa **primary (verde) en claro** y **accent (ámbar)
en oscuro** — la única superficie del sistema con esta inversión deliberada;
no se generaliza a otros íconos del topbar.

`resources/js/molecules/theme-toggle.js` se simplifica en la misma
proporción: sin `SISTEMA`, sin el listener de `matchMedia`, sin
`sincronizarInterruptores` (no hay más que un `data-bs-theme` que alternar
y persistir).

## 13. Novena vuelta (29/8/2026) — dashboard: de ERP genérico a panel visual/estadístico

Pedido explícito del usuario: el rubro (fumigación agrícola con drones) le
interesa más ver estadísticas y mapas que tablas. El dashboard
(`app/Dominios/Seguridad/Infraestructura/Http/Views/pages/dashboard.blade.php`)
pasa de 3 pestañas (Resumen/Sesiones/Pausas + KPIs) a 4: **Resumen** (sin
KPIs, con 3 gráficos ApexCharts + detalle de clientes), **Mapa** (Leaflet),
**Resumen por lote** (cuadros por lote) y **Multimedia**. Detalle completo del
plan y las 9 fases ejecutadas: rama `feature/dashboard-agro`, plan en
`docs/gestion/` de esa sesión.

### 13.1. Primera vez que el panel carga una librería JS pesada — convención de import() dinámico

Hasta ahora todo el JS del panel (`resources/js/organisms/*.js`,
`molecules/theme-toggle.js`) se importa estático desde `app.js`, porque es
vanilla sin dependencias de terceros. **ApexCharts** (gráficos de Resumen)
y **Leaflet** (mapa satelital) son las primeras dependencias npm pesadas
del panel (~130 KB y ~40 KB respectivamente) — como Vite solo tiene un
entrypoint (`resources/js/app.js`, cargado en TODAS las páginas vía
`panel-shell.blade.php`), un import estático las bajaría hasta en el
login. Regla nueva, exclusiva para dependencias de este tamaño (el resto
del catálogo sigue con import estático simple):

```js
// resources/js/app.js
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-ag-chart]')) import('./organisms/dashboard-charts.js');
});
```

Vite genera un chunk separado por cada import() dinámico (`dashboard-charts-*.js`,
`dashboard-charts.js`) que el
navegador solo pide si el DOM de la página tiene el contenedor
correspondiente — verificado con Playwright que login/selección de rol no
descargan ninguno de los dos chunks.

**Gotcha real, no obvio**: el módulo importado dinámicamente se carga
DESPUÉS de que `DOMContentLoaded` ya disparó (por definición: el import()
ocurre dentro de un handler de ese mismo evento). Si el módulo hijo
también hace `document.addEventListener('DOMContentLoaded', renderizar)`,
ese listener nunca se ejecuta. `dashboard-charts.js`
renderizan de inmediato al cargar, sin volver a esperar el evento.

### 13.2. Resolución de colores en JS — `resources/js/shared/color-tokens.js`

ApexCharts y Leaflet piden colores ya resueltos en JS (`colors: [...]`,
`fillColor`), nunca `var(--ag-color-x)` literal — y `getComputedStyle().getPropertyValue()`
no alcanza para tokens con `color-mix()` (varios navegadores devuelven el
texto tal cual, no el color mezclado). `leerColorToken(nombreToken)`
aplica el token a `color` de un `<span>` oculto y lee
`getComputedStyle().color`, forzando la resolución completa a `rgb()`.
Todo color nuevo en JS pasa por acá — confirmado en auditoría que es el
único mecanismo usado (excepción encontrada y corregida: un `#fff` literal
en el borde de los `circleMarker` del mapa, ver 13.4).

### 13.4. Auditoría (`validador`, 29/8/2026) — 2 hallazgos bloqueantes, ambos corregidos

Resto de la auditoría (ADR 0003, ADR 0009, ADR 0013, seguridad de los
popups de Leaflet vía `createElement`/`textContent` — nunca `innerHTML`—,
Atomic Design, CSS/i18n huérfanos, patrón de template) sin hallazgos.
`pint`/`phpstan`/`pest` (159/159) pasan limpio tras las dos correcciones.

## 14. Tarea 31 (2/9/2026) — el arquetipo formulario y la compuerta visual

TE (sin HU propia): quedan 25 pantallas de Sprint 7 en adelante y el catálogo
no tenía con qué construir un formulario — `form-section` era un `<fieldset>`
sin chrome ni grid, y faltaban seis piezas más. Especificación completa en
`docs/diseno/guia_pantalla_panel.md` §6.3/§7 (referencia de composición: el
canvas de Claude Design "Registro de la compañía", estructura y medidas
traducidas a tokens, nunca su paleta/fuentes propias).

**Siete piezas del catálogo:**

- **`form-section` evolucionado**: de `<fieldset>`+`<legend>` a tarjeta
  (`--ag-color-surface-card`/`-border-card`/`--ag-radius-lg`) con
  `section-head` como header y `grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr))`
  para el cuerpo. Utilidad nueva `.ag-form-section__field--full`
  (`grid-column: 1 / -1`) para el campo que necesita el ancho completo.
- **`page-header`** (organism): h1 + bajada + slot `actions`. No migra
  `ag-dash__header` del dashboard todavía (fuera de alcance de esta tarea).
- **`tabs`** (molecule): extrae el nav que el dashboard ya armaba a mano con
  `data-bs-toggle`; el CSS (`tabs.css`) no cambió, solo se agregó el
  componente. El `tab-content`/`tab-pane` sigue siendo de cada página.
- **`form-actions-bar`** (organism): barra pegajosa al pie (`position: sticky`,
  no `absolute` — no repite el bug de §4.1) con estado de guardado en texto
  y slot `actions`, mismas acciones que `page-header`.
- **`summary-card`** / **`progress-meter`** (molecule): ambas componen
  `section-head` como header — mismo criterio ya aceptado para
  `form-section` (§6.3 lo pide explícito: "un header separado por borde que
  es exactamente `molecules/section-head`"), aunque en sentido estricto
  eso excede la regla general de §2 de la guía de pantalla ("molecule: sin
  orquestar otras moléculas"). `progress-meter` recibe el porcentaje ya
  calculado (nunca calcula una regla de negocio) y lo pasa a la barra por
  una custom property (`--ag-progress-meter-percent`) — no es una medida de
  diseño hardcodeada, es un dato en runtime.
- **`file-field`** (molecule): reemplaza el markup suelto que tenía
  `organizacion.css` para el preview del logo (`.ag-organizacion__logo-preview*`,
  retirado). Preview vía slot (no decide qué es: ícono, imagen, `atoms/logo`).

**Bug de LSP encontrado al montar `organizacion`**: `atoms/input` NO fusionaba
`$attributes` en su `<div>` raíz (solo en el `<input>` interno) — pasarle
`class="ag-form-section__field--full"` al componente no movía esa clase al
hijo directo del grid, que es el `<div class="ag-input">`. Se resolvió al
vuelo envolviendo ese campo puntual (`contacto_direccion`) en un `<div>`
propio en la página en vez de tocar `atoms/input` (blast radius: ese átomo lo
consume todo el panel; arreglarlo quedó anotado como tarea aparte). Los demás
casos de ancho completo (`file-field`, el `radiogroup` de planes) sí
fusionaban `$attributes` en su raíz y no necesitaron el envoltorio.
**Resuelto en la tarea 32 (§15)**: el envoltorio en `organizacion` ya no
existe.

**`/panel/organizacion` reconstruida** sobre las siete piezas: layout de dos
columnas (`ag-organizacion__main` flexible + `ag-organizacion__aside`
pegajoso, `flex: 1 1 19rem; max-width: 20rem`, literal de §6.3 regla 4),
tabs "Organización / Usuarios y roles / Facturación" (solo la primera tiene
contenido — las otras dos son un placeholder de una línea, "Próximamente",
no una pantalla nueva). `organizacion.css` quedó sin un solo `px`/hex
inventado (antes: `max-width: 600px`, `60px` del preview, `minmax(250px, …)`)
— lo que sobrevive son medidas en `rem` sueltas propias de esta página
(igual criterio que `login-form`'s `max-width: 380px`, ya documentado en
§3: los anchos de layout puntuales no se tokenizan) y los breakpoints
`1199.98px`/`767.98px` de la escala.

**La compuerta visual, conectada**: `tests/Visual/` existía desde la tarea
07 (Playwright + specs de `login`/`seleccionar-rol`/`dashboard`) pero
`bin/verify` no la corría — se agregó `tests/Visual/organizacion.spec.ts`
(mismo patrón que los tres specs existentes: login real, `esperarFuentes`
antes de capturar) y una tercera etapa a `bin/verify` (`npx playwright test`).
Deliberadamente fuera de CI (`.github/workflows/` no se tocó): los
snapshots llevan sufijo de plataforma (`-darwin` hasta el 3/9/2026, `-win32`
desde que el desarrollo pasó a Windows), y el runner Linux de CI daría falsos
rojos por diferencia de plataforma, no por regresión real.

## 15. Tarea 32 (3/9/2026) — `atoms/input` fusiona `$attributes` en su raíz

Arregla el bug de LSP que dejó anotado la tarea 31 (§14). `atoms/input` tiene
estructura dual: `<div class="ag-input">` raíz envolvente + `<input>` control
real — no un único elemento raíz, que es el caso simple que ya resolvían
`file-field` y el resto del catálogo. Ningún componente existente tenía este
problema resuelto para copiarlo.

**La solución parte el `$attributes` bag en dos**, no lo fusiona entero en
ningún lado:

- El `<div>` raíz fusiona **solo la `class`** de layout
  (`$attributes->class([...])->only('class')`) — así una utilidad como
  `ag-form-section__field--full` llega al hijo del grid que la necesita.
- El `<input>` recibe **el resto del bag salvo `class`**
  (`$attributes->except('class')`) más su clase fija `ag-input__field` como
  literal — así `disabled`, `data-*`, `aria-*`, `wire:model`, etc. le siguen
  llegando al control real (varios formularios del panel, incluido
  `organizacion`, pasan `disabled` a campos de solo lectura) sin duplicar ni
  ensuciar la clase fija con la de layout.

`organizacion/index.blade.php` ya no envuelve `contacto_direccion` en un
`<div>` propio: la clase va directo en `<x-atoms.input class="ag-form-section__field--full" ...>`,
mismo patrón que ya usaba `file-field`.

**`atoms/switch` queda con el mismo bug latente, sin tocar** (raíz
`<div class="ag-switch">` sin `$attributes`, el `<input type="checkbox">`
interno sí lo recibe): ningún consumidor de hoy le pasa una clase de layout
al componente, a diferencia de `atoms/input` que ya tenía un caso de uso real
bloqueado. Mismo criterio de blast radius que la tarea 31: se arregla cuando
aparezca la necesidad real.

## 16. Tarea 76 (8/9/2026, HU-53) — el sistema de inputs: `select`, `date`, `checkbox`, `checkbox-group`, `radio-group`, `textarea`

Seis átomos nuevos, mismo contrato partido de `$attributes` que ya fijó la
tarea 32 (§15) para `atoms/input`: la raíz solo fusiona `class`
(`->only('class')`), el/los control(es) real(es) reciben el resto
(`->except('class')`) — así `data-*`/`wire:model`/`aria-*` le siguen llegando
al control nativo, no a un `<div>` decorativo. Cierra el diagnóstico de la
tarea (11 `type="date"`, 70 `<select>`, 7 `type="checkbox"` crudos en el
panel) y migra las 88+ apariciones reales encontradas.

### 16.1. Qué átomo usar para cada caso

| Necesito... | Átomo | Por qué no otro |
|---|---|---|
| Un texto/número/fecha-hora/contraseña/archivo de una línea | `atoms/input` | Sin cambios en esta tarea. |
| Un texto de varias líneas | `atoms/textarea` | Mismo contrato que `input`, sin `type`/`icon`/toggle de password (no aplican a multilínea), con `rows` en su lugar. |
| Elegir UNA fecha | `atoms/date` | Nunca el `<input type="date">` nativo (diagnóstico de la tarea: se ve distinto por navegador/SO, ignora `es`). Reemplaza también a `<x-atoms.input type="date">`, que era el patrón real en la mayoría de los formularios (no HTML crudo). |
| Elegir UN rango horario (hora de inicio y de fin de un mismo día) | `atoms/time-range` | Nunca dos `<input type="time">` sueltos (cada navegador los dibuja distinto, no respetan los tokens ni el tema oscuro, y son dos casillas para un solo dato). Una sola casilla "06:00 – 10:00" que abre un selector de reloj circular estilo Material. Propio, sin librería: flatpickr (el de `atoms/datetime`) no maneja un rango horario en una casilla. Mejora progresiva como `atoms/date`: sin JS quedan los dos `<input type="time">` nativos, que son los que se envían. |
| Elegir UNA opción de una lista (con o sin búsqueda) | `atoms/select` | Combobox propio con label/placeholder/ícono/error/help y `options` como `valor => etiqueta` ya traducida. Con más de 8 opciones agrega búsqueda por texto automáticamente — `count($options) > 8`, o el prop `searchable` cuando las opciones las llena JS después (selects en cascada, cuyo conteo del servidor es 0). La búsqueda (19/9/2026) es un `<input>` REAL que toma el foco al abrir — antes era una fila decorativa: pulsarla no hacía nada, el placeholder no se iba y no había teclado en el celular — y es por PALABRAS: cada palabra escrita debe aparecer en la etiqueta, en cualquier orden, sin distinguir mayúsculas ni acentos ("cotoca santa" encuentra "Cotoca - Santa Cruz"). El placeholder del input se oculta apenas tiene foco. Con 8 opciones o menos no hay caja de búsqueda pero el teclado soporta type-ahead. El `<select>` nativo deshabilitado sigue oculto (doble clase en el CSS: el reboot de Bootstrap trae `select:disabled { opacity: 1 }`), y el placeholder queda seleccionado cuando el valor es `null` o `''` (antes un `''` dejaba elegida la primera opción real sin que nadie la eligiera). |
| Prender/apagar UNA opción booleana suelta (ítem de lista, "aceptar términos") | `atoms/checkbox` | Casilla cuadrada con check de Material Symbols. Distinta de `atoms/switch` (pastilla con thumb, pensada para "prender/apagar una preferencia", no para "marcar un ítem" — criterio ya fijado en la sexta vuelta, §8). |
| Elegir VARIAS opciones de una lista (con o sin búsqueda) | `atoms/checkbox-group` | Selección múltiple con `name[]`, cada opción es un `<input type="checkbox">` real (teclado gratis del navegador). Con más de 8 opciones agrega el mismo filtro de texto que `select`. Reemplaza tanto varios `type="checkbox"` sueltos con el mismo `name[]` como un `<select multiple>` nativo (ver ejemplo de `usuarios/_formulario.blade.php` abajo). |
| Elegir UNA opción entre pocas, todas visibles a la vez (sin necesidad de desplegable) | `atoms/radio-group` | `<input type="radio">` reales agrupados por `name`, sin JS propio (el navegador ya mueve el foco con las flechas). Poco usado hoy en el panel — existe para cuando aparezca el caso, no se forzó ningún `select` a convertirse en esto. |

### 16.2. Ejemplos de uso

```blade
{{-- select: opciones id => etiqueta, ya traducidas por el llamador --}}
<x-atoms.select
    name="cliente_id"
    label="{{ __('comercial.contratos.campo_cliente') }}"
    placeholder="{{ __('comercial.contratos.campo_cliente_placeholder') }}"
    :options="$clientesDisponibles"
    value="{{ $clienteId }}"
    required
    error="{{ $errors->first('cliente_id') }}"
/>

{{-- date: value/min/max siempre en ISO (YYYY-MM-DD), igual que type="date" --}}
<x-atoms.date
    name="fecha_inicio"
    label="{{ __('comercial.contratos.campo_fecha_inicio') }}"
    value="{{ $fechaInicio }}"
    required
    error="{{ $errors->first('fecha_inicio') }}"
/>

{{-- checkbox: casilla booleana suelta, value default "1" --}}
<x-atoms.checkbox name="remember" label="{{ __('seguridad.login.recordarme') }}" />

{{-- checkbox-group: selección múltiple, se envía como roles[] --}}
<x-atoms.checkbox-group
    name="roles"
    label="{{ __('seguridad.usuarios.campo_roles') }}"
    :options="$opcionesRoles"
    :value="$rolesSeleccionados"
    help="{{ __('seguridad.usuarios.campo_roles_ayuda') }}"
    error="{{ $errors->first('roles') }}"
/>
```

### 16.3. Selects dependientes (cliente→campaña, rubro→subrubro): el mapeo ya no va por `<option data-*>`

La pantalla `contratos/_formulario.blade.php` filtra un `<select>` según el
valor elegido en otro (`contratos-form.js`). Antes el mapeo hijo→padre
viajaba como un atributo `data-*` en cada `<option>` del `<select>` hijo. Con
`atoms/select`, el `<select>` nativo deja de ser el único elemento visible —
`resources/js/atoms/select.js` arma un combobox al lado y no sabe leer
atributos por opción. Solución, sin tocar el contrato del átomo: el mapeo
completo viaja como UN atributo JSON en el propio `<select>` (p. ej.
`data-mapa-cliente-campania="{{ $mapa->toJson() }}"`, reenviado al nativo por
el LSP de `$attributes->except('class')`), y el script dependiente lo lee una
vez (`JSON.parse(...)`) en vez de iterar `option[data-cliente-id]`. El resto
del algoritmo (ocultar/deshabilitar `<option>`, limpiar el valor si deja de
ser visible) no cambió.

Ese cambio de mecanismo dejó una foto vieja en el combobox: `select.js` lee
las opciones del nativo UNA sola vez, al inicializar. Un filtro externo que
después marca `<option>` como `disabled`/`hidden` (el caso de arriba) no se
reflejaba en la lista ya pintada. Se agregó un `MutationObserver` sobre el
`<select>` nativo (`attributeFilter: ['disabled', 'hidden']` + `childList`)
que re-lee las opciones y repinta si el combobox está abierto — genérico,
sirve para cualquier select dependiente futuro, no solo para los dos casos de
hoy.

### 16.4. Dos excepciones deliberadas, no migradas

- **`app/Dominios/Seguridad/Infraestructura/Http/Views/pages/roles/permisos.blade.php`**:
  sus `type="checkbox"` son parte de una matriz de permisos con chips/switches
  propios (`ag-permisos__chip`, `ag-permisos__switch`), no casillas sueltas —
  convertirlas a `atoms/checkbox` habría sido un rediseño visual de esa
  pantalla, fuera del alcance de esta tarea (que el prompt marca
  explícitamente: "Fuera de alcance: ... permisos ..."). El grep de
  aceptación de `type="checkbox"` los sigue mostrando; es esperado.
- **`resources/views/components/atoms/switch.blade.php`**: su
  `<input type="checkbox">` interno es la implementación del átomo `switch`
  en sí (existe desde la sexta vuelta, §8), no marcado crudo del panel —
  mismo criterio que dejar `resources/views/components/atoms/select.blade.php`
  fuera del grep de `<select>`.

`type="month"` (los filtros de período de los listados) tampoco se tocó: es un tipo de input distinto a
`date` (selector de año-mes, no de día), fuera del diagnóstico de la tarea
(11 `type="date"`) y sin átomo propio en el catálogo — queda para cuando
haga falta un `atoms/month` real.

### 16.5. `usuarios/_formulario.blade.php` — el único `<select multiple>` del panel, migrado a `checkbox-group`

No estaba en el conteo original de 88 (un `<select multiple>` no matchea
`<select` seguido de un atributo simple en el grep del diagnóstico, y de
hecho el propio docblock del archivo decía "no hay átomo de selección
múltiple en el catálogo" — cierto cuando se escribió, en la tarea 39, antes
de que existiera `checkbox-group`). Es exactamente el caso de uso para el que
se dimensionó `checkbox-group` (elegir varios roles de una lista): se migró
igual, aunque no estuviera en la lista original, porque dejarlo habría sido
el único `<select>` real sobreviviente en todo el panel.

## 17. Tarea 80 (9/9/2026, HU-57) — `checkbox-group` gana `extraPorOpcion`: contenido por opción

El pedido del dueño sobre `/panel/ordenes-mantenimiento/{id}/editar` ("otro
modelo de selección por casillas y no tanto de select en los repuestos") no
alcanzaba con `checkbox-group` tal como quedó en la tarea 76: elegir varios
repuestos es lo que ya resolvía, pero cada uno necesita ADEMÁS su propio
campo de cantidad al lado, visible solo cuando esa casilla está marcada — el
átomo no tenía forma de inyectar nada dentro del `<li>` de una opción
puntual.

En vez de un componente nuevo para la pantalla (explícitamente prohibido por
el prompt de la tarea), se extendió el átomo con un prop genérico:

```blade
<x-atoms.checkbox-group
    name="repuestos_marcados"
    :options="$repuestosDisponibles"
    :extra-por-opcion="$extraPorRepuesto"
/>
```

`extraPorOpcion` es `valor => HTML ya armado` (típicamente
`view(...)->render()` del llamador) — el átomo lo inyecta después de la
etiqueta de esa opción, dentro de su `<li>`, sin saber qué es ni cuándo debe
mostrarse. Sigue "sin lógica de negocio": es una bolsa de HTML opaca,
indexada por el mismo valor que ya usa `options`. Quien la pasa es
responsable de:

- **Mostrarla**: pura CSS, `.ag-checkbox-group__item:has(.ag-checkbox-group__input:checked)
  .ag-checkbox-group__option-extra { display: block; }` — no hace falta JS
  para el reveal en sí, es instantáneo incluso si el JS de la página no
  cargó todavía.
- **Que sus campos no viajen en el POST estando ocultos**: eso sí es JS
  (`disabled` en el `change` de la casilla hermana) — a diferencia del
  reveal visual, esto no tiene equivalente en CSS puro.

Ningún consumidor de `checkbox-group` (roles de
`usuarios/_formulario.blade.php`, §16.5) pasa `extraPorOpcion`, así que su
`isset()` siempre da falso para ellos.

## 18. Décima vuelta (9/9/2026) — el header se queda sin contexto de negocio

Feedback directo del dueño sobre el panel andando, sobre captura del header:

> *"en el header hay un select que dice la zona horaria, eso se supone que
> lo captura internamente no que podamos ver o elegir la zona horaria (…)
> asimismo la campaña que dice septiembre 2026 quítalo del header, como eso
> no nos pertenece"*.

Salen tres cosas del bloque derecho de `organisms/topbar`. Queda el buscador,
el `theme-toggle`, la campana y el menú de usuario — solo herramientas de la
sesión, ningún dato del negocio.

### 18.1. El selector de período (`.ag-topbar__period`)

Es el que se veía como "septiembre 2026". Nunca fue la campaña: era el mes
calendario en curso (`CascaraPanel::periodoEnCurso()`), demo visual sin
backend — no filtraba ninguna pantalla. Pero con un ícono de calendario y el
nombre de un mes al lado del avatar, se leía exactamente como el contexto de
campaña que el ADR 0015 ya había decidido que no existe. Un control que no
hace nada y que además miente sobre lo que representa no tiene defensa: se
va entero (blade, CSS, `ui.header.periodo` y el método que lo calculaba).

### 18.2. El chip de campaña (`.ag-topbar__campaign`)

Venía apagado desde la tarea 67 (`campaniaActiva` era `null` fijo por ADR
0015 punto 1: la campaña es del cliente, y con decenas abiertas a la vez no
hay una "activa" de sesión). La prop se había conservado "para no volver a
tocar las 82 vistas si algún día vuelve a tener con qué llenarse". Nunca
volvió a tener con qué: llegó a hoy en `null` y ya no eran 82 vistas sino
95, y el hueco que dejaba en el header es justo el que ocupó el período
para pasar por ella. Se va el chip y se va la prop de toda la cadena
(`CascaraPanel` → `panel-layout` → `topbar`/`mobile-topbar` → las 95 vistas).

**Regla que queda:** una prop de chrome que nace en `null` fijo no se
conserva "por si acaso" — el costo de reintroducirla el día que tenga con
qué llenarse es un `sed`, y mientras tanto ocupa lugar en el header y en la
cabeza de quien lee el layout.

### 18.3. La zona horaria: de `select` en el header a badge en el pie

Era un `<select>` con las ~400 zonas IANA (`molecules/timezone-selector`)
pegado al `theme-toggle`. Dos problemas, y el dueño nombró los dos: pedía
**elegir** un dato que el navegador ya informa
(`Intl.DateTimeFormat().resolvedOptions().timeZone` — el login lo venía
mandando desde el vamos), y al hacerlo lo ponía al nivel de una decisión,
en el borde superior derecho, que es el lugar más caro de la pantalla.

Ahora:

- **Se captura sola.** `molecules/timezone-badge.js` compara, al cargar, la
  zona del navegador contra la guardada y persiste la diferencia por el
  mismo endpoint que usaba el select. Cubre lo que el login no cubría: la
  sesión vieja sin preferencia, y el usuario que entra desde otra máquina o
  desde otro huso.
- **Se muestra, no se toca.** `molecules/timezone-badge` es un badge de
  lectura en el pie de `panel-layout` y `portal-layout`, con la tipografía
  mono chica del footer. El dato sigue a la vista porque hace falta: si la
  bitácora dice "hoy 14:32", conviene poder ver desde qué huso se está
  leyendo esa hora. Pero como dato, no como control.

**Regla que queda:** lo que el navegador puede responder solo no se le
pregunta al usuario. Si además conviene mostrarlo, va como badge de lectura
en el pie, nunca como control en el header.

## 19. Buscador global (9/9/2026) — el input del header deja de ser maqueta

Pedido del dueño, después de sacarle al header lo que no servía (§18):
*"habilitar el input search del header y crear una página de resultados (…)
que no solo busque una sola palabra, si ingresa palabras múltiples como sea el
nombre de una estancia buscar todo completo o una palabra ubicada en medio de
una oración, una búsqueda más inteligente"*.

### 19.1. El campo del header

Pasa de `<label>` decorativo a `<form method="GET">` contra `panel.buscar`. Se
envía con Enter y **sin JavaScript**: el atajo ⌘K solo enfoca el campo
(`organisms/topbar.js`), no es lo que dispara la búsqueda. El `<kbd>` que
anunciaba el atajo desde la quinta vuelta ahora hace lo que decía.

### 19.2. La pantalla: bloques, no una tabla

`/panel/buscar` no es el arquetipo Listado de §6.2 de la guía de pantallas.
Son tarjetas, una por entidad con coincidencias, en `repeat(auto-fill,
minmax(22rem, 1fr))`: con una sola coincidencia el resultado no queda perdido
en una columna de 1200 px, y con ocho bloques no hay scroll horizontal.

Cada bloque muestra hasta cinco filas y, si hay más, un enlace **"ver todos"**
al listado de ese módulo con la búsqueda ya aplicada. El buscador no
reimplementa esas pantallas: ellas ya saben filtrar, paginar y gatear por
permiso.

Tres estados vacíos distintos, y son distintos a propósito: no escribió nada
(explica dónde busca), escribió solo letras sueltas (pide un poco más), y
buscó pero no hubo coincidencias (sugiere acortar).

### 19.3. Qué encuentra, y con qué reglas

Diez entidades, todas las que tienen un nombre o un identificador propio:
clientes, personas, propiedades, drones, lotes, campañas, cultivos, equipos de
trabajo, bases y baterías.

El motor parte la consulta en palabras y exige **todas**, cada una en
**cualquier posición** y **sin importar acentos ni mayúsculas**. "peranza"
encuentra "Esperanza"; "san jorge" y "jorge san" dan lo mismo; "agricola"
encuentra "Agrícola".

**Por qué no full-text de Postgres:** `tsvector` indexa palabras enteras, así
que "peranza" no encontraría nada — justo el caso pedido.

**Limitación conocida:** la puntuación cuenta como texto. "esperanza sa" no
encuentra "Estancia La Esperanza S.A.", porque el dato dice "S.A." y no "sa".
Escribir "esperanza" alcanza.

### 19.4. Regla que queda: un resultado es tan sensible como su pantalla

El buscador **no tiene permiso propio**. Cada bloque se gatea con el permiso
de la entidad que muestra, evaluado contra el **rol activo** (CLAUDE.md
invariante 10). Un piloto escribe el nombre de un cliente y no le aparece
ningún cliente. Un permiso "buscar" separado solo habría creado una forma de
tener el buscador prendido y vacío, o de creer que gatea algo que en realidad
gatean los proveedores.

Sumar una entidad al buscador es escribir un proveedor de ~40 líneas
(`Infraestructura/Busqueda/` del módulo) y taggearlo en su `ServiceProvider`.
Ni el agregador ni la pantalla se tocan.


## 20. El parpadeo de tema (9/9/2026) — `atoms/tema-inicial`

Bug reportado en video: al entrar al panel con el tema oscuro, cada pantalla
se pintaba primero en claro y saltaba a oscuro de golpe. Medido sobre la
grabación (frames a 60 fps): **~130 ms de página clara** en cada navegación, y
~400 ms en la pantalla de selección de rol.

**Causa.** El tema tenía dos fuentes y se resolvían en momentos distintos. El
servidor sirve `data-bs-theme` desde `sec_user_preferencia.tema` (correcto), y
`molecules/theme-toggle.js` corregía ese valor con el de `localStorage` en
`DOMContentLoaded` — o sea, después del primer pintado. Mientras las dos
fuentes coincidieran no se notaba; divergen apenas alguien toca el toggle en
la pantalla de **login**, donde no hay sesión contra la cual persistir nada, y
a partir de ahí divergen para siempre: el JS aplicaba el tema del navegador
pero nunca lo guardaba, así que el servidor seguía sirviendo el otro en cada
request.

**Arreglo, en dos mitades.**

1. `atoms/tema-inicial` — un `<script>` inline en el `<head>`, antes del CSS,
   que aplica el tema de `localStorage`. Es el único script inline del
   sistema y tiene que serlo: todo lo que entra por `@vite` se carga como
   `type="module"`, diferido por definición, y nunca llega antes del primer
   pintado. Va en `templates/panel-shell` y en las tres páginas públicas que
   arman su propio `<html>` (login, restablecer).
2. `theme-toggle.js` deja de aplicar el tema al cargar y, en su lugar,
   **cierra la divergencia**: si el tema del navegador no es el que sirvió el
   servidor (anotado en `data-ag-tema-servidor` por el script de arriba),
   lo persiste. Así el tema elegido en la pantalla de login se guarda solo
   con entrar, y a la siguiente request el servidor ya sirve el correcto.

Sin la mitad 2, el script del head escondería el síntoma dejando la
desincronización intacta.

**Regla que queda:** una página nueva que arme su propio `<html>` (en vez de
usar `panel-shell`) lleva `<x-atoms.tema-inicial />` en el `<head>`. Cubierto
por test en `tests/Feature/Seguridad/PantallaLoginTest.php` y
`TemaPersistidoPanelTest.php`.

## 21. Paleta curada de color de `Propiedad` (16/9/2026, ampliada dos veces la misma sesión) — dato de negocio, no theming

`Comercial` agrega un campo `color` al objeto `Propiedad` (terreno de un
cliente): lo elige el usuario para distinguir visualmente sus propiedades en
listados y en el mapa (los lotes de una propiedad heredan visualmente el
mismo color). Pedido explícito del dueño: **paleta curada**, no un input de
color libre (hex picker) — así dos propiedades nunca terminan con tonos
casi indistinguibles.

**Por qué está acá y no en §1 (tokens):** esto NO es un token de theming del
panel. Un token de theming se reasigna según `[data-bs-theme]` (claro/oscuro
cambian su valor); el color de una propiedad es un dato elegido por el
usuario que tiene que verse **igual en los dos temas** — es lo opuesto de un
token semántico. Vive documentado acá porque de todos modos es este agente
quien cura los valores (para que sean distinguibles entre sí, legibles como
swatch en ambos temas y visibles sobre capa satelital) — pero la
migración/constraint de Postgres y el array de constantes PHP los escribe
`backend` en el módulo `Comercial`, no `design-ui`. Ver el comentario de
cabecera de `atoms/color-swatch-picker.blade.php` sobre por qué esto no
viola CLAUDE.md invariante 11 (el HEX es dato pasado por el llamador, nunca
un literal dentro de un archivo CSS del catálogo).

**Ampliada la misma sesión (16/9/2026, segunda vuelta):** el dueño vio el
control de 13 círculos en fila dentro del formulario real y pidió moverlo a
un modal (§21.4) — y, con más espacio disponible ahí adentro, "aumentar los
colores para escoger". §21.1 documenta la paleta resultante de **13 a 16**
colores y, en la propia tabla de abajo, por qué el techo quedó en 16 y no
más arriba.

**Ampliada una segunda vez la misma sesión (16/9/2026, tercera vuelta):**
con el campo ya reestructurado como popup anclado al botón "Cambiar" (no
un modal — corrección directa del dueño, ver la cabecera de
`molecules/color-swatch-field.blade.php`) y sin la limitación de espacio de
una fila de formulario, el dueño pidió otra vez más colores, esta vez sin
acotar un número exacto ("cierto quiero más colores"), aceptando ~24 al
preguntársele. Mientras se armaba esta ronda llegó además un pedido
puntual: sumar negro (o un casi-negro, si el puro trae problemas de
legibilidad) a la mezcla. §21.1 documenta con qué técnica se llegó de 16 a
**23** — por qué no 22 a secas (ahí quedaba antes del negro) ni 24 exactos
— con el mismo rigor de las dos vueltas anteriores.

### 21.1. La paleta final: 23 colores (13 → 16 → 23, tres vueltas)

Elegidos con separación deliberada de matiz (HSL, ~27-28° entre colores
saturados consecutivos, saltando a propósito la franja 60-140° — amarillo-
verde — que es la del cultivo en una vista satelital, para que ninguna
propiedad se confunda con el propio terreno) y luminosidad media (ni pastel
—se funde en tema claro— ni casi negro —se funde en tema oscuro—). El verde
de la paleta (`#218349`) es deliberadamente más azulado que el verde de
marca (`--ag-brand-light-primary` = `#347A36`) y que el verde de cultivo típico:
bajo deuteranopia/protanopia, un canal de azul más alto ayuda a separarlo
del rojo (el eje que esos dos tipos de daltonismo comprometen). Diez colores
(los cuatro de la segunda vuelta — Marrón, Pizarra, Musgo, Malva — y los
seis de la tercera — Caqui, Salvia, Acero, Aciano, Vino, Terracota) son
deliberadamente desaturados: se distinguen del resto por CROMA, no por
matiz, así no compiten por el mismo eje de confusión que los doce
saturados. Un color más, Negro, no pertenece a ninguno de los dos anillos:
es la única pieza NEUTRA (croma cero) de la paleta — pedido explícito del
dueño, llegado después de encargada esta misma ronda ("no tenga miedo de
usar también el color negro en la combi") — ver el bloque dedicado al final
de esta sección.

**Primera y segunda vuelta (13 → 16): resumen — historial completo en el
propio control de versiones del documento.** La primera vuelta fijó 11
tonos saturados con ~27° de separación entre consecutivos (una excepción
aceptada, Rojo→Naranja, documentada entonces en ~17°) más Marrón y Pizarra
(desaturados). La segunda sumó Carmín (partiendo en dos el mayor hueco
suelto del anillo, entre Frambuesa y Rojo) y Musgo/Malva (las dos familias
de matiz — verde y violeta/púrpura — que todavía no tenían versión
desaturada), para totalizar 12 saturados + 4 desaturados = 16. Esa vuelta ya
advertía que el margen de matiz puro estaba prácticamente agotado.

**Tercera vuelta (16/9/2026): de 16 a 22 con una técnica nueva, más el
negro aparte hasta 23.**

Antes de decidir cómo estirar la paleta, se recalcularon los 16 HSL exactos
con la fórmula completa sRGB→HSL (las dos vueltas anteriores estimaban a
ojo/mentalmente; esta vez con el cálculo cifra por cifra). El resultado
corrige una cifra de la vuelta anterior: el hueco real entre Carmín
(`#B82343`, H≈347°) y Rojo (`#B6202D`, H≈355°) es de solo **~8°**, no los
~17-18° que asumía el texto anterior de esta sección (que ubicaba a Rojo en
"5°/365°" en vez de sus ~355° reales — un desvío de cálculo, no un cambio
de HEX). Con el dato correcto, el anillo saturado de 12 colores no tiene
ningún margen real: su hueco mínimo (~8°) ya está por debajo del piso de
17° que la propia paleta venía aceptando como límite. **Conclusión: cero
colores saturados nuevos en el anillo** — no es que no convenga sumar más,
es que ya no cabe ninguno sin bajar del piso ya aceptado, y esta vez no en
un punto aislado sino en el hueco más comprometido de todo el círculo.

Se evaluaron dos técnicas para los colores que hacían falta para acercarse
a ~24:

- **Segunda banda de luminosidad (más clara o más oscura del mismo matiz).**
  Descartada para el grueso de la ampliación. §21.2 ya medía que el
  contraste de los 16 anteriores contra los dos extremos del panel
  (`--ag-color-bg-elevated` casi blanco en tema claro,
  `--ag-color-surface-card` casi negro en tema oscuro) ronda 2.7-2.8:1 — la
  banda de luminosidad actual (L≈32-48%) ya es el punto medio que mejor
  sirve a los dos temas a la vez. Una banda más clara ganaría legibilidad en
  tema oscuro pero la perdería en tema claro (se acercaría a pastel, el
  mismo problema que el criterio original de "ni pastel ni casi negro" ya
  prohibía); una banda más oscura, lo simétrico al revés. Escalar esto a
  6-8 colores nuevos dejaría a la mitad fallando contra uno de los dos
  temas — inseguro como técnica de volumen, aunque como se ve más abajo
  hay una única excepción deliberada al final de esta ronda.
- **Segunda banda de croma (colores desaturados adicionales), la misma
  técnica de Marrón/Pizarra/Musgo/Malva, llevada a fondo.** Elegida. La
  segunda vuelta la usó dos veces (2 familias de matiz sin versión
  desaturada todavía); la tercera completa el resto del anillo — pero NO
  matiz por matiz 1:1: eso hubiera exigido un desaturado también para
  Carmín y para Rojo, hoy a solo ~8° de distancia entre sí, y dos versiones
  desaturadas ahí serían casi indistinguibles ENTRE ELLAS, más que sus
  contrapartes saturadas — porque separar matices por percepción es más
  difícil cuanto menos croma tiene el color (es la razón de fondo por la
  que ninguna técnica de "más colores por matiz" puede tratar igual a un
  tono vívido que a uno apagado). En cambio, el conjunto de desaturados se
  trató como un anillo PROPIO, con su propio piso de separación —
  deliberadamente más alto que el 17° aceptado para saturados, porque le
  falta el canal de croma como ayuda — repartido en el espacio de matiz
  disponible (los mismos 360° menos la franja 60-140° que ya evita el
  anillo saturado), sin exigir que cada desaturado caiga exactamente sobre
  el matiz de un saturado particular.

Con los 4 desaturados existentes (Marrón H≈26°, Musgo H≈150°, Pizarra
H≈205°, Malva H≈294°) como punto de partida, se repartieron 6 posiciones
nuevas en los huecos más anchos del anillo desaturado, siempre evitando la
franja 60-140°:

| Hueco (desaturado → desaturado) | Ancho | Color(es) nuevo(s) insertado(s) |
|---|---|---|
| Marrón (26°) → Musgo (150°) | 124° (mayormente franja prohibida) | Caqui, H≈48° — único tramo libre del hueco (26°-60°), a 22° de Marrón |
| Musgo (150°) → Pizarra (205°) | 55° | Salvia, H≈172° — a 22° de Musgo, 33° de Pizarra |
| Pizarra (205°) → Malva (294°) | 89° | Acero (H≈226°) y Aciano (H≈258°) — a 21°, 32° y 36° entre sí y de los vecinos |
| Malva (294°) → Marrón (386°=26°) | 92° | Vino (H≈325°) y Terracota (H≈355°) — a 31°, 30° y 31° entre sí y de los vecinos |

El hueco mínimo resultante entre dos desaturados es **21°** (Pizarra→Acero)
— por encima del piso de 17° ya aceptado para saturados, con el margen
extra deliberado explicado arriba. S y L de los 6 nuevos se mantuvieron
dentro del mismo rango ya validado por los 4 desaturados existentes (S
20-35%, L 34-42%) — no hizo falta remedir contraste pieza por pieza, mismo
criterio que ya usó la segunda vuelta para Carmín/Musgo/Malva (§21.2). Sobre
daltonismo: dos de los seis (Vino, Terracota) caen del lado cálido del
anillo, cerca de Carmín/Rojo — se les subió levemente el canal de azul
relativo al verde (mismo recurso ya usado en Verde bosque para separarse
del rojo bajo deuteranopia/protanopia), y al ser colores de croma bajo, la
distancia perceptual que un simulador de daltonismo puede "comerse" es
menor de entrada que en un color vívido — la desaturación ayuda un poco en
vez de sumar riesgo en este eje específico.

**Por qué 22 (matiz) y no 24 exactos:** se podría llegar a 23 desde acá
rompiendo el propio piso de 21° recién argumentado para desaturados y
bajándolo al piso de 17° ya aceptado para saturados — partiendo el hueco
Aciano→Malva (36°) en dos de ~18°. Se decidió no hacerlo: ese piso más alto
para desaturados no es un capricho de esta vuelta, es la razón por la que
la técnica funciona (croma bajo = matiz más difícil de distinguir), y
romperlo para ganar un solo color contradice el propio argumento que
sostiene toda la ampliación. Llegar a 24 exactos por esta vía exigiría dos
excepciones así, no una — el mismo tipo de riesgo que la segunda vuelta ya
había rechazado para el anillo saturado. 22 es el techo defendible con la
técnica de matiz/croma ya validada.

**Negro (fuera de presupuesto de matiz): de 22 a 23.** Mientras se armaba
esta ronda llegó el pedido directo de sumar negro (o un casi-negro si el
puro trae problemas). Esta pieza no compite con el razonamiento de arriba
porque no es ni un tono saturado ni un desaturado de un matiz — es NEUTRA
(S=0%), la única croma-cero de la paleta, así que no reabre ni el piso del
anillo saturado ni el del desaturado.

Sobre el propio pedido ("no ser puro `#000000` si eso trae problemas de
legibilidad"): sí los trae, y son los mismos dos que el criterio original
("ni pastel ni casi negro") ya señalaba para el resto de la paleta, más
agudos en el extremo: (1) contra `--ag-color-surface-card` en tema oscuro
(≈`#2E3236`, L≈20%) un negro puro tiene contraste de relleno prácticamente
nulo; (2) sobre una imagen satelital real, un negro puro se confunde con
sombra profunda o asfalto, más que cualquier otro tono de la paleta. Por
eso el valor elegido es **`#121212`** (L≈7%, S=0%) — no pastel del lado
oscuro, sino la convención de "negro profundo" que ya usa el propio sistema
(coincide con `--ag-color-gray-950`, y con el "casi negro" que Material
Design recomienda para superficies oscuras en vez de `#000000` puro, mismo
criterio: dureza de contraste y de sombreado, no solo estética).

Por qué esto no contradice el rechazo de la "segunda banda de luminosidad"
de más arriba: ahí el problema era escalar la técnica a 6-8 colores nuevos,
la mitad de los cuales fallaría contra alguno de los dos temas a la vez.
Acá es **una sola pieza**, de una categoría aparte (neutra, no matiz),
pedida explícitamente pese al criterio general — y su legibilidad no
depende del contraste de relleno sino del mismo mecanismo que ya sostenía
los 22 anteriores (§21.2): el anillo de 2px en `--ag-color-border-strong`
(≈`#5B5E61`, L≈37% en tema oscuro) contrasta con claridad tanto contra el
relleno casi negro (L≈7%) como contra la tarjeta oscura de fondo (L≈20%) —
un salto de luminosidad real a los dos lados del anillo, el caso límite
para el que ese mecanismo se diseñó. Nota para cuando `frontend` pinte este
color sobre el mapa (polígono de lote/propiedad): si "Negro" se pierde
contra sombra o asfalto en la imagen satelital, la solución es un halo/trazo
de contorno claro alrededor del polígono (igual que el anillo del swatch en
el panel) — no aclarar el HEX, que dejaría de leerse como negro.

**Por qué 23 y no 24 exactos, ahora con el negro adentro:** llegar a 24
exigiría además un neutro simétrico del otro extremo (un blanco/casi-blanco
— la misma pieza que Negro, del lado claro). Nadie lo pidió: el pedido fue
puntualmente "negro", no "un neutro en cada punta". Agregarlo por cuenta
propia para cerrar el número sería inventar alcance no pedido, lo mismo que
este documento evita hacer con las pantallas de negocio. 23 es el resultado
real de dos técnicas defendidas con rigor (matiz/croma hasta 22, más un
neutro pedido explícitamente) — más cerca de 24 que 22 a secas, sin
forzar ninguna de las dos.

### 21.1.1. Los 23 colores

| # | HEX | Nombre (es) | Constante PHP sugerida | Categoría / vuelta |
|---|-----|-------------|-------------------------|---|
| 1 | `#B6202D` | Rojo | `ROJO` | Saturado, 1ª |
| 2 | `#CD5E1D` | Naranja | `NARANJA` | Saturado, 1ª |
| 3 | `#AA8C18` | Ámbar / mostaza | `AMBAR` | Saturado, 1ª |
| 4 | `#218349` | Verde bosque | `VERDE_BOSQUE` | Saturado, 1ª |
| 5 | `#1E8F80` | Verde azulado | `VERDE_AZULADO` | Saturado, 1ª |
| 6 | `#1F80AD` | Turquesa | `TURQUESA` | Saturado, 1ª |
| 7 | `#2B50CA` | Azul | `AZUL` | Saturado, 1ª |
| 8 | `#4A30A6` | Índigo | `INDIGO` | Saturado, 1ª |
| 9 | `#9331C4` | Violeta | `VIOLETA` | Saturado, 1ª |
| 10 | `#A32995` | Púrpura | `PURPURA` | Saturado, 1ª |
| 11 | `#B92770` | Frambuesa | `FRAMBUESA` | Saturado, 1ª |
| 12 | `#B82343` | Carmín | `CARMIN` | Saturado, 2ª |
| 13 | `#755238` | Marrón | `MARRON` | Desaturado, 1ª |
| 14 | `#566F81` | Pizarra | `PIZARRA` | Desaturado, 1ª |
| 15 | `#467C61` | Musgo | `MUSGO` | Desaturado, 2ª |
| 16 | `#7B4D80` | Malva | `MALVA` | Desaturado, 2ª |
| 17 | `#766B42` | Caqui | `CAQUI` | Desaturado, 3ª |
| 18 | `#487A73` | Salvia | `SALVIA` | Desaturado, 3ª |
| 19 | `#4E597E` | Acero | `ACERO` | Desaturado, 3ª |
| 20 | `#5B4B81` | Aciano | `ACIANO` | Desaturado, 3ª |
| 21 | `#7C4665` | Vino | `VINO` | Desaturado, 3ª |
| 22 | `#7E4449` | Terracota | `TERRACOTA` | Desaturado, 3ª |
| 23 | `#121212` | Negro | `NEGRO` | Neutro, 3ª (pedido del dueño) |

Formato listo para copiar:

```php
// PHP — array de constantes (nombre de la clase, namespace y ubicación
// exacta dentro de app/Dominios/ quedan a criterio de backend)
public const ROJO = '#B6202D';
public const NARANJA = '#CD5E1D';
public const AMBAR = '#AA8C18';
public const VERDE_BOSQUE = '#218349';
public const VERDE_AZULADO = '#1E8F80';
public const TURQUESA = '#1F80AD';
public const AZUL = '#2B50CA';
public const INDIGO = '#4A30A6';
public const VIOLETA = '#9331C4';
public const PURPURA = '#A32995';
public const FRAMBUESA = '#B92770';
public const CARMIN = '#B82343';
public const MARRON = '#755238';
public const PIZARRA = '#566F81';
public const MUSGO = '#467C61';
public const MALVA = '#7B4D80';
public const CAQUI = '#766B42';
public const SALVIA = '#487A73';
public const ACERO = '#4E597E';
public const ACIANO = '#5B4B81';
public const VINO = '#7C4665';
public const TERRACOTA = '#7E4449';
public const NEGRO = '#121212';
```

```sql
-- Postgres CHECK (mayúsculas — si el backend normaliza a minúsculas antes
-- de guardar, ajustar la lista, pero SIEMPRE la misma capitalización en
-- los dos lugares: CHECK y constantes PHP no pueden divergir en el string
-- exacto o el `IN` rechaza valores que la constante sí produce)
CHECK (color IN (
    '#B6202D', '#CD5E1D', '#AA8C18', '#218349', '#1E8F80',
    '#1F80AD', '#2B50CA', '#4A30A6', '#9331C4', '#A32995',
    '#B92770', '#B82343', '#755238', '#566F81', '#467C61', '#7B4D80',
    '#766B42', '#487A73', '#4E597E', '#5B4B81', '#7C4665', '#7E4449',
    '#121212'
))
```

Si `backend` ya había aplicado la migración/constraint con los 16
anteriores, actualizar a los 23 es otra vez un `ALTER TABLE ... DROP
CONSTRAINT` + `ADD CONSTRAINT` con la lista nueva — sigue sin hacer falta
tocar filas existentes, porque ningún HEX de los 16 anteriores cambió en
esta vuelta tampoco (los 6 desaturados y el negro son estrictamente
aditivos).

### 21.2. Por qué la legibilidad del swatch depende del anillo, no del hex exacto

Medido (aprox., sRGB con gamma 2.2, no el método exacto de WCAG pero
suficiente para la decisión de diseño): un rojo de la paleta contra
`--ag-color-surface-card` en tema oscuro ronda **2.7:1**; el ámbar/mostaza
—el más "claro" perceptualmente— contra `--ag-color-bg-elevated` en tema
claro ronda **2.8:1**. Ninguno de los 22 tonos de matiz/croma llega a 3:1
contra los DOS extremos de fondo del panel a la vez (uno casi blanco,
`--ag-color-bg-elevated` claro; el otro casi negro, `--ag-color-surface-card`
oscuro) sin dejar de ser un color medio reconocible — los tres colores de la
segunda vuelta y los seis de la tercera (Carmín, Musgo, Malva, Caqui,
Salvia, Acero, Aciano, Vino, Terracota) se diseñaron dentro del mismo rango
de luminosidad que los 11 saturados originales (§21.1), así que esta
medición no cambia con ellos, no hizo falta remedirla pieza por pieza. El
color 23, Negro, es la única pieza que se diseña a propósito FUERA de ese
rango — ver el bloque dedicado en §21.1 sobre por qué ahí el anillo, no el
relleno, sigue siendo el mecanismo real, llevado a su caso límite. Por eso
`atoms/color-swatch-picker`
no confía en el relleno para la separación del fondo: cada swatch lleva un
anillo de 2px con `--ag-color-border-strong` (el token que ya significa
"borde de control interactivo" en el resto del catálogo) en reposo y
`--ag-color-primary` cuando está seleccionado — el mecanismo de legibilidad
es el token del anillo, no el ajuste fino de cada hex. Cualquier pieza de
solo-lectura que más adelante pinte estos mismos colores como chip de tabla
o relleno de polígono en el mapa (`frontend`, cuando conecte la pantalla de
`Propiedad`) **debería reusar el mismo anillo** en vez de redescubrir el
problema — y de todos modos verificarlo a ojo (checklist de
`guia_pantalla_panel.md` §8: "el cálculo en papel no alcanza"), en los dos
temas y sobre una imagen satelital real, antes de darlo por cerrado.

### 21.3. El átomo: `atoms/color-swatch-picker`

Ver el comentario de cabecera del propio archivo
(`resources/views/components/atoms/color-swatch-picker.blade.php`) para el
contrato completo de props y accesibilidad. Resumen de las decisiones que no
son obvias del código:

- **Independiente de `atoms/radio-group`**, no wrapper ni extensión: mismo
  mecanismo nativo (fieldset + radios reales + técnica visually-hidden),
  marcado propio — un atom no compone otro componente del catálogo
  (`guia_pantalla_panel.md` §2).
- **`options` es `hex => etiqueta`, no un id que resuelva contra otra
  tabla**: el HEX es tanto la clave que viaja en el `value` del radio como
  el color a pintar — coincide 1:1 con cómo vive el dato en Postgres (columna
  `color`, el HEX crudo, no una FK a una tabla de paleta).
- **La selección nunca depende solo del color**: estado real en el
  `:checked` nativo: insignia con ícono de check que APARECE (no solo
  cambia de color) sobre fondo `--ag-color-primary` fijo (nunca el color de
  la opción — así el check se lee igual sobre los 16 tonos) + anillo que
  pasa a `--ag-color-primary`. Nombre accesible del radio = nombre del color
  ("Verde bosque"), no el HEX ni "opción 4" — `<span>` visualmente oculto
  dentro del `<label>`.
- **No conecta a ningún formulario todavía** — lo arma `frontend` al
  construir la pantalla de alta/edición de `Propiedad`, con `options`
  resuelto desde las constantes PHP de §21.1 (traducidas vía `lang/es/`, el
  átomo no hardcodea ningún nombre de color).
- **Segunda vuelta (16/9/2026): cambió DÓNDE vive, no qué es.** El átomo en
  sí no se tocó ni una línea — sigue siendo el mismo fieldset de 16 radios
  con la misma accesibilidad. Lo que cambió es que ya no se pinta suelto en
  la fila del formulario: ahora vive dentro de `molecules/color-palette-modal`
  (§21.4), y la fila del formulario muestra en su lugar el control compacto
  `molecules/color-swatch-field`.

### 21.4. El control compacto y el modal: `molecules/color-swatch-field` + `molecules/color-palette-modal`

Pedido directo del dueño tras ver el resultado real en el navegador (los 13
círculos en fila de la primera vuelta competían por espacio en el
formulario): mover la elección
a un modal y dejar en la fila un control compacto — *"en input tendrá el
color, su hex y botón para cambiar, similar al input file de las fotos solo
que sin la altura del mismo input file image"*. Dos piezas nuevas de
catálogo, ambas molecule (ninguna puede ser atom: las dos componen
`atoms/button`, y un atom no compone otro átomo del catálogo,
`guia_pantalla_panel.md` §2):

- **`molecules/color-swatch-field`** — el control de la fila: swatch chico +
  HEX en texto (mono, nunca solo color — `guia_pantalla_panel.md` §6.3 regla
  6 para el hex, y la propia regla de accesibilidad de este átomo/molécula
  para "nunca solo color") + botón "Cambiar". Inspirado en la anatomía de
  `molecules/file-field` (preview + meta + acción) pero de UNA sola línea
  compacta — a propósito no repite la caja alta de preview de un archivo,
  que es justo lo que el dueño pidió evitar. No lleva `name`: no somete
  nada al formulario, es DISPLAY del valor actual + TRIGGER del modal
  (`data-bs-toggle="modal" data-bs-target="#{modalId}"` en el botón).
- **`molecules/color-palette-modal`** — el modal Bootstrap (mismo patrón
  `.modal.fade` + header/body/footer que ya usa el catálogo en
  `atoms/image-modal` y el partial `pages/contratos/_modal-lotes.blade.php`,
  título con `.ag-page-header__title`, footer con dos acciones) que aloja
  adentro a `atoms/color-swatch-picker` sin cambiarlo — acá SÍ vive el
  `name` real que somete el color al POST. Reutilizable por cualquier campo
  futuro de paleta curada, no solo `Propiedad.color`: es un passthrough casi
  directo de props hacia el átomo.

**Contrato de sincronización (el marcado ya lo soporta; el JS que lo
conecta lo escribe `frontend`, no esta pieza):**

| Pieza | Hook | Qué hace |
|---|---|---|
| `color-swatch-field` (raíz) | `data-ag-color-swatch-field` + `data-ag-color-swatch-field-modal="{modalId}"` | Identifica el control y a qué modal corresponde |
| `color-swatch-field` (swatch) | `data-ag-color-swatch-field-swatch` | JS setea `style.setProperty('--ag-color-swatch-fill', hex)` y quita `--empty` al elegir |
| `color-swatch-field` (texto) | `data-ag-color-swatch-field-hex` | JS setea `textContent` con el hex nuevo |
| `color-swatch-field` (nombre accesible) | `data-ag-color-swatch-field-name` | JS setea `textContent` con la etiqueta del color elegido |
| `color-palette-modal` (cada radio) | `data-ag-color-palette-modal-input` | Hook directo para `change` — llega a cada `<input>` porque `atoms/color-swatch-picker` reenvía `$attributes->except('class')` a los radios, no al `<fieldset>` (ver su comentario de cabecera) |

No hay hidden input duplicado ni segunda fuente de verdad: el único
`<input type="radio" name="color">` real es el de adentro del modal: al
elegir un swatch, su `:checked` ya queda committeado en el DOM aunque el
modal se cierre después (Bootstrap oculta el modal con CSS, no lo
desmonta) — el submit del formulario funciona aunque el JS de
sincronización visual todavía no exista. El botón "Aplicar" del footer del
modal solo cierra (`data-bs-dismiss="modal"`, igual que "Cancelar"): no hay
nada que descartar, la selección ya es inmediata al clickear un swatch.

Ejemplo de uso previsto (referencia para `frontend`, no conectado todavía —
ver la tarea que encargó estas piezas):

```blade
<x-molecules.color-swatch-field
    id="ag-propiedad-color-field"
    modal-id="ag-propiedad-color-modal"
    label="{{ __('comercial.propiedades.campo_color') }}"
    :value="$color ?: null"
    :color-name="$color ? \App\Dominios\Comercial\Dominio\ColorPropiedad::from($color)->etiqueta() : null"
    change-label="{{ __('comercial.propiedades.campo_color_cambiar') }}"
    placeholder-label="{{ __('comercial.propiedades.campo_color_vacio') }}"
    help="{{ __('comercial.propiedades.campo_color_ayuda') }}"
    error="{{ $errors->first('color') }}"
    class="ag-form-section__field--full"
/>

<x-molecules.color-palette-modal
    id="ag-propiedad-color-modal"
    title="{{ __('comercial.propiedades.campo_color_modal_titulo') }}"
    name="color"
    :options="$coloresDisponibles"
    :value="$color"
    cancel-label="{{ __('ui.action.cancel') }}"
    confirm-label="{{ __('comercial.propiedades.campo_color_modal_aplicar') }}"
/>
```

## 22. Tarea 141 (23/9/2026) — `molecules/notifications-menu`: avisos con destino y «Marcar todas como leídas»

La campana deja de ser decorativa. La molécula sigue siendo la misma que
comparten `organisms/topbar` y `organisms/mobile-topbar` (los dos llamadores
no cambian de API), pero cada aviso de la lista puede traer dos claves más:

- **`href`** (opcional): con él la fila es un enlace real. Sin él sigue siendo
  texto plano, como antes. El destino lo resuelve el servidor contra el rol
  activo (`panel.notificaciones.abrir`, ADR 0025 punto 6): el aviso nunca sabe
  a qué URL lleva.
- **`id`** (opcional): lo traen los avisos que nacen del motor de
  `Notificaciones`; las alertas técnicas de `ope_alertas` no lo llevan porque
  se atienden en su propia pantalla. Si hay al menos un aviso con `id` sin leer,
  el popover suma el pie **«Marcar todas como leídas»** (`ui.topbar.mark_all_read`,
  un `POST` con `@csrf`); sin ninguno, el pie no aparece.

Marcado y estilos:

- La fila es siempre un `<a class="ag-notification-item">` dentro de un `<li>`:
  un `<a>` sin `href` es un marcador de posición válido, así el markup y el
  estilo son idénticos con y sin destino.
- Hover/foco de una fila con destino: `--ag-color-bg-row-hover`. Una fila «sin
  leer» ya está tintada de `--ag-color-primary-subtle`, así que su hover suma un
  borde propio (`--ag-color-primary-border-subtle`, regla 4 de §8) en vez de
  cambiar a otro verde parecido.
- `.ag-notifications-popover__footer` / `__mark-all`: acción secundaria en
  texto plano, con fondo sutil y transición al pasar el puntero (regla 5 de §8).
  Solo tokens, ningún color propio; se comporta igual en tema claro y oscuro.
- Prop nueva documentada en `organisms/topbar`: `notifications` es ahora
  `{icon, title, time, unread, href?, id?}`.

Qué entra en la lista lo decide `Seguridad\...\Presentacion\CampanaDeAvisos`
(no la molécula): todo lo no leído primero, hasta el tope de 10, y después los
leídos más recientes. Así el badge —que cuenta lo no leído de la lista que
recibe— sigue siendo exacto hasta «9+».

## Acciones de fila, buscador y tablero por permisos

### Acciones de fila (`organisms/row-actions` + `molecules/row-action`)

Toda tabla del panel pinta sus acciones con un único componente, `organisms/row-actions`, que fija el orden sin importar cómo las declare quien lo usa:

1. **Ver** (`info-outline`)
2. **Editar** (`warning-outline`)
3. **Aprobar** (`success-outline`)
4. **Transiciones de la máquina de estados** (`<tono del estado de llegada>-outline`): rechazar, suspender, rehabilitar, bloquear, ocultar, etc.
5. **Eliminar** (`danger-outline`, token de peligro `--ag-color-danger`), siempre al final.

Cada acción es un `<x-molecules.row-action kind="ver|editar|aprobar|transicion|eliminar">` dentro del slot con nombre que le corresponde (`ver`, `editar`, `aprobar`, `transiciones`, `eliminar`). El `kind` fija el color; nadie lo elige a mano. No existe slot por defecto ni se admiten botones sueltos.

- Hasta tres acciones: botones de ícono cuadrados, con `title` y `aria-label` (el texto existe siempre y se oculta solo a la vista).
- Más de tres: todas pasan al menú «⋮» de la fila, con ícono y texto, en el mismo orden.
- Solo se ofrecen las acciones válidas para el estado actual y permitidas al rol; el llamador las filtra con `@puede`/`@if`, igual que en la ficha.
- Los `<form>` y los modales de confirmación van fuera del componente, en la celda.
- Un objeto con máquina de estados de aprobación (proveedor, piloto, dron) usa `organisms/row-actions-aprobacion`, que arma las acciones y los modales de la ficha (`_modales-aprobacion`) y vuelve al listado tras resolver (`desde=listado`). El «Revisar» aparte desapareció: revisar es abrir la ficha (Ver) y aprobar es una transición. «Dar de baja» se muestra como Eliminar.
- Clientes, calificaciones, documentos y usuarios exponen sus transiciones con el mismo componente. Las disputas y las solicitudes tienen transiciones con formularios propios (resultado, reembolso, motivo) y siguen en su ficha.

`RowActionsOrdenTest` congela el orden y el color; `PanelHomogeneoTest` exige que ningún listado tenga botones sueltos ni una acción en un slot ajeno.

### Imágenes y documentos: cuadrícula y modal de revisión (`organisms/cuadricula-medios` + `organisms/modal-revision-medio`)

Toda ficha con imágenes o documentos por aprobar (fotos del dron; documentos de proveedor, piloto y dron) los muestra como un lienzo de bloques, no como miniaturas con botones debajo, y la revisión ocurre en un modal con el contenido completo. Nunca se actúa sobre una miniatura.

- **Cuadrícula.** Rectángulos de `--ag-color-surface-card` sobre `--ag-color-bg`, con separación pequeña y uniforme (`--ag-space-2`). El primer bloque es el grande (2x2 desde 768 px) y el primero pendiente de aprobación se resalta con `--ag-color-success-subtle`. Cada bloque lleva la miniatura (`url_miniatura`, `cover` centrada), el nombre del tipo, el badge de estado y las marcas «Obligatoria» y «Pendiente de aprobación» (ícono y texto, el color no es la única señal). Un PDF u otro archivo sin vista previa muestra un ícono por tipo y la etiqueta «Sin vista previa»; un archivo ausente, «Archivo no disponible». El bloque es un botón que abre el modal: sin formularios, sin Aprobar, Rechazar ni Descargar.
- **Modal.** Contenido completo (`url_media`; imagen ajustada con «Ampliar», que pide el original; PDF embebido con enlace a la pestaña nueva), datos (tipo, estado, fecha, peso, formato, obligatoria), Aprobar, Rechazar (el motivo es obligatorio y aparece al pulsar Rechazar) y Descargar original. Anterior y Siguiente (y las flechas del teclado) recorren los archivos de la ficha. Es el modal de Bootstrap: foco atrapado, Esc cierra, el foco vuelve al bloque, y sin movimiento con `prefers-reduced-motion`.
- **Contrato.** Cada ítem es un `ItemDeRevisionDeMedio::aArray()` (el `ArchivoDeMedio` más estado, `puede_aprobar`, `puede_rechazar`, `url_revisar`). Los permisos se deciden al armar el ítem (`ItemsDeRevisionDeDocumentos`, `ItemsDeRevisionDeFotos`): sin `proveedores.documento.descargar` no hay `url_descarga` y el botón no existe; si `existe` es falso el modal lo avisa y bloquea Aprobar. Las fotos del dron no tienen descarga.
- **Flujo.** Aprobar o rechazar envía el formulario del modal, la página se recarga y el modal se reabre en el siguiente pendiente (`sessionStorage`). La cola `/panel/documentos` conserva su tabla: su acción «Revisar el archivo» abre el mismo modal. El visor aislado `/panel/documentos/{id}/archivo` se conserva.
- **Uso.** `<x-organisms.cuadricula-medios id="…" :items="$items" />` (incluye su modal); cualquier otro disparador usa `data-medio-modal` y `data-medio-clave`. JS en `resources/js/organisms/modal-revision-medio.js`; textos en `lang/es/revision_medios.php`.

### Buscador y «Limpiar filtros»

Todo listado lleva `molecules/table-search` (con `clear-label`) y, si tiene filtros, `organisms/filter-panel` con `active-count`, que pinta «Limpiar filtros» cuando hay alguno activo. Si un listado abre con un filtro por omisión (documentos, en «En revisión»), `filter-panel` recibe `clear-url` hacia la URL que significa «todos» (`?estado=`); si no, limpiar volvería a aplicar el filtro. `table-search` acepta `preserve` para no perder los demás filtros al buscar. Única excepción documentada en `PanelHomogeneoTest`: el ranking de métricas, de solo lectura.

### Tablero y accesos rápidos por permisos

No hay selector de vista ni «ver como»: el tablero muestra cada bloque solo si la sesión tiene el permiso de lo que contiene (solicitudes con `solicitudes.solicitud.ver`, cada cola pendiente con el permiso de su pantalla). Los accesos rápidos de «Mi perfil» salen del menú del rol y se completan con sus primeras pantallas; cada tarjeta lleva un color distinto dentro de la lista mostrada (hasta 6): el cuadro del ícono toma uno de siete tonos con tokens existentes (`info`, `distintivo-1`, `distintivo-2`, `warning`, `distintivo-3`, `success`, `primary-2`; `danger` y `neutral` quedan fuera porque significan error y «sin estado»). El tono preferido sale de la ruta de destino (`crc32` de la ruta), no del dominio ni de la posición, así no cambia entre visitas; si ya lo tiene una tarjeta anterior se desplaza al siguiente libre, en orden fijo. No hizo falta un token nuevo: los siete usan el relleno sólido `-contrast-fill` con ícono blanco, el mismo par que ya se usa en `link-row`. El color nunca es la única señal: cada tarjeta lleva ícono y texto.

## 19. Gráficas del panel — `molecules/grafico` (Chart.js)

El tablero y las métricas del mercado muestran gráficas en vez de tablas con números: se leen de un vistazo y la tabla queda como alternativa. Se usa **Chart.js 4 (MIT)**, instalado con npm y empaquetado por Vite; no se usa `spatie/laravel-dashboard` ni sus tiles (exigen Livewire, que el panel no tiene, y traen su propio estilo fuera de los tokens). Motivo de la dependencia: es el motor de gráficas más usado y mantenido con licencia MIT, se carga por módulos (solo entran los controladores de barras y líneas) y dibuja en un `<canvas>` que se estila por completo desde JavaScript, lo que permite leer los tokens.

### 19.1. Componente

`<x-molecules.grafico>` (props documentadas en el propio Blade): `titulo`, `ayuda`, `tipo` (`barras` o `lineas`), `horizontal`, `apilado`, `etiquetas`, `series` (nombre, valores y el nombre de un token de color), `formato` (decimales y sufijo), `categoria` y `vacio`. Los datos llegan resueltos del servidor (los lectores de `Metricas`) y viajan en un `data-ag-grafico` en JSON, solo con lo que se grafica. El CSS es `components/grafico.css`; las páginas con varias gráficas las componen en un lienzo (§23); `.ag-grafico-grilla` queda como grilla simple de dos columnas.

### 19.2. Qué tipo de gráfica

- **Línea** para una magnitud en el tiempo (actividad semanal, precio por hectárea); las semanas sin dato quedan sin punto, no en cero.
- **Barras** para comparar categorías; horizontales cuando los nombres son largos o son más de cinco (estados, departamentos, pasos del embudo).
- **Apiladas** solo para partes de un mismo total (solicitudes de un departamento por grupo de estado).
- **Sin dona**: ninguna medición del panel tiene tan pocas partes como para que valga la pena.

### 19.3. Tokens y tema

`resources/js/shared/color-tokens.js` lee los tokens en el momento de dibujar: colores (el token se aplica a un elemento oculto y se lee de vuelta pasando por un píxel de canvas, porque los `color-mix()` no siempre salen como `rgb()`), familias (Manrope para rótulos, JetBrains Mono para cifras de ejes y tooltip), tamaños, radios, espacios y la duración de `--ag-transition-base`. Una serie nombra un token semántico (`success`, `info`, `warning`, `danger`, `distintivo-1`, `distintivo-2`); ninguna gráfica declara un color propio. Un `MutationObserver` sobre `data-bs-theme` las repinta al cambiar de tema.

### 19.4. Accesibilidad y estados

- Sin animación con `prefers-reduced-motion: reduce` (también si cambia con la página abierta); con `prefers-contrast: more` las líneas son más gruesas.
- Las series de línea se distinguen también por la forma del punto, no solo por el color; las barras apiladas llevan leyenda y borde de superficie.
- El lienzo es `role="img"`, se rotula con el título y se describe con la tabla «Ver datos», que trae los mismos números con encabezados de fila y de columna. Es la alternativa para lector de pantalla, teclado y para quien no cargue JavaScript.
- Estados: cargando (hasta que Chart.js dibuja), error (JSON ilegible: se indica abrir la tabla), vacío (texto que orienta, sin lienzo) y sin dato en un punto (la tabla dice «sin datos», el tooltip «—»).
- El tooltip usa los tokens de scrim (`--ag-color-scrim-strong` y `--ag-color-text-on-scrim`), legibles en ambos temas.

### 19.5. Carga diferida

Chart.js pesa unos 60 KB comprimidos, así que va en un chunk aparte (`grafico-*.js`) que `app.js` pide con `import()` solo si la página tiene un `[data-ag-grafico]`, con la misma convención de la §13.1: el módulo dibuja de inmediato al cargarse porque `DOMContentLoaded` ya pasó.

## 23. Lienzo — composición de bloques por fracciones (`templates/lienzo`)

Las páginas con varias gráficas y tarjetas (el tablero y Métricas del mercado) no las reparten en una grilla uniforme: las componen como un lienzo, bloques blancos sobre un fondo gris con separación pequeña y uniforme, una pieza principal y columnas de distinta anchura.

**Piezas.** `<x-templates.lienzo>` es una fila o una columna de bloques; `<x-molecules.lienzo-bloque>` envuelve cada pieza (una tarjeta, una gráfica, una tira de KPI u otro lienzo) y la hace llenar su espacio. Es recursivo: un bloque puede llevar otro lienzo.

- `direccion="fila"`: los hijos van lado a lado y `fracciones` reparte el ancho. `direccion="columna"`: van uno bajo otro y `fracciones` reparte el alto.
- `fracciones`: números sobre una base de 12 («4 8», «6 6», «12»). Se pintan como `minmax(0, Nfr)` con CSS grid; no hay alturas fijas en píxeles.
- `raiz`: el lienzo más externo. Pone el fondo (`--ag-color-bg-lienzo`), mide su propio ancho con una container query y fija su alto (`alto` unidades de `--ag-space-8`) para que las fracciones de las columnas tengan sobre qué repartirse. Los anidados toman el alto de su bloque.
- Separación `--ag-space-3`, radios `--ag-radius-lg`; todo color y medida sale de tokens.

**Responsive.** Mientras el lienzo mida menos de 60rem (celular, o escritorio con poco contenido) no reparte nada: apila en una columna en el orden del código, así que la pieza principal va primero. Desde 60rem aplica las fracciones; la gráfica toma el alto que sobra de su tarjeta (`flex: 1 1 0`) y Chart.js, con `responsive` y `maintainAspectRatio: false`, se redibuja cuando cambia el bloque. Un bloque de alto repartido no crece con su contenido: si no cabe (la tabla «Ver datos» abierta, la cola de pendientes) se desplaza dentro de su tarjeta. La medida es la del lienzo, no la de la pantalla: con el menú lateral abierto a 1024 px el tablero todavía apila.

**Accesibilidad.** Cada gráfica conserva su título, su `canvas` con etiqueta y su tabla alternativa «Ver datos»; las tarjetas y enlaces mantienen el foco visible. El orden del código es el orden de lectura.

**Tablero** (`/panel`), `alto=21`:

```
+---------------------------------------------------------+
|  PRINCIPAL  Actividad semanal (líneas)            (12)  |  fila 4/12
+--------------------+------------------------------------+
| Solicitudes por    | Hectáreas por     | Precio por     |
| estado             | departamento (6)  | hectárea (6)   |  fila 8/12
| (barras)    (7/12) |                   |                |
+--------------------+-------------------+----------------+
| Esperan tu         | Tarjetas KPI                 (12)  |
| decisión    (5/12) |                                    |
+--------------------+------------------------------------+
   columna 4/12          columna 8/12 (filas 6/6)
```

La actividad semanal es la principal porque cuenta la historia del mercado de un vistazo. Las barras largas de estados piden altura (diez categorías) y van en la columna angosta y alta; los departamentos y el precio por semana piden ancho medio. Si el rol no puede abrir pendientes, el bloque de estados toma toda la columna; sin permiso de solicitudes o sin solicitudes no hay lienzo y se muestran las tarjetas, el estado vacío y la cola.

**Métricas del mercado** (`/panel/metricas`): una composición más calmada, una sección por pestaña (`molecules/tabs`, Bootstrap `tab`), con el selector de período siempre arriba. La pestaña activa viaja en el hash de la URL (`pages/metricas-secciones.js`), también en los enlaces de período.

```
Mercado                      Negociación               Proveedores            Calidad e ingreso
+----------------+-----+     +---------------------+   +---------------------+ +------------+-----+
| Embudo   (8)   | KPI |     | KPI tiempo | KPI of.|   | KPI x4              | | Calidad(8) | Ing.|
|                | (4) |     +--------------------+    +---------------------+ | 3 KPI      | (4) |
+----------------+-----+     | Tiempo (7) | Rondas |   | Ranking             | +------------+-----+
| Departamentos (12)   |     |            |  (5)   |   | (solo lectura)      |
+----------------------+     +--------------------+    +---------------------+
```

*Qué salió y por qué (decisión del dueño si quiere algo de vuelta).*

- Se quedó en análisis: embudo, departamentos, tiempo hasta adjudicar, rondas, las tarjetas de calidad y de oferta y el ranking de proveedores. Nada se movió al tablero: lo accionable (pendientes, solicitudes en mercado, precio por hectárea) ya estaba allí y las cifras de Métricas dependen del período elegido, que el tablero no tiene.
- Salió: la tarjeta «Ingreso por hectárea» (decía «comisión generada», y la aplicación no cobra comisión; el precio por hectárea acordado ya está en el tablero) y el encabezado «Embudo y negociación». El dato sigue calculándose en `BloqueIngreso`.
- Se fundieron: «Solicitudes publicadas» y «Tasa de adjudicación» quedan junto al embudo con la tercera cifra (hectáreas servidas) en vez de repetirse en una tira aparte; las dos cifras de negociación (tiempo hasta aceptar, ofertas por solicitud) quedan sobre sus gráficas.
- Detrás de «Ver datos en una tabla» siguen las tablas de cada gráfica; el ranking de proveedores queda a la vista porque no tiene gráfica que lo reemplace.
