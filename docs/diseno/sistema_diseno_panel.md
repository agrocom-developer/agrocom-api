# Sistema de diseño del panel — tokens y catálogo Atomic Design (HU-02)

**Mantiene:** agente `design-ui`. **Depende de:** ADR 0002 (AdminLTE + Atomic Design), ADR 0004 (rol activo, ADR extendido 27/8/2026), ADR 0013 (multi-idioma UI), CLAUDE.md invariante 11 (ningún color hardcodeado).

Este documento es el catálogo vigente de tokens y componentes de presentación del panel. No es un ADR (no fija arquitectura, ADR 0002 ya lo hizo) — es el inventario vivo de qué existe, en qué nivel de Atomic Design, y qué falta. Se actualiza cada vez que `design-ui` agrega o cambia un token o un componente.

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
| `--ag-font-family-display` | Tipografía de titulares grandes tipo hero (HU-02). Aditivo, no reemplaza `-base` en ningún otro lado — la usan `templates/auth-layout` (wordmark + headline) y `organisms/login-form` (título de "Ingreso"/"Recuperar acceso"). **"Fraunces"** (variable, `@fontsource-variable/fraunces`, import `standard.css`), instalada — reemplazó a "Instrument Serif" en el rediseño de login (tercera vuelta): a 38-44px esa fuente se leía muy angosta/apretada (pedido explícito del usuario), Fraunces es notablemente más ancha/cálida al mismo tamaño. Todo consumidor de este token agrega `font-optical-sizing: auto` (eje `opsz` variable de la fuente, se ajusta solo al tamaño real) |
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
| Molecule | `role-selector-item` | `resources/views/components/molecules/role-selector-item.blade.php` | Implementado |
| Molecule | `theme-toggle` | `resources/views/components/molecules/theme-toggle.blade.php` | Implementado |
| Molecule | `menu-item` | `resources/views/components/molecules/menu-item.blade.php` | Implementado |
| Organism | `collapsible-menu-group` | `resources/views/components/organisms/collapsible-menu-group.blade.php` | Implementado |
| Organism | `login-form` | `resources/views/components/organisms/login-form.blade.php` | Implementado |
| Organism | `sidebar-nav` | `resources/views/components/organisms/sidebar-nav.blade.php` | Implementado |
| Organism | `topbar` | `resources/views/components/organisms/topbar.blade.php` | Implementado |
| Template | `panel-layout` | `resources/views/components/templates/panel-layout.blade.php` | Implementado |
| Template | `auth-layout` | `resources/views/components/templates/auth-layout.blade.php` | Implementado |
| Atom | `badge` | `resources/views/components/atoms/badge.blade.php` | Implementado (2026-08-28) |
| Atom | `switch` | `resources/views/components/atoms/switch.blade.php` | Implementado (2026-08-28) |
| Molecule | `stat-card` | `resources/views/components/molecules/stat-card.blade.php` | Implementado (2026-08-28) |
| Molecule | `plan-card` | `resources/views/components/molecules/plan-card.blade.php` | Implementado (2026-08-28) |
| Molecule | `form-section` | `resources/views/components/molecules/form-section.blade.php` | Implementado (2026-08-28) |

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
  `camila.rojas`/`password` en `Demo/PanelDemoSeeder`.
