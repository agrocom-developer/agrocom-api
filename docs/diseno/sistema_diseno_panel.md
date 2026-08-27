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
| `--ag-color-text` sobre `--ag-color-bg-auth` (tema claro, "ocre pastel" ~`#faf4e8`) | ~15.6:1 | Pasa AAA | Texto principal del panel de formulario de login, rediseño HU-02 tercera vuelta |
| `--ag-color-text-muted` (gris-600) sobre `--ag-color-bg-auth` (tema claro) | ~4.3:1 | **Falla AA** | Por qué existe `--ag-color-text-muted-auth`: el muted global cae bajo 4.5:1 sobre el tono ocre tintado |
| `--ag-color-text-muted-auth` (gris-700) sobre `--ag-color-bg-auth` (tema claro) | ~7.5:1 | Pasa AAA | Labels uppercase, subtítulos, pie mono de `login-form`, tema claro |
| `--ag-color-accent-link` (ámbar-800) sobre `--ag-color-bg-auth` (tema claro) | ~4.9:1 | Pasa AA | Link "¿Olvidaste tu contraseña?", tema claro |
| `--ag-color-primary-emphasis` (verde-700) sobre `--ag-color-bg-auth` (tema claro) | ~4.9:1 | Pasa AA | Link "Volver al ingreso" (estado de recuperación), tema claro — reutiliza el token existente, no necesita uno propio |
| `--ag-color-text` sobre `--ag-color-bg-auth` (tema oscuro, "verde-oliva muy oscuro" ~`#112315`) | ~14.2:1 | Pasa AAA | Texto principal del panel de formulario de login, tema oscuro |
| `--ag-color-text-muted` (gris-500) sobre `--ag-color-bg-auth` (tema oscuro) | ~8.2:1 | Pasa AAA | El muted global YA pasa cómodo en oscuro — `--ag-color-text-muted-auth` reutiliza el mismo valor, no hace falta aclarar/oscurecer más |
| `--ag-color-accent-link` (ámbar-300) sobre `--ag-color-bg-auth` (tema oscuro) | ~8.4:1 | Pasa AAA | Link "¿Olvidaste tu contraseña?", tema oscuro |
| `--ag-color-primary-emphasis` (verde-300) sobre `--ag-color-bg-auth` (tema oscuro) | ~8.3:1 | Pasa AAA | Link "Volver al ingreso", tema oscuro |

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
| `--ag-color-focus-ring` | Contorno de foco de teclado (accesibilidad) |
| `--ag-space-1`…`--ag-space-8` | Espaciado, escala de 8px |
| `--ag-font-family-base|mono`, `--ag-font-size-*`, `--ag-line-height-*`, `--ag-font-weight-*` | Tipografía de texto/cifras |
| `--ag-font-family-display` | Tipografía de titulares grandes tipo hero (HU-02). Aditivo, no reemplaza `-base` en ningún otro lado — la usan `templates/auth-layout` (wordmark + headline) y `organisms/login-form` (título de "Ingreso"/"Recuperar acceso"). **"Fraunces"** (variable, `@fontsource-variable/fraunces`, import `standard.css`), instalada — reemplazó a "Instrument Serif" en el rediseño de login (tercera vuelta): a 38-44px esa fuente se leía muy angosta/apretada (pedido explícito del usuario), Fraunces es notablemente más ancha/cálida al mismo tamaño. Todo consumidor de este token agrega `font-optical-sizing: auto` (eje `opsz` variable de la fuente, se ajusta solo al tamaño real) |
| `--ag-radius-sm|md|lg|pill` | Radio de borde |
| `--ag-shadow-sm|md|lg` | Elevación (vocabulario Material) |
| `--ag-transition-fast|base` | Duración de transiciones |
| `--ag-ease-emphasized|drawer` | Curvas cubic-bezier propias (solo la curva, HU-02) — entradas/expansiones (stagger de menú, popover de rol, tarjeta de auth) y deslizamiento de paneles tipo cajón (sidebar offcanvas), respectivamente |
| `--ag-color-scrim-strong`, `--ag-color-scrim`, `--ag-color-text-on-scrim` | Overlay oscuro y texto sobre la foto de `auth-layout` (HU-02, refinamiento editorial) — **constantes entre temas** a propósito (la foto no reasigna por tema); nunca usar `--ag-color-text-inverse` sobre la foto, ver §1.3 |
| `--ag-color-bg-auth` (nuevo, HU-02 rediseño de login, tercera vuelta) | Fondo del panel de formulario de `auth-layout` — reemplaza el uso de `--ag-color-bg-elevated` ahí. Tono propio ("ocre pastel" en claro, "verde-oliva muy oscuro" en oscuro), deliberadamente distinto del gris neutro del resto del panel, para que ninguna otra tarjeta genérica que sí use `-bg-elevated` herede el tinte sin querer |
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

### 3.1. Convención de ubicación

`resources/views/components/{atoms|molecules|organisms|templates}/<nombre>.blade.php` → invocable como `<x-atoms.button>`, `<x-molecules.theme-toggle>`, etc. (resolución automática de Blade por subcarpeta). `pages/` no es una carpeta de componentes: son las vistas reales bajo `Infraestructura/Http/` de cada módulo (ADR 0008) — no le corresponde a `design-ui`.

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
