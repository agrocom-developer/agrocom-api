# Sistema de diseño del panel — tokens y catálogo Atomic Design (HU-02)

**Mantiene:** agente `design-ui`. **Depende de:** ADR 0002 (AdminLTE + Atomic Design), ADR 0004 (rol activo, ADR extendido 27/8/2026), ADR 0013 (multi-idioma UI), CLAUDE.md invariante 11 (ningún color hardcodeado).

Este documento es el catálogo vigente de tokens y componentes de presentación del panel. No es un ADR (no fija arquitectura, ADR 0002 ya lo hizo) — es el inventario vivo de qué existe, en qué nivel de Atomic Design, y qué falta. Se actualiza cada vez que `design-ui` agrega o cambia un token o un componente.

> **Tampoco es la receta para construir una pantalla.** Está escrito como bitácora ("quinta vuelta", "novena vuelta") y sirve para buscar el VALOR exacto de algo o entender por qué se decidió así. Si lo que vas a hacer es armar una pantalla nueva —dónde va cada archivo, con qué se compone un tablero / un listado / un formulario, qué verificar antes de cerrar— eso está en **`docs/diseno/guia_pantalla_panel.md`**.

## 1. Tokens CSS

Ubicación: `resources/css/tokens/`. Import único desde `resources/css/app.css` vía `resources/css/tokens/index.css`.

### 1.1. Dos capas, deliberadamente separadas

1. **Primitivos** (`tokens/primitives/`) — valores literales (hex). Ningún componente los usa directamente.
   - `brand.css`: **solo** las rampas verde/ámbar de Agrocom. Es la única pieza del sistema atada a la identidad visual de esta marca — ver §1.4.
   - `base.css`: escala neutra (grises), colores de estado universales (rojo/amarillo/azul — la marca no define un rojo), y las escalas de espaciado/tipografía/radio/elevación/transición. No tiene nada de marca.
2. **Semánticos** (`tokens/semantic/theme-light.css`, `theme-dark.css`) — los tokens que de verdad referencian los componentes (`--ag-color-bg`, `--ag-color-text`, `--ag-color-primary`, etc.), resueltos sobre los primitivos de ambos archivos de la capa 1. El tema activo se resuelve reasignando **estos** tokens vía el atributo nativo de Bootstrap 5.3 `[data-bs-theme="light"|"dark"]` en el `<html>` — nunca duplicando una regla de componente por tema.

Por qué la separación brand/base dentro de la capa 1 (pedido explícito, no implementado, solo organizativo): hoy el panel es de una sola marca. Si en el futuro el sistema se vendiera como SaaS multi-tenant (cada cliente con su logo/colores propios), el único archivo que un tenant nuevo necesitaría sobreescribir es `primitives/brand.css` — ningún componente, ninguna vista, ni `base.css`, ni la capa semántica cambiarían, porque nada fuera de `semantic/` referencia un hex directamente. **Esto no implementa multi-tenant**: no hay tabla, no hay resolución por tenant, no hay selector de marca — es solo el criterio de organización del archivo de tokens que de todos modos había que escribir para HU-02.

### 1.2. Paleta de marca (verificada contra los logos oficiales)

Confirmado contra `public/logo.png` (antes `logo-dark.jpeg`/`logo-light.jpeg`, mismo isotipo) y la memoria de proyecto (`paleta-colores-marca-agrocom`) — no se inventaron valores nuevos:

| Rampa | 100 | 300 (logo) | 500 | 700 (logo) | 900 |
|---|---|---|---|---|---|
| Verde | `#E8F4D9` | `#8CC63F` | `#55A03A` | `#1E7A34` | `#0F3D1A` |
| Ámbar | `#FDEDD3` | `#F5A623` | `#EF8C18` | `#E8720C` | `#743906` |

Los tonos "300" y "700" son los dos extremos exactos del degradado leído en cada logo (verde lima→bosque, ámbar del ícono de señal); 100/500/900 son interpolaciones/mezclas para dar una rampa completa (tinte claro, punto medio, sombra oscura) sin inventar un hue nuevo.

### 1.3. Verificación de contraste (WCAG 2.1 AA, 4.5:1 texto normal)

| Combinación | Ratio aprox. | Resultado | Dónde se usa |
|---|---|---|---|
| Texto blanco sobre `--ag-color-primary` (verde-700) | 5.4:1 | Pasa AA | Botón primario, ambos temas |
| Texto gris-900 sobre `--ag-color-accent` (ámbar-700) | 5.0:1 | Pasa AA | Badge de rol/menú sobre `--ag-color-accent-subtle` (topbar.css, menu-item.css) — ahí sigue siendo gris-900, fondo muy claro |
| Texto blanco sobre ámbar-700 | 3.1:1 | Falla AA texto normal | Por eso ningún consumidor de `--ag-color-accent` como relleno usa blanco |
| Texto ámbar-100 sobre `--ag-color-accent` (ámbar-700) | ~5.1:1 | Pasa AA | Botón `variant="accent"` (relleno sólido, hoy solo "Enviar solicitud" del tab de recuperar acceso) — `--ag-color-accent-contrast-fill`, ambos temas. Gris-900 ahí pasaba contraste igual, pero se leía muy duro/negro sobre el naranja vivo; ámbar-100 es un blanco cálido que también pasa AA sin ese efecto |
| `--ag-color-text` (gris-900) sobre `--ag-color-bg`/`--ag-color-bg-elevated` (tema claro) | ~15.8:1 | Pasa AAA | Texto de cuerpo, tema claro |
| `--ag-color-text-muted` (gris-600) sobre blanco | ~4.7:1 | Pasa AA (al límite) | Texto secundario, tema claro |
| `--ag-color-text` (gris-100) sobre `--ag-color-bg` (gris-950, tema oscuro) | ~14.9:1 | Pasa AAA | Texto de cuerpo, tema oscuro |
| `--ag-color-primary-emphasis` (verde-300) sobre `--ag-color-bg-chrome` negro puro (tema oscuro) | ~10.3:1 | Pasa AAA | Ítem de menú activo sobre sidebar oscuro |
| `--ag-color-primary-emphasis` verde-700 (si se usara igual que en claro) sobre negro puro | ~2:1 | Falla | Por eso `-emphasis` se aclara a verde-300 en tema oscuro, mientras el relleno sólido del botón (`--ag-color-primary`) se mantiene igual en ambos temas |
| `--ag-color-text` (gris-100) sobre `--ag-color-bg-elevated` (gris-850, tema oscuro) | ~15.0:1 | Pasa AAA | Tarjeta de `login-form` dentro de `auth-layout`, tema oscuro — comparable a los ~15.8:1 de la misma tarjeta en tema claro (fila de arriba) |
| `--ag-color-text-muted` (gris-500) sobre `--ag-color-bg-elevated` (gris-850, tema oscuro) | ~8.0:1 | Pasa AAA | Subtítulo/help text de `login-form`, tema oscuro |
| `--ag-color-text-on-scrim` (blanco) sobre foto + `--ag-color-scrim-strong` (zona del wordmark, cielo claro de `drone-hero.jpg`) | ~5.7:1 (estimado — sin muestreo de píxeles, foto real) | Pasa AA | Wordmark de `templates/auth-layout`, **idéntico en ambos temas** (token constante) |
| `--ag-color-text-on-scrim` (blanco) sobre foto + `--ag-color-scrim` (zona del headline, follaje/campo más oscuro) | ~7.5:1 (estimado) | Pasa AAA | Headline de `templates/auth-layout`, **idéntico en ambos temas** |
| `--ag-color-text` sobre `--ag-color-bg-auth` (tema claro, "crema anaranjado" ~`#fefaf2`, cuarta vuelta) | ~14.9:1 | Pasa AAA | Texto principal del panel de formulario de login. Bajó levemente de ~15.6:1 (era un poco más oscuro/ocre) pero sigue muy por encima de AAA |
| `--ag-color-text-muted` (gris-600) sobre `--ag-color-bg-auth` (tema claro) | ~4.5:1 (al filo del umbral, cálculo de precisión insuficiente para asegurar de qué lado cae) | Indeterminado/al límite — no se puede dar por bueno | Por qué sigue existiendo `--ag-color-text-muted-auth`: el fondo más claro de la cuarta vuelta mejora el ratio del muted global (era ~4.3:1, falla clara) pero lo deja apenas en el borde de 4.5:1, no con margen — no alcanza para dejar de declarar la variante propia |
| `--ag-color-text-muted-auth` (gris-700) sobre `--ag-color-bg-auth` (tema claro) | ~7.9:1 | Pasa AAA | Labels uppercase, subtítulos, pie mono de `login-form`, tema claro. Subió de ~7.5:1 (el nuevo fondo es más claro) |
| `--ag-color-accent-link` (ámbar-800) sobre `--ag-color-bg-auth` (tema claro) | ~5.2:1 | Pasa AA | Link "¿Olvidaste tu contraseña?", tema claro. Subió de ~4.9:1 |
| `--ag-color-primary-emphasis` (verde-700) sobre `--ag-color-bg-auth` (tema claro) | ~5.2:1 | Pasa AA | Link "Volver al ingreso" (estado de recuperación), tema claro — reutiliza el token existente, no necesita uno propio. Subió de ~4.9:1 |
| `--ag-color-text` sobre `--ag-color-bg-auth` (tema oscuro, "grafito-petróleo con dejo verde" ~`#1a261d`, cuarta vuelta) | ~14.2:1 | Pasa AAA | Texto principal del panel de formulario de login, tema oscuro. Prácticamente sin cambio respecto al tono anterior |
| `--ag-color-text-muted` (gris-500) sobre `--ag-color-bg-auth` (tema oscuro) | ~7.6:1 | Pasa AAA (con menos margen que antes) | El muted global sigue pasando cómodo en oscuro — `--ag-color-text-muted-auth` reutiliza el mismo valor. Bajó de ~8.2:1 (el nuevo fondo es más luminoso), pero se mantiene por encima del piso AAA (7:1) |
| `--ag-color-accent-link` (ámbar-300) sobre `--ag-color-bg-auth` (tema oscuro) | ~7.8:1 | Pasa AAA | Link "¿Olvidaste tu contraseña?", tema oscuro. Bajó de ~8.4:1, sigue AAA |
| `--ag-color-primary-emphasis` (verde-300) sobre `--ag-color-bg-auth` (tema oscuro) | ~7.7:1 | Pasa AAA | Link "Volver al ingreso", tema oscuro. Bajó de ~8.3:1, sigue AAA |

