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

Confirmado contra `public/logo-dark.jpeg` / `public/logo-light.jpeg` y la memoria de proyecto (`paleta-colores-marca-agrocom`) — no se inventaron valores nuevos:

| Rampa | 100 | 300 (logo) | 500 | 700 (logo) | 900 |
|---|---|---|---|---|---|
| Verde | `#E8F4D9` | `#8CC63F` | `#55A03A` | `#1E7A34` | `#0F3D1A` |
| Ámbar | `#FDEDD3` | `#F5A623` | `#EF8C18` | `#E8720C` | `#743906` |

Los tonos "300" y "700" son los dos extremos exactos del degradado leído en cada logo (verde lima→bosque, ámbar del ícono de señal); 100/500/900 son interpolaciones/mezclas para dar una rampa completa (tinte claro, punto medio, sombra oscura) sin inventar un hue nuevo.

### 1.3. Verificación de contraste (WCAG 2.1 AA, 4.5:1 texto normal)

| Combinación | Ratio aprox. | Resultado | Dónde se usa |
|---|---|---|---|
| Texto blanco sobre `--ag-color-primary` (verde-700) | 5.4:1 | Pasa AA | Botón primario, ambos temas |
| Texto gris-900 sobre `--ag-color-accent` (ámbar-700) | 5.0:1 | Pasa AA | Botón de acento — **con texto oscuro, no blanco** |
| Texto blanco sobre ámbar-700 | 3.1:1 | Falla AA texto normal | Por eso el acento usa `--ag-color-accent-contrast` = gris-900, no blanco |
| `--ag-color-text` (gris-900) sobre `--ag-color-bg`/`--ag-color-bg-elevated` (tema claro) | ~15.8:1 | Pasa AAA | Texto de cuerpo, tema claro |
| `--ag-color-text-muted` (gris-600) sobre blanco | ~4.7:1 | Pasa AA (al límite) | Texto secundario, tema claro |
| `--ag-color-text` (gris-100) sobre `--ag-color-bg` (gris-950, tema oscuro) | ~14.9:1 | Pasa AAA | Texto de cuerpo, tema oscuro |
| `--ag-color-primary-emphasis` (verde-300) sobre `--ag-color-bg-chrome` negro puro (tema oscuro) | ~10.3:1 | Pasa AAA | Ítem de menú activo sobre sidebar oscuro |
| `--ag-color-primary-emphasis` verde-700 (si se usara igual que en claro) sobre negro puro | ~2:1 | Falla | Por eso `-emphasis` se aclara a verde-300 en tema oscuro, mientras el relleno sólido del botón (`--ag-color-primary`) se mantiene igual en ambos temas |

Consecuencia de diseño explícita: **el relleno sólido de marca (botones) es constante entre temas** (identidad de marca no cambia); lo que sí se reasigna por tema son los tonos usados como *texto/ícono sobre una superficie* (`-emphasis`) y los fondos tenues (`-subtle`), porque esos sí dependen de si están sobre blanco o sobre negro.

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
| `--ag-font-family-base|mono`, `--ag-font-size-*`, `--ag-line-height-*`, `--ag-font-weight-*` | Tipografía |
| `--ag-radius-sm|md|lg|pill` | Radio de borde |
| `--ag-shadow-sm|md|lg` | Elevación (vocabulario Material) |
| `--ag-transition-fast|base` | Duración de transiciones |
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
| Molecule | `role-selector-item` / `role-selector` | — | Especificado, no implementado (§4.1) |
| Molecule | `theme-toggle` | — | Especificado, no implementado (§4.2) |
| Molecule | `menu-item` | — | Especificado, no implementado (§4.3) |
| Organism | `collapsible-menu-group` | — | Especificado, no implementado (§4.4) |
| Organism | `login-form` | — | Especificado, no implementado (§4.5) |
| Organism | `sidebar-nav` | — | Especificado, no implementado (§4.6) |
| Organism | `topbar` | — | Especificado, no implementado (§4.7) |
| Template | `panel-layout` | — | Especificado, no implementado (§4.8) |
| Template | `auth-layout` | — | Especificado, no implementado (§4.9) |

Por qué solo los átomos están implementados en este pase: es el límite de alcance que se fijó para esta entrega de HU-02 (tokens + piezas de más bajo nivel, sin lógica de negocio). Las moléculas/organismos/templates de abajo quedan **contratados** (props, slots, de qué sistema toman su base, qué tokens consumen) para que `frontend` no tenga que inventar markup suelto — cuando los necesite, se implementan acá, en `design-ui`, sobre esta misma especificación (responsabilidad 4 del agente).

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

- **Instalar Bootstrap/AdminLTE + la fuente Material Symbols + las fuentes IBM Plex Sans/IBM Plex Mono (auto-hospedadas, no CDN) vía npm, y el paquete `livewire/livewire` vía composer.** Nada de esto está en `package.json`/`composer.json` todavía (el scaffold actual es el default de `laravel new`, con Tailwind). `design-ui` no tiene shell — dejó los tokens y componentes listos para consumir estas dependencias en cuanto existan, pero no las instaló. → `frontend`.
- **Reemplazar el scaffold Tailwind de `resources/css/app.css`** por la pila Bootstrap+AdminLTE real (los tokens sobreviven el cambio, son CSS puro). → `frontend`.
- **Columna de preferencia de tema por usuario.** Vive en el modelo de usuario del módulo `Identidad` (ADR 0002 punto 4, ADR 0004 la excluye explícitamente de `sec_user`) — **el módulo `Identidad` todavía no existe** en `app/Dominios/`; crearlo es una decisión de `arquitectura`/`backend`, no de `design-ui`. Contrato sugerido para no reinventarlo al implementarlo: columna `tema_preferido` (string/enum `claro`|`oscuro`, o directamente los mismos valores `light`|`dark` que consume `data-bs-theme`, para no traducir entre capas), default `light`. La molécula `theme-toggle` (§4.2) ya emite el evento que esa persistencia necesita escuchar.
- **Middleware `ResolverRolActivo` y la ruta de cambio de rol activo** (ADR 0004 extensión, puntos 2 y 4). → `backend`.
- **Implementación de las moléculas/organismos/templates de §4** cuando `frontend` esté listo para ensamblar las pantallas reales de HU-02 (login, selector de rol, dashboard). → `design-ui`, a pedido.
- **Ensamblar las páginas concretas** (`pages/login`, `pages/seleccionar-rol`, el dashboard de cada rol) usando `auth-layout`/`panel-layout`. → `frontend`.
- **Asset de logo transparente** (hoy `logo-light.jpeg`/`logo-dark.jpeg` traen fondo sólido horneado en el archivo, no transparencia) — solo es un problema si el logo llega a usarse fuera del chrome de marca, donde su fondo coincide con `--ag-color-bg-chrome`. No bloqueante para HU-02.