Consecuencia de diseño explícita: **el relleno sólido de marca (botones) es constante entre temas** (identidad de marca no cambia); lo que sí se reasigna por tema son los tonos usados como *texto/ícono sobre una superficie* (`-emphasis`) y los fondos tenues (`-subtle`), porque esos sí dependen de si están sobre blanco o sobre negro. El mismo criterio se extiende (HU-02, refinamiento editorial de `auth-layout`) a `--ag-color-scrim*`/`--ag-color-text-on-scrim`: la fotografía de fondo no reasigna por tema, así que el overlay y el texto que van montados sobre ella tampoco — usar `--ag-color-text-inverse` ahí sería un bug real en tema oscuro (resuelve a gris-900, casi invisible sobre la foto oscurecida).

### 1.4. Inventario de tokens semánticos

| Token | Uso |
|---|---|
| `--ag-color-bg`, `--ag-color-bg-elevated`, `--ag-color-bg-chrome` | Fondo de página, fondo de tarjeta/input, fondo de sidebar/topbar |
| `--ag-color-text`, `--ag-color-text-muted`, `--ag-color-text-inverse` | Texto principal, secundario, texto sobre fondos oscuros/de color |
| `--ag-color-border`, `--ag-color-border-strong` | Divisores sutiles vs. bordes de controles interactivos |
| `--ag-color-primary(-hover|-contrast|-emphasis|-subtle)` | Verde de marca — botones, foco, estado activo |
| `--ag-color-accent(-hover|-contrast|-emphasis|-subtle)` | Ámbar de marca — CTA secundario, resaltados |
| `--ag-color-success|danger|warning|info(-subtle)` | Estados de formulario/alertas |
| `--ag-color-neutral-subtle` (nuevo, mockup de dashboard 2026-08-28) | Gris "sin estado" para `atoms/badge`/`molecules/stat-card` variante "neutral" — un peldaño más marcado que `--ag-color-bg`/`-bg-elevated` (gray-200 claro / gray-800 oscuro) para que el pill/chip se distinga de la superficie sin necesitar tinte de color |
| `--ag-color-focus-ring` | Contorno de foco de teclado (accesibilidad) |
| `--ag-space-1`…`--ag-space-8` | Espaciado, escala de 8px |
| `--ag-font-family-base|mono`, `--ag-font-size-*`, `--ag-line-height-*`, `--ag-font-weight-*` | Tipografía de texto/cifras |
| `--ag-font-family-display` | Tipografía de titulares grandes tipo hero (HU-02). Aditivo, no reemplaza `-base` en ningún otro lado — la usan `templates/auth-layout` (wordmark + headline), `organisms/login-form` (título de "Ingreso"/"Recuperar acceso") y el resto de consumidores de §7.3/§11.2. **"IBM Plex Sans Condensed"** (`@fontsource/ibm-plex-sans-condensed`, pesos 400/500/600/700) desde la séptima vuelta (§11.2) — reemplazó a "Fraunces" (serif), que a su vez había reemplazado a "Instrument Serif" en la tercera vuelta. Historial completo en §11.2 |
| `--ag-radius-sm|md|lg|pill` | Radio de borde |
| `--ag-shadow-sm|md|lg` | Elevación (vocabulario Material) |
| `--ag-transition-fast|base` | Duración de transiciones |
| `--ag-ease-emphasized|drawer` | Curvas cubic-bezier propias (solo la curva, HU-02) — entradas/expansiones (stagger de menú, popover de rol, tarjeta de auth) y deslizamiento de paneles tipo cajón (sidebar offcanvas), respectivamente |
| `--ag-color-scrim-strong`, `--ag-color-scrim`, `--ag-color-text-on-scrim` | Overlay oscuro y texto sobre la foto de `auth-layout` (HU-02, refinamiento editorial) — **constantes entre temas** a propósito (la foto no reasigna por tema); nunca usar `--ag-color-text-inverse` sobre la foto, ver §1.3 |
| `--ag-color-bg-auth` (nuevo, HU-02 rediseño de login, tercera vuelta; retocado en la cuarta) | Fondo del panel de formulario de `auth-layout` — reemplaza el uso de `--ag-color-bg-elevated` ahí. Tono propio ("crema anaranjado" en claro, "grafito-petróleo con dejo verde" en oscuro — antes "ocre pastel"/"verde-oliva muy oscuro", ajustado a pedido en la cuarta vuelta, ver nota más abajo), deliberadamente distinto del gris neutro del resto del panel, para que ninguna otra tarjeta genérica que sí use `-bg-elevated` herede el tinte sin querer |
| `--ag-color-text-muted-auth` (nuevo, ídem) | Variante del texto muted, exclusiva de superficies montadas sobre `--ag-color-bg-auth` — el `--ag-color-text-muted` global cae bajo AA (~4.3:1) contra el nuevo fondo tintado en tema claro; en oscuro reutiliza el mismo valor del global (ya pasa cómodo). Ver §1.3 |
| `--ag-color-accent-link` (nuevo, ídem) | Ámbar seguro como TEXTO de enlace ("¿Olvidaste tu contraseña?") — distinto de `--ag-color-accent-emphasis`, que en tema claro resuelve a ámbar-700 y ya está documentado como no apto para texto. Usa el primitivo nuevo `--ag-color-amber-800` en claro, ámbar-300 en oscuro (mismo valor que `-accent-emphasis` en oscuro, pero separado semánticamente por contrato: "seguro para texto") |
| `--ag-color-wordmark-accent` (nuevo, HU-02 tercera vuelta) | Ámbar para la parte "COM" del wordmark bicromía de `auth-layout` — constante entre temas (igual que `--ag-color-scrim*`) porque la foto no reasigna por tema. Ambos temas usan ámbar-300 (verde oscuro = identidad de marca fija en la tipografía editorial) |
| `--ag-color-chip-icon` (nuevo, ídem) | Verde para el ícono `flight_takeoff` del chip de tagline de `auth-layout` — constante entre temas, mismo criterio que el wordmark. Ambos temas usan verde-300 (identidad de marca fija) |
| `--bs-body-bg`, `--bs-body-color`, `--bs-border-color`, `--bs-primary(-rgb)`, `--bs-secondary(-rgb)`, `--bs-success`, `--bs-danger`, `--bs-warning`, `--bs-info` | Las mismas variables nativas de Bootstrap 5, reasignadas a los tokens de arriba para que `.btn-primary`, `.alert-success`, y el propio AdminLTE hereden la marca **sin overrides por componente** |

## 2. Mezcla Bootstrap / Material — criterio por tipo de componente

| Qué | Sistema base | Por qué |
|---|---|---|
| Grid, contenedores, utilidades de espaciado/flex, breakpoints responsivos, offcanvas (sidebar en mobile) | Bootstrap | Es la base de AdminLTE (ADR 0002 punto 2); reinventar un grid propio no aporta nada |
| Botones, inputs, cards, formularios | Material (forma "outlined"/pill, elevación, radios de borde mayores) montado sobre marcado propio — no se usa `.form-control`/`.btn` de Bootstrap para no heredar su estética por defecto, que es justo lo que ADR 0002 pide reemplazar | Ver `resources/css/components/input.css`, `button.css` |
| Iconografía | Material Symbols (ligadura de texto, variable font) | Set único, permisivo, reemplaza a los íconos de Bootstrap Icons/FontAwesome que trae AdminLTE por defecto |
| Tipografía | **IBM Plex Sans** (texto) / **IBM Plex Mono** (cifras tabulares) como única familia base — deliberadamente *no* Roboto. Roboto es el default de Material por asociación automática, y ADR 0002 pide el comportamiento/estética de Material para inputs/cards/iconos, no que se herede su tipografía de fábrica. Plex tiene personalidad técnica acorde a un panel B2B agro-industrial, buena legibilidad en tablas/formularios densos (el uso real de este panel), cifras tabulares nativas para hectáreas/montos/códigos de sesión, y se auto-hospeda desde Google Fonts (coherente con que `frontend` instala fuentes por build, no por CDN) | Evita "panel genérico sin pensar la tipografía"; ver `--ag-font-family-base`/`--ag-font-family-mono` |
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
| Molecule | `menu-item` | `resources/views/components/molecules/menu-item.blade.php` | Implementado |
| Organism | `collapsible-menu-group` | `resources/views/components/organisms/collapsible-menu-group.blade.php` | Implementado |
| Organism | `login-form` | `resources/views/components/organisms/login-form.blade.php` | Implementado |
| Organism | `sidebar-nav` | — | **Renombrado** a `module-sidebar` (28/8/2026, quinta vuelta: el sidebar plano pasó a ser el nivel 2 del layout de tres niveles). El archivo con este nombre ya no existe. |
| Organism | `topbar` | `resources/views/components/organisms/topbar.blade.php` | Implementado |
| Template | `panel-layout` | `resources/views/components/templates/panel-layout.blade.php` | Implementado |
| Template | `auth-layout` | `resources/views/components/templates/auth-layout.blade.php` | Implementado |
| Atom | `badge` | `resources/views/components/atoms/badge.blade.php` | Implementado (2026-08-28) |
| Atom | `switch` | `resources/views/components/atoms/switch.blade.php` | Implementado (2026-08-28) |
| Molecule | `stat-card` | `resources/views/components/molecules/stat-card.blade.php` | Implementado (2026-08-28) |
| Molecule | `plan-card` | `resources/views/components/molecules/plan-card.blade.php` | Implementado (2026-08-28) |
| Molecule | `form-section` | `resources/views/components/molecules/form-section.blade.php` | Implementado (2026-08-28); **evolucionado** de `<fieldset>` desnudo a tarjeta con header `section-head` + grid de dos columnas (2/9/2026, tarea 31 — ver §14) |
| Molecule | `role-card` | `resources/views/components/molecules/role-card.blade.php` | Implementado (2026-08-28, quinta vuelta) |
| Molecule | `section-head` | `resources/views/components/molecules/section-head.blade.php` | Implementado (2026-08-28, sexta vuelta parte 2) |
| Molecule | `donut-chart` | — | **Retirado** (28/8/2026, auditoría visual externa obs. #5/#6 — ver §10.5). Reemplazado por `distribution-bar`, mismo prop shape. |
| Molecule | `distribution-bar` | `resources/views/components/molecules/distribution-bar.blade.php` | Implementado (2026-08-28, auditoría visual externa obs. #5/#6) |
| Molecule | `apex-chart` | `resources/views/components/molecules/apex-chart.blade.php` | Implementado (29/8/2026, novena vuelta — ver §13) |
| Molecule | `lote-resumen-card` | `resources/views/components/molecules/lote-resumen-card.blade.php` | Implementado (29/8/2026, novena vuelta) |
| Molecule | `captura-rc-card` | `resources/views/components/molecules/captura-rc-card.blade.php` | Implementado (29/8/2026, novena vuelta) |
| Organism | `mapa-operativo` | `resources/views/components/organisms/mapa-operativo.blade.php` | Implementado (29/8/2026, novena vuelta — ver §13) |
| Molecule | `alert-strip` | `resources/views/components/molecules/alert-strip.blade.php` | Implementado (28/8/2026, sexta vuelta parte 2 — ver §10.3). Faltaba en esta tabla; se agrega en la sincronización del 2/9/2026. |
| Organism | `module-rail` | `resources/views/components/organisms/module-rail.blade.php` | Implementado (28/8/2026, quinta vuelta — nivel 1 del layout, §7.1). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Organism | `module-sidebar` | `resources/views/components/organisms/module-sidebar.blade.php` | Implementado (28/8/2026, quinta vuelta — nivel 2 del layout, §7.1; sucesor de `sidebar-nav`). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Organism | `module-drawer` | `resources/views/components/organisms/module-drawer.blade.php` | Implementado (28/8/2026, quinta vuelta — offcanvas de módulos en móvil, §7.2). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Organism | `mobile-topbar` | `resources/views/components/organisms/mobile-topbar.blade.php` | Implementado (28/8/2026, quinta vuelta — header oscuro <768px, §7.2). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Template | `panel-shell` | `resources/views/components/templates/panel-shell.blade.php` | Implementado (28/8/2026, quinta vuelta — cáscara `<html>` compartida por todas las páginas del panel, con el tema persistido del usuario). Faltaba en esta tabla; se agrega el 2/9/2026. |
| Organism | `page-header` | `resources/views/components/organisms/page-header.blade.php` | Implementado (2/9/2026, tarea 31 — arquetipo formulario, ver §14) |
| Molecule | `tabs` | `resources/views/components/molecules/tabs.blade.php` | Implementado (2/9/2026, tarea 31 — el CSS ya existía desde el rediseño del dashboard, faltaba el componente) |
| Organism | `form-actions-bar` | `resources/views/components/organisms/form-actions-bar.blade.php` | Implementado (2/9/2026, tarea 31) |
| Molecule | `summary-card` | `resources/views/components/molecules/summary-card.blade.php` | Implementado (2/9/2026, tarea 31) |
| Molecule | `progress-meter` | `resources/views/components/molecules/progress-meter.blade.php` | Implementado (2/9/2026, tarea 31) |
| Molecule | `file-field` | `resources/views/components/molecules/file-field.blade.php` | Implementado (2/9/2026, tarea 31 — antes markup suelto en `organizacion.css`) |
| Template | `portal-layout` | `resources/views/components/templates/portal-layout.blade.php` | Implementado (3/9/2026, tarea 55 — cáscara del portal del cliente, header de una fila con 3 links fijos en vez del layout de tres niveles de `panel-layout`, ver §4.10). Faltaba en esta tabla; se agrega en la corrección de la tarea 55. |

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

- **`--ag-font-family-display`** (`tokens/primitives/base.css`): tipografía de titulares grandes, ADITIVA a `--ag-font-family-base` (IBM Plex Sans sigue siendo la única familia de texto/formularios/menú). Originalmente "Instrument Serif"; reemplazada por **"Fraunces"** en el rediseño de login (tercera vuelta) por verse muy angosta a 38-44px — ver fila de §1.4.
- **`--ag-color-scrim-strong`, `--ag-color-scrim`, `--ag-color-text-on-scrim`** (nuevos, `tokens/semantic/theme-{light,dark}.css`, **mismo valor en los dos archivos a propósito**): overlay oscuro (vignette, sin color de marca) y texto sobre `drone-hero.jpg`. Constantes entre temas porque la foto no reasigna por `[data-bs-theme]` — usar `--ag-color-text-inverse` ahí sería un bug real en tema oscuro (resuelve a gris-900, casi invisible sobre la foto ya oscurecida). Ver estimación de contraste en §1.3 (ídem en ambos temas, por diseño).
- **El wordmark es texto, no el átomo `logo`**: al momento de esta decisión, `logo-light.jpeg`/`logo-dark.jpeg` traían fondo sólido horneado (JPEG, sin transparencia); sobre una foto se hubiera visto un recuadro blanco/negro. Se resolvió como `<p>` en `--ag-font-family-display` reutilizando la clave `ui.logo.alt` (sin agregar un string nuevo) — el logo de `login-form` no se duplicó ni se quitó, sigue siendo el único wordmark visible en mobile (donde la foto se oculta). **Actualización (tercera vuelta):** el asset ya es `public/logo.png` transparente (ver nota más abajo, "Asset de logo transparente") — la limitación técnica desapareció, pero el wordmark sigue en texto porque el mockup pide la bicromía tipográfica "AGRO"/"COM", no el isotipo cuadrado.
- **Headline** (`seguridad.auth.headline`, nuevo en `lang/es/seguridad.php`): `clamp(2.25rem, 1.2rem + 2.2vw, 3rem)`, `line-height: 1.12`. Solo se renderiza en tablet/desktop (≥768px, cuando la foto es visible) — en mobile no hay riesgo de que se vea desproporcionado o recortado porque directamente no se pinta (`display:none` heredado del contenedor de la foto).
- Verificado explícitamente que el ajuste funciona en **ambos temas**, no solo en el default: como los tres tokens nuevos son constantes entre temas, el contraste del wordmark/headline sobre la foto es idéntico en claro y oscuro (no dependía de la corrección). La tarjeta de `login-form` (`--ag-color-bg-elevated` + `--ag-color-text`/`-muted`) ya pasaba AA en claro (§1.3 original); se sumó a la tabla la verificación equivalente en oscuro (~15:1 texto principal, ~8:1 texto secundario — ambas AAA). El panel de la foto no "se aplana" contra el resto de la UI oscura porque el vignette deja visible el centro de la foto (color/textura reales del cultivo) — la distinción no depende de un salto de luminosidad con el fondo circundante, sino de que ahí hay una fotografía y no una superficie plana.

**Rediseño de login (tercera vuelta, HU-02)** — el usuario aprobó un mockup de referencia (`docs/login.html`, sección "Turno 3", ids `#3a` claro / `#3b` oscuro) para un tercer refinamiento visual del login. Esta vuelta es ajuste de tokens + extensión de átomos compartidos + re-arquitectura de `login-form` en TABS; no toca arquitectura de negocio (contratos, `SesionController`, `IniciarSesionRequest`), solo presentación:

- **`--ag-color-amber-800`** (nuevo primitivo, `tokens/primitives/brand.css`): escalón intermedio 700→900, existe para dar un ámbar oscurecido usable como texto (ámbar-700 falla AA como texto, ámbar-900 es demasiado marrón). Ver §1.3.
- **`--ag-color-bg-auth`, `--ag-color-text-muted-auth`, `--ag-color-accent-link`** (nuevos tokens semánticos, `theme-{light,dark}.css`): fondo propio del panel de formulario de login (reemplaza `--ag-color-bg-elevated` ahí, quita la tarjeta blanca elevada de vueltas anteriores), su variante de texto muted (el muted global falla AA sobre el nuevo fondo tintado en claro), y un ámbar seguro para texto de enlace. Ver §1.3/§1.4 para la verificación de contraste completa y §1.4 para el detalle de cada token.
- **`--ag-color-wordmark-accent`, `--ag-color-chip-icon`** (nuevos tokens semánticos, `theme-{light,dark}.css`, mismo valor exacto en ambos archivos a propósito): constantes entre temas para el branding tipográfico editorial de `auth-layout` (wordmark bicromía "AGRO"/"COM" + chip de ícono). Criterio idéntico que `--ag-color-scrim*`: la foto no reasigna por tema, así que lo que va montado sobre ella tampoco. Ambos temas usan ámbar-300 (wordmark-accent) y verde-300 (chip-icon), identidad de marca fija.
- **`atoms/input` — prop `variant`** (`'boxed'` default | `'line'`): variante de línea editorial (sin caja, solo `border-bottom`, label uppercase) para el nuevo diseño de login. Mismo Blade, mismo marcado/accesibilidad — el modificador es una clase CSS (`ag-input--line`) resuelta en `resources/css/components/input.css`, no un componente nuevo. El resto del panel sigue usando `'boxed'` (default) sin cambios. El color muted propio de auth (`--ag-color-text-muted-auth`) es responsabilidad de quien consume el átomo (el organism/template de login), no del átomo compartido — este no conoce "auth" como concepto.
- **`atoms/button` — prop `iconPosition`** (`'start'` default | `'end'`): permite el ícono después del texto (p. ej. `arrow_forward` al final de "Iniciar sesión"). Sin cambios de CSS (el `gap` del flex ya aplica en cualquier orden de hijos); 100% compatible con los consumidores existentes, que no pasan la prop y siguen viendo el ícono al inicio.
- **`molecules/theme-toggle` — rediseño completo del marcado/CSS interno**: el switch track+thumb deslizante se reemplaza por una pastilla "segmented" de dos celdas fijas (`light_mode`/`dark_mode`), la del tema activo con fondo elevado + color de acento. Sigue siendo UN control accesible (`role="switch"`, `aria-checked`) — no dos botones independientes — y `resources/js/molecules/theme-toggle.js` no cambia una línea (solo lee/escribe `data-bs-theme` y sincroniza `aria-checked`, ninguna de esas dos cosas depende del marcado interno). Este cambio aplica a los TRES consumidores actuales (login vía `auth-layout`, topbar, pie de `sidebar-nav`) a propósito: es el mismo molecule, no hay bifurcación visual por pantalla.
- **`templates/auth-layout` — redesarrollo completo**: header (logo + theme-toggle), footer (copyright + versión) ahora viven en el template (no en `login-form`) para que `seleccionar-rol.blade.php` los herede sin duplicación. Panel visual: 55%/45% split-screen foto/formulario en desktop (quita el `max-width` fijo de antes, flex ratios en su lugar para mayor flexibilidad); ~240px altura en tablet; solo formulario en mobile. Editorial: wordmark tipográfico en dos colores (texto "AGRO" + ámbar "COM"), chip verde con ícono `flight_takeoff` arriba a la derecha, regla de gradiente 64×3px verde→ámbar, headline con subheadline de 14.5px al 80% opacidad. Panel: fondo `--ag-color-bg-auth` (tintado, no blanca elevada), sin `box-shadow`.
- **`organisms/login-form` — estructura de tabs rediseñada**: dos tabs (Ingreso / Recuperar acceso) que togglean entre dos paneles. **Panel "Ingreso"**: contiene el `<form>` real de autenticación (username/password inputs en `variant="line"`, checkbox "Recordarme", link "¿Olvidaste tu contraseña?" que cambia de tab, botón submit con ícono al final). **Panel "Recuperar acceso"**: presentacional (fuera de alcance, backend no existe todavía), SIN `<form>` envuelto (esto es deliberado: `resources/js/pages/login.js` busca `[data-ag-login-form] form`, que sigue resolviendo a UN ÚNICO form porque no hay `<form>` en el panel "recuperar"). Inputs de `variant="line"`. Línea "¿No tenés cuenta?" al pie (siempre visible, fuera de tabs). Botones de cambio de tab (`[data-ag-login-switch-tab]`) estilizados sin fondo/borde, con colores de enlace (`--ag-color-accent-link` para "¿Olvidaste?", `--ag-color-primary-emphasis` para "Volver"). **Claves nuevas** en `lang/es/seguridad.php`: `auth.tagline|subheadline|sin_alta_publica`, `login.recordarme|olvido_password` (actualizar `boton_ingresando` a "Verificando acceso…"), `recuperar.*` (titulo|subtitulo|boton_enviar|volver).
- **`resources/js/organisms/login-form.js` — nuevo archivo**: maneja los tabs con patrón de delegación de eventos vanilla (cero frameworks nuevos, consistente con `theme-toggle.js`/`sidebar-nav.js`). Funciona: activar tab por click, roving tabindex (ArrowRight/ArrowLeft navega entre tabs), botones de cambio de tab (`[data-ag-login-switch-tab]`) movilizan el foco al tab correspondiente. Guard contra `DOMContentLoaded` para no romper otras páginas. Importado en `resources/js/app.js` (line de `import './organisms/login-form.js';` agregada).

**Ajustes puntuales de login (cuarta vuelta, HU-02, PR #15)** — el usuario probó el PR y pidió una ronda de retoques acotados, sin reabrir arquitectura (ni ADR 0002, ni tabs, ni el motor de sync/backend). Todos los cambios son de tokens/CSS/JS de presentación dentro del catálogo ya existente:

- **`--ag-color-bg-auth` retocado en ambos temas** (`tokens/semantic/theme-{light,dark}.css`, mismo mecanismo `color-mix()` sobre primitivos existentes, ningún hex nuevo):
  - Claro: de `color-mix(in srgb, var(--ag-color-amber-100) 45%, var(--ag-color-gray-50) 55%)` a `color-mix(in srgb, var(--ag-color-amber-100) 30%, var(--ag-color-gray-0) 70%)` — menos peso de ámbar, mezclado contra blanco puro (antes gris-50) para que el resultado se lea "crema" en vez de "ocre pastel". Aprox. `#fefaf2`.
  - Oscuro: de `color-mix(in srgb, var(--ag-color-green-900) 40%, var(--ag-color-gray-950) 60%)` a `color-mix(in srgb, var(--ag-color-green-900) 25%, var(--ag-color-gray-850) 75%)` — menos peso de verde, mezclado contra gray-850 (antes gray-950, más oscuro) para que el matiz verde siga siendo perceptible pese a pesar menos en la mezcla. Aprox. `#1a261d` — grafito/petróleo con un dejo verde, ya no "verde-oliva".
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

- **`atoms/badge`** (nuevo): pill de estado, variantes `success|warning|info|danger|neutral|accent`. Decisión de contraste deliberada: el texto de las variantes de estado es SIEMPRE `--ag-color-text` (nunca el token crudo del estado, p. ej. `--ag-color-warning`, como color de texto) — se verificó que amarillo-sobre-amarillo-pálido da ~1.4:1 (falla catastrófica) y que azul/rojo rondan el límite de AA (~4.4-4.8:1) en vez de pasarlo con margen. `--ag-color-text` sobre cualquier `-subtle` (tinte pálido en claro, overlay de baja opacidad sobre superficie oscura en oscuro) pasa AAA en los dos temas sin excepción — el color de marca de cada estado se conserva en un punto decorativo (`.ag-badge__dot`, no-texto, exigencia de contraste más laxa) o en el ícono si se pasa uno, nunca en el texto. La variante "accent" reutiliza el par ya establecido y verificado en el sistema (`--ag-color-accent-subtle`/`--ag-color-accent-contrast`, el mismo que ya usan `.ag-role-badge` y `.ag-menu-item__badge`). Nuevo token semántico `--ag-color-neutral-subtle` (gray-200 claro / gray-800 oscuro) para la variante "neutral", que no tenía un `-subtle` propio en el sistema.
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

- ~~Instalar Bootstrap/AdminLTE + Material Symbols + IBM Plex Sans/Mono vía npm~~ — hecho por `frontend` (27/8/2026): `bootstrap@5.3.8`, `material-symbols`, `@fontsource/ibm-plex-{sans,mono}` en `package.json`, importados desde `resources/css/app.css`; `bootstrap` (JS) importado completo en `resources/js/app.js` (`window.bootstrap`). `livewire/livewire` (composer) sigue pendiente.
- ~~Reemplazar el scaffold Tailwind de `resources/css/app.css`~~ — hecho por `frontend`; los tokens/componentes de `design-ui` sobrevivieron el cambio sin tocarlos.
- **Wiring de los JS nuevos de este pase en `resources/js/app.js`** (archivo reservado a `frontend`, `design-ui` no lo toca): falta agregar `import './molecules/theme-toggle.js';` y `import './organisms/sidebar-nav.js';`, mismo patrón que la línea ya existente `import './atoms/input.js';`. Sin este import, el switch de tema y el colapso a icon-rail del sidebar quedan sin comportamiento (el resto de la interacción nueva — offcanvas, collapse, dropdown — ya funciona porque depende de `window.bootstrap`, que `app.js` ya expone). → `frontend`.
- **Columna de preferencia de tema por usuario**: resuelta como `sec_user_preferencia.tema` (enum `TemaPreferencia::Claro|Oscuro`), ya implementada por `backend` en `app/Dominios/Seguridad/`. Pendiente real: el mapeo `claro|oscuro` ↔ `light|dark` (el que persiste el backend vs. el que consume `data-bs-theme`/`theme-toggle`) lo hace quien conecte el evento `agrocom:theme-changed` a la persistencia (Livewire) — no es responsabilidad de la molécula. → `frontend`.
- ~~Middleware `ResolverRolActivo` y la ruta de cambio de rol activo~~ — implementado por `backend` (`app/Dominios/Seguridad/Infraestructura/Http/Middleware/ResolverRolActivo.php`, `RolActivoController.php`).
- **Implementación de las moléculas/organismos/templates de §4** — hecho en este pase (27/8/2026). Ver tabla de arriba y las notas de composición.
- **Ensamblar las páginas concretas** (`pages/login`, `pages/seleccionar-rol`, el dashboard de cada rol) usando `auth-layout`/`panel-layout`, conectar el submit de `login-form` y los triggers de `role-selector-item`/popovers de rol a `SesionController`/`RolActivoController` (ambos responden JSON, pensados para fetch/Livewire, no submit clásico con redirect). → `frontend`.
- ~~Asset de logo transparente~~ — resuelto (rediseño de login, tercera vuelta): los dos JPEG (`logo-light.jpeg`/`logo-dark.jpeg`, fondo sólido horneado blanco/negro) se reprocesaron a un único `public/logo.png` con canal alfa real (recorte del isotipo + wordmark, extracción de alfa por chroma-key con limpieza de ruido de compresión — filtro de mediana + erosión + blur suave sobre el canal alfa, no sobre el color). Ya no hace falta variante por tema: `atoms/logo` renderiza un solo `<img>`, `logo.css` perdió la regla `[data-bs-theme]` que alternaba `display`. El wordmark editorial de `auth-layout` sigue en texto (no en esta imagen) por decisión de diseño del mockup (bicromía tipográfica "AGRO"/"COM"), no ya por la limitación técnica — ver notas de §3.
- ~~Instalar la fuente de `--ag-font-family-display` vía npm~~ — resuelto, dos vueltas: primero se instaló `@fontsource/instrument-serif`; en el rediseño de login (tercera vuelta) se reemplazó por `@fontsource-variable/fraunces` (más ancha/cálida a 38-44px, con eje óptico `opsz`) y se desinstaló el paquete de Instrument Serif (sin otros consumidores).

## 7. Quinta vuelta (28/8/2026) — layout de tres niveles del panel

Rediseño del panel completo sobre las maquetas aprobadas del mockup
`docs/Login Agro Drones.dc.html` (`4a` escritorio, `5a` tablet, `5b` móvil,
`5c` selección de rol). Tres decisiones del pedido gobiernan todo el pase:
el **layout** es el de las maquetas; los **módulos del menú** salen de la
especificación (`especificacion_funcional_tecnica.md` §4), no del mockup; y
los **hex del mockup nunca se copian** — cada color se tradujo a un token
semántico (invariante 11).

### 7.1. Los tres niveles

| Nivel | Componente | Qué es |
|---|---|---|
| 1 | `organisms/module-rail` | Riel de módulos, 74px (64px tablet), superficie oliva oscura **en ambos temas** (`--ag-color-bg-rail*`, constantes — chrome de marca, mismo criterio que `--ag-color-scrim*`). Solo los módulos que el rol activo puede ver; engranaje al pie → `panel.organizacion.index`. |
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
| <768 | 5b | Web, no app: sin barra inferior ni FAB. Header oscuro (`mobile-topbar`: hamburguesa → `module-drawer` offcanvas con los 7 módulos colapsables, logo, `ROL · CAMPAÑA` en mono lima, campana, avatar) + banda de breadcrumb con lupa; KPI protagonista (cifra 2.5rem) + 2 secundarios; sesiones como fichas; pie mono `AGROCOM SRL · año` + versión. Objetivos táctiles ≥44px. |

El chrome no scrollea, el contenido sí: `.ag-panel` fija `100dvh` y cada
columna flex interna lleva `min-height: 0` (sin eso el hijo flex nunca
encoge y el scroll interno no aparece); las tarjetas que no deben encogerse
van `flex: 0 0 auto`.

### 7.3. Tipografía — tres familias, una por contexto

Confirmado en este pase (pedido del 28/8/2026): **Fraunces** es LA fuente
display del sistema — el mockup traía Instrument Serif, pero esa no es la
del sistema (ya reemplazada en la tercera vuelta); se mantiene Fraunces y
los tamaños del panel se calibran a su métrica más ancha (título de página
2.125rem vs. 36px del mockup, KPI 2.125rem vs. 38px, sidebar 1.375rem vs.
23px). **IBM Plex Sans** para toda la interfaz/subtítulos — ahora cableada
explícitamente a `--bs-body-font-family` para que nada caiga al stack del
sistema — e **IBM Plex Mono** para datos/metadatos (horas, drones, badges
de pendientes, `ROL · CAMPAÑA`, pie). Los íconos pasan de Material Symbols
Outlined a **Rounded** (los de las maquetas): `material-symbols/rounded.css`
+ clase `material-symbols-rounded` en `atoms/icon`.

### 7.4. Tokens nuevos (ambos temas)

Primitivas (`tokens/primitives/`): rampa **arena** `--ag-color-sand-50…700`
y rampa **oliva** `--ag-color-olive-150…1000` en `brand.css` (neutros
cálidos del lenguaje aprobado — un tenant las reemplazaría junto con las
rampas de marca); `--ag-color-green-200` (lima del riel); en `base.css` el
rojo óxido `--ag-color-red-50|200|600|900` y el azul `--ag-color-blue-50|700`.

Semánticos (mismo nombre en ambos temas, valores reasignados):

| Token | Uso |
|---|---|
| `--ag-color-bg` (reasignado) | El fondo del contenido del panel es **el mismo `--ag-color-bg-auth` del login** en ambos temas (pedido explícito del 28/8/2026) — crema en claro, grafito-petróleo en oscuro |
| `--ag-color-bg-rail`, `-bg-rail-active`, `-text-rail`, `-text-rail-active` | Riel de módulos — **constantes entre temas** (oliva-1000/900, oliva-300, verde-200) |
| `--ag-color-surface-card`, `--ag-color-border-card` | Tarjeta del panel (KPI, tablas, fichas) y su borde suave, separados del chrome |
| `--ag-color-bg-table-head`, `--ag-color-border-row`, `--ag-color-bg-row-hover` | Cabecera mono de tabla, borde de fila, hover de fila |
| `--ag-color-bg-input-chrome` | Relleno del buscador del header y píldoras inactivas |
| `--ag-color-success-strong`, `-warning-strong`, `-info-strong`, `-danger-strong`, `-neutral-strong` | TEXTO de los chips de estado sobre su propio `-subtle` — cada par verificado AA (ver §7.5). `warning` pasa del amarillo Bootstrap al ámbar de marca; `danger` al rojo óxido |
| `--ag-color-danger-border` | Borde de la alerta de RC |
| `--ag-color-primary-border-subtle` | Borde tenue verde (chip de campaña, píldora/filtro activo) |
| `--ag-color-text-faint` | Oliva decorativa (íconos apagados, barras) — **no pasa AA como texto**, documentado |
| `--ag-color-track`, `--ag-color-bar-unassigned` | Pista de las barras de pausas y la barra "sin causa asignada" |

`--ag-color-text`/`-text-muted`/`-border`/`-neutral-subtle` se reasignaron
a la escala oliva/arena (claro) y a mezclas oliva-sobre-gris + texto crema
(oscuro).

### 7.5. Contraste verificado (se suma a §1.3)

| Combinación (tema claro) | Ratio aprox. | Resultado |
|---|---|---|
| `--ag-color-text` (oliva-950) sobre `--ag-color-surface-card` (arena-50) | ~12.9:1 | AAA |
| `--ag-color-text-muted` (oliva-500) sobre arena-50 / `--ag-color-bg` crema | ~5.0:1 / ~5.1:1 | AA — por eso muted es oliva-500 y NO la oliva-400 del mockup (~3.5:1, falla; quedó como `-text-faint` decorativo) |
| `--ag-color-success-strong` sobre `--ag-color-success-subtle` | ~7.2:1 | AAA (chip "Validada") |
| `--ag-color-warning-strong` (ámbar-800) sobre `--ag-color-warning-subtle` (ámbar-100) | ~4.6:1 | AA (chip "Sin evidencia", badges de pendientes, "ÚLTIMO USADO") |
| `--ag-color-info-strong` (azul-700) sobre `--ag-color-info-subtle` (azul-50) | ~5.5:1 | AA (chip "En vuelo") |
| `--ag-color-danger-strong` (rojo-900) sobre `--ag-color-danger-subtle` (rojo-50) | ~9.2:1 | AAA (alerta de RC) |
| `--ag-color-neutral-strong` (oliva-600) sobre `--ag-color-neutral-subtle` (arena-200) | ~5.2:1 | AA (chip "Programada") |
| Blanco sobre `--ag-color-danger` (rojo-600) | ~5.1:1 | AA (botón "Resolver") |
| `--ag-color-text-rail` (oliva-300) sobre `--ag-color-bg-rail` (oliva-1000) | ~4.9:1 | AA (íconos del riel; el mínimo exigido para no-texto es 3:1) |
| `--ag-color-text-rail-active` (verde-200) sobre `--ag-color-bg-rail-active` (oliva-900) | ~8.7:1 | AAA |

| Combinación (tema oscuro) | Ratio aprox. | Resultado |
|---|---|---|
| Texto crema sobre `--ag-color-surface-card` | ~14:1 | AAA |
| `--ag-color-text-muted` (salvia) sobre la tarjeta oscura | ~6:1 | AA+ |
| `-strong` de cada estado sobre su `-subtle` (overlay sobre tarjeta): success ~5.6, warning ~5.6, info ~5.3, danger ~5.3, neutral ~9.6 | ≥5.3:1 | AA todos |

Regla operativa que queda fijada: **un chip de estado usa siempre el par
`-subtle` (fondo) + `-strong` (texto)**, nunca el token crudo como texto;
el ámbar como texto sobre superficie clara es siempre el oscurecido
(ámbar-800 vía `-warning-strong`/`-accent-link`) porque el tono base falla AA.

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
  `carlos.ferrufino`/`password` en `Demo/PersonalDemoSeeder`.

## 8. Reglas fijas de pulido UI (sexta vuelta, 28/8/2026)

Seis reglas derivadas de la sesión de login + `seleccionar-rol` del
28/8/2026, para aplicar por defecto en cualquier componente nuevo del
catálogo en vez de redescubrirlas por prueba y error en cada pantalla.

1. **Tipografía display: `font-weight` siempre explícito.**
   `.ag-login-form__title` no lo declaraba (heredaba el 500 de Bootstrap
   Reboot); `.ag-role-select__title` sí, pero en
   `--ag-font-weight-regular` (400) a 2rem — un peldaño más chico/liviano
   que el login pese a compartir `auth-layout`. Igualados a 38px +
   `--ag-font-weight-bold` explícito en ambos. Regla: todo consumidor de
   `--ag-font-family-display` declara `font-weight` con el token, nunca
   depende del peso por defecto de Bootstrap para headings.
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
6. **Transición nativa entre navegaciones del mismo flujo**:
   `@view-transition { navigation: auto; }` declarado una sola vez en
   `app.css` (transversal, no por template), con el bloque
   `prefers-reduced-motion` correspondiente sobre `::view-transition-*`.
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

## 9. Sexta vuelta — parte 2 (28/8/2026): rediseño del dashboard

Ejecuta `docs/gestion/plan_dashboard_rediseno.md` — Anexo A y fases 1 a 7 de
ese plan (queda solo la Fase 8, auditoría final, que es este mismo cierre).
Verificado en navegador (Playwright, claro/oscuro/móvil, usuario
`carlos.ferrufino`) antes de cerrar cada fase.

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
  (`color-mix(olive-900 22%, gray-850 78%)`) — contra `--ag-color-bg`
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

Cierra la Fase 8 (auditoría final) que `docs/gestion/plan_dashboard_rediseno.md`
había dejado pendiente, y la amplía con una revisión de diseño externa sobre
el panel completo (tokens de color, contraste AA, alertas, botones, gráfica
de distribución, grilla, tipografía, elevación en oscuro, detalles menores).
Rama `feature/auditoria-visual`, plan completo en el historial de la sesión.
Tres observaciones de la revisión no aplicaban (ya resueltas por trabajo
previo, no se tocó código): la rampa neutra ya estaba "teñida" (arena/oliva,
no grises fríos), el padding de KPI vs. tarjeta de distribución ya era el
mismo (`var(--ag-space-4)`), y el azul de "En vuelo" ya era
`--ag-color-info` del catálogo semántico.

### 10.1. Sistema de color — consistencia de marca

Verificado con cálculo exacto de contraste WCAG (script Python, no a ojo),
antes de fijar cualquier hex.

| Combinación | Ratio | Resultado | Dónde se usa |
|---|---|---|---|
| `--ag-color-green-400` (`#79b93d`, nuevo primitivo — blend 65% green-300/35% green-500) sobre `--ag-color-surface-card` (oscuro) | 5.21:1 | Pasa AA | `--ag-color-primary-emphasis`/`-success` (crudo), tema oscuro |
| `--ag-color-green-400` sobre `--ag-color-bg-chrome` (oscuro) | 5.86:1 | Pasa AA | Ídem |
| `--ag-color-green-200` (success-strong, oscuro) sobre su propio `--ag-color-success-subtle` (`rgba(198,232,106,.16)`) | 5.95:1 | Pasa AA | Chip "Validada" (`atoms/badge`), tema oscuro |
| `--ag-color-green-500` (success crudo, claro) sobre `--ag-color-sand-50` | 3.10:1 | Pasa 3:1 no-texto | Uso decorativo (borde/relleno de gráfica), NUNCA como texto |
| `--ag-color-success-strong` (claro, `color-mix(green-500 30%, green-900 70%)` ≈ `#245b24`) sobre su propio `--ag-color-success-subtle` (`color-mix(green-500 16%, sand-50 84%)` ≈ `#e0ecd8`) | 6.61:1 | Pasa AAA | Chip "Validada", tema claro |
| `--ag-color-danger-contrast-fill` (`--ag-color-red-600`, `#c0442e`) con texto blanco | 5.11:1 | Pasa AA | Relleno sólido de `.ag-button--danger`, ambos temas |

Cambios de token (`tokens/primitives/brand.css`, `tokens/semantic/theme-{dark,light}.css`):

1. **Verde de marca entre temas (obs. #1).** Nuevo primitivo
   `--ag-color-green-400` — paso intermedio 300→500, menos saturado que el
   lima puro del logo. `--ag-color-primary-emphasis`/`-success` (crudo) en
   oscuro pasan de `green-300` a `green-400`. `--ag-color-text-rail-active`/
   `--ag-color-chip-icon` (chrome de marca fijo) y el relleno sólido de
   `--ag-color-primary` (ya constante) NO se tocaron.
2. **`success` ≠ `primary` (obs. #2).** Antes eran literalmente el mismo
   valor en ambos temas. Ahora: oscuro usa `green-200` para
   `-success-strong` (distinto del `green-400` de `-primary-emphasis`);
   claro usa `green-500` como base de `success`/`-subtle`/`-strong`, sin
   reutilizar `green-700` (el primitivo de `primary`) en ningún punto de la
   cadena — antes `-success-strong` sí lo reutilizaba al 50%.
3. **Patrón `-subtle`/`-border`/`-strong` completo (obs. #1.3).** Nuevos
   `--ag-color-success-border`, `-warning-border`, `-info-border` en ambos
   temas (mismo criterio que `--ag-color-danger-border`: literal/mix en
   claro, `rgba` del tono crudo en oscuro) — `atoms/badge` puede dar borde
   a los cuatro estados por igual (regla §8 punto 3). El propio `badge.css`
   no se tocó (ninguna variante aplicaba borde todavía, ni siquiera danger
   — queda fuera de esta vuelta, es un cambio de componente, no de tokens).

### 10.2. Botón "Resolver" en oscuro (obs. #2)

`.ag-button--danger` usaba `background: var(--ag-color-danger)` — en
oscuro ese token es `#ea868f`, calibrado para TEXTO sobre superficie
oscura, no para relleno sólido (blanco sobre ese rosa claro se leía
"deshabilitado"). Nuevo `--ag-color-danger-contrast-fill` (constante entre
temas, mismo criterio que `--ag-color-accent-contrast-fill`, reutiliza
`--ag-color-red-600`) como `background` del botón. Verificado en navegador,
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

### 10.5. `distribution-bar` reemplaza a `donut-chart` (obs. #5, #6)

`donut-chart` (blade + css) se retiró — no tenía otro consumidor.
`molecules/distribution-bar` (mismo prop shape: `segments`, `total`,
`centerLabel` — `DatosDemoPanel::distribucionSesiones()` no cambió): KPI
grande a la izquierda + barra horizontal 100% apilada a la derecha (18px
de alto), leyenda debajo como grid de 4 columnas (`auto 1fr auto auto`:
punto/etiqueta/valor mono/%, cada `<li>` en `display:contents` para que
sus 4 hijos caigan directo en las columnas del padre y se alineen entre
filas). El "48" del `count` de `section-head` se quitó de esa llamada —
ahora vive dentro de la tarjeta como KPI (`.ag-distribution-bar__total`,
sans + `tabular-nums`, mismo criterio que §10.6).

### 10.6. Tipografía — superó la regla §8.7 anterior (obs. #7)

`.ag-stat-card__value` y `.ag-distribution-bar__total` pasan de
`--ag-font-family-mono` a `--ag-font-family-base` + `font-variant-numeric:
tabular-nums`. El mono abría demasiado el tracking de una cifra de varios
dígitos ("Bs 18.490"); tabular-nums da el mismo efecto de "dígitos
alineados en columna" que motivaba el mono original, sin ese tracking. La
display (Fraunces en su momento, IBM Plex Sans Condensed desde la séptima
vuelta — ver §11.2) sigue reservada para el titular de página — eso no
cambió. Los usos mono CHICOS (horas, drones, `ROL · CAMPAÑA`, valores de
leyenda) no cambian. §8.7 marcada como superada.
Título de card (`.ag-card__title`, sans/bold/`font-size-sm` en las tres
cards del dashboard): ya cumplía, se declaró como regla fija (§8, entrada
nueva más abajo).

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
`theme-dark.css` para esto. `.ag-stat-card` y la tarjeta de
`distribution-bar` (vía su wrapper `.ag-card`) ya aplican
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
  control clicable.

### 10.10. Regla nueva en §8

Título de card (`.ag-card__title`, sans/bold/`font-size-sm`) se fijó como
regla 9 de §8 — ver ahí.

## 11. Séptima vuelta (28/8/2026) — ajuste puntual: tema oscuro y tipografía display

Feedback directo sobre captura real del dashboard en producción local (no
una auditoría externa formal como §10): "el tema oscuro está demasiado
verdoso" + "se nota que el sistema está generado con IA, como si fuera
Times New Roman". Dos cambios independientes, misma vuelta.

### 11.1. Tema oscuro — el tinte de marca se retira de la superficie estructural

Diagnóstico: `--ag-color-bg-auth` (tokens/semantic/theme-dark.css) alimentaba
TODO el cascade de superficie (`--ag-color-bg`, `-bg-elevated`, `-bg-chrome`,
`-surface-card`, `-border-card`, `-bg-table-head`, `-border-row`,
`-bg-row-hover`, `-bg-input-chrome`, `-track`) con un mismo tinte
verde/oliva — cuatro vueltas previas (§9, §10.1) ya habían diluido ese
tinte (40%→25% de peso, base gray-950→gray-850) sin resolver la percepción
de "todo verde", precisamente porque diluir un valor que se repite en cada
superficie a la vez no cambia que se repita en cada superficie a la vez.

Corrección (pedido explícito del usuario, no una preferencia de
`design-ui`): **el lienzo de contenido (`--ag-color-bg`) mantiene su tinte**
— es el mismo criterio "un fondo con identidad propia" que ya rige en claro
(`--ag-color-bg-auth` ahí es crema, no gris puro) y el usuario lo confirmó
explícitamente como correcto. Lo que cambia es que **el chrome estructural
deja de heredar ese tinte**:

- `--ag-color-bg-elevated` / `--ag-color-bg-chrome` (sidebar de nivel 2,
  header): pasan de derivar de `--ag-color-bg-auth` a `--ag-color-gray-900`
  plano — mismo criterio que en claro, donde ese chrome es `sand-50` (más
  neutro que el `bg-auth` crema del lienzo).
- `--ag-color-surface-card` y toda su escala derivada (`-border-card`,
  `-bg-table-head`, `-border-row`, `-bg-row-hover`, `-bg-input-chrome`,
  `-track`, `-border`, `-border-strong`, `-neutral-subtle`): mismo overlay
  blanco creciente de siempre, pero ahora sobre `--ag-color-gray-900` en vez
  de `--ag-color-bg-auth`.
- `--ag-color-text-muted`/`-text-faint`: de mezcla oliva a gris neutro
  (`gray-500`/`gray-600`) — pasan más contraste que antes en ambas
  superficies.
- `--ag-color-primary-subtle` (el wash translúcido más usado del sistema —
  15+ consumidores): 0.16→0.10 de opacidad. Sobre un fondo casi negro un
  verde lima translúcido se percibe más vívido que el mismo % sobre un fondo
  claro (el alpha-blend deja pasar más croma cuanto más oscura es la base).
- **Selección del ítem de menú activo** (`.ag-menu-item.is-active`,
  `.ag-module-band__pill.is-active` — sidebar de nivel 2): dejó de reutilizar
  `--ag-color-primary-subtle` (un wash translúcido que se ve distinto según
  qué color tenga debajo, señalado explícitamente como otra causa de
  "demasiado verde") y pasa a un token propio, **`--ag-color-bg-nav-active`**
  — sólido, sin alpha, en los dos temas: `green-100` en claro (idéntico al
  valor previo, sin cambio visual), `green-900` sólido en oscuro. Contraste
  con `--ag-color-success-strong` (texto de ese estado): ~9:1 en oscuro, AAA.
- `--ag-color-bg-rail`/`-bg-rail-active` (riel, nivel 1) y el resto de la
  paleta de acento (`primary`, `success`, `accent`) **no cambiaron** — la
  identidad de marca en oscuro queda 100% en los acentos (botones, badges,
  texto cálido, riel, ítem de menú activo), nunca en la superficie de fondo
  del chrome. Referencia externa verificada (hoja de estilos real de
  deere.com, un actor del mismo rubro): cromo estructural neutro
  (blanco/gris/negro), verde de marca reservado a CTAs/badges — nunca al
  fondo, pese a que JD es una marca todavía más asociada al verde que
  Agrocom.

`theme-light.css` no se tocó (ningún cambio visual en claro) salvo el nuevo
token `--ag-color-bg-nav-active`, con el mismo valor que ya tenía
`--ag-color-primary-subtle` ahí.

### 11.2. Tipografía display — Fraunces (serif) → IBM Plex Sans Condensed

`--ag-font-family-display` (tokens/primitives/base.css) deja de ser
**"Fraunces"** (confirmada como LA fuente display en la quinta vuelta, §7.3)
— feedback directo: un serif editorial en un panel operativo de campo lee
como plantilla genérica ("se nota que está generado con IA"), no como
identidad propia; irónico además porque el fallback de ese mismo token
terminaba en `'Times New Roman', serif`.

Se verificó `deere.com` (hoja de estilos real, no captura) como referencia
del rubro: usa un sans grotesco propio (`jd_sans_pro*`, fallback
`"Helvetica Neue", Helvetica, Arial`) SIEMPRE bold/condensado en titulares,
nunca serif. Nueva fuente display: **`IBM Plex Sans Condensed`**
(`@fontsource/ibm-plex-sans-condensed`, pesos 400/500/600/700 importados en
`app.css`) — misma familia tipográfica que `--ag-font-family-base` (IBM Plex
Sans), diferenciada de la interfaz por condensación + peso en vez de por una
tipografía ajena. Resuelve más angosta que Fraunces (favorable para
KPIs/títulos de contenedor angosto — lo opuesto al problema que tenía
Instrument Serif en la tercera vuelta, §3).

`@fontsource-variable/fraunces` se desinstaló (sin otros consumidores,
mismo criterio que la baja de `@fontsource/instrument-serif` en la tercera
vuelta). Ningún consumidor de `--ag-font-family-display` (§4.8, §4.9,
`login-form`, `module-sidebar`, `panel-layout`, `dashboard.css`,
`seleccionar-rol.css`) cambió de código — todos ya declaraban
`font-weight`/`font-optical-sizing` explícitos por consumidor (regla 1 de
§8), así que el swap de familia en el token alcanzó sin tocar componentes.
Las tres familias del sistema (§7.3) siguen siendo tres, solo que la
"protagonista" cambió: **IBM Plex Sans Condensed** títulos/cifras
protagonistas, **IBM Plex Sans** interfaz/subtítulos, **IBM Plex Mono**
datos/metadatos.

Nota para quien lea §1.4, §3 y §7.3 más arriba: describen decisiones
históricas correctas para su momento (Instrument Serif → Fraunces, tercera
vuelta) — el estado ACTUAL del token es el de esta sección, no el de esas
tablas/párrafos, que se dejan como registro histórico sin reescribir.

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
**Resumen por lote** (cuadros por lote + `distribution-bar` reubicada acá
desde Resumen) y **Multimedia** (capturas RC reales). Detalle completo del
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
    if (document.querySelector('[data-ag-map]')) import('./organisms/dashboard-map.js');
});
```

Vite genera un chunk separado por cada import() dinámico (`dashboard-charts-*.js`,
`dashboard-map-*.js`, con su CSS propio en el caso de Leaflet) que el
navegador solo pide si el DOM de la página tiene el contenedor
correspondiente — verificado con Playwright que login/selección de rol no
descargan ninguno de los dos chunks.

**Gotcha real, no obvio**: el módulo importado dinámicamente se carga
DESPUÉS de que `DOMContentLoaded` ya disparó (por definición: el import()
ocurre dentro de un handler de ese mismo evento). Si el módulo hijo
también hace `document.addEventListener('DOMContentLoaded', renderizar)`,
ese listener nunca se ejecuta. `dashboard-charts.js`/`dashboard-map.js`
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

### 13.3. `apex-chart` y `mapa-operativo` — altura declarada vs. altura real

`apex-chart.blade.php` NO fija `height` como CSS del contenedor — lo pasa
como `chart.height` (número) a ApexCharts vía `data-ag-chart-height`, y el
contenedor solo lleva ese valor como `min-height` mientras el chunk
diferido carga. Motivo: con `chart.height: '100%'` sobre un contenedor de
altura CSS fija, ApexCharts llena esa altura solo con el gráfico y agrega
la leyenda POR ENCIMA — el `overflow:hidden` de `.ag-card` recortaba la
segunda fila de leyenda del donut de 4 estados. Con un alto numérico,
ApexCharts reserva espacio para la leyenda dentro de ese total.

`mapa-operativo` tiene su propio gotcha de layout: el pane "Mapa" no es el
tab activo por defecto (Bootstrap lo deja en `display:none` hasta el
primer click) y Leaflet inicializado en un contenedor de tamaño cero
renderiza mal — `dashboard-map.js` escucha `shown.bs.tab` del botón del
tab para llamar `mapa.invalidateSize()` la primera vez que se muestra.

### 13.4. Auditoría (`validador`, 29/8/2026) — 2 hallazgos bloqueantes, ambos corregidos

- Invariante 11: `dashboard-map.js` tenía `color: '#fff'` literal en el
  borde de los `circleMarker` de sesión (único hex de toda la rama) —
  corregido a `leerColorToken('--ag-color-gray-0')`.
- Larastan (nivel 6): los métodos privados `lote()`/`sesion()` de
  `DatosDemoMapaOperativo` devolvían `array` sin value type — se
  completó el docblock con el shape exacto (mismo criterio que
  `lotes()`/`sesionesGeo()`).

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
  diseño hardcodeada, es un dato en runtime, mismo criterio que
  `distribution-bar`.
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
