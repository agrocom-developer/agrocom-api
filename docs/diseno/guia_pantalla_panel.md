# Guía de construcción de una pantalla del panel

**Mantiene:** agentes `frontend` y `design-ui` (cada uno su mitad, ver §1). **Depende de:** ADR 0002 (AdminLTE + Atomic Design), ADR 0003 (arquitectura modular), ADR 0008 (separación backend/frontend), ADR 0013 (multi-idioma), CLAUDE.md invariantes 10 y 11.

Este documento es la **receta reproducible** para construir una pantalla nueva del panel. Los otros dos documentos de `docs/diseno/` no lo son y no pretenden serlo:

| Documento | Qué es | Cuándo abrirlo |
|---|---|---|
| `sistema_diseno_panel.md` | Catálogo e historial: qué token existe, con qué valor, qué componente está implementado y por qué se decidió así. Escrito como bitácora ("quinta vuelta", "novena vuelta") | Cuando necesitás el **valor exacto** de algo, o entender por qué una decisión es como es |
| `diseno-laravel.md` | Checklist genérico de gobernanza importado de otro proyecto. Sus valores son de ejemplo y **no** son los de este panel | Como checklist de proceso, nunca como fuente de valores |
| **este archivo** | Receta: dónde va cada archivo, en qué nivel de Atomic Design entra cada pieza, cómo se aplican SOLID y clean code en Blade, con qué se arma cada arquetipo de pantalla, y qué verificar antes de cerrar | **Cada vez que construís una pantalla** |

Quedan 25 pantallas por construir (`SecMenuSeeder` siembra 7 módulos y 33 ítems de menú; 8 tienen ruta). Esta guía existe para que las 25 salgan iguales sin revisarlas una por una.

---

## 1. Dónde va cada archivo

La frontera, en una línea: **si lo usan varios módulos es catálogo y vive en `resources/`; si pertenece a un solo módulo vive dentro de ese módulo.**

```
resources/views/app.blade.php                       cáscara HTML mínima (no la usa el panel)
resources/views/components/{atoms,molecules,organisms,templates}/
                                                    catálogo Atomic Design — lo mantiene `design-ui`
resources/css/tokens/{primitives,semantic}/         tokens — la ÚNICA fuente de color/medida
resources/css/components/<componente>.css           un archivo por componente del catálogo
resources/css/pages/<pantalla>.css                  CSS de UNA pantalla — lo mantiene `frontend`
resources/js/{atoms,molecules,organisms,pages}/     idem para JS

app/Dominios/<Modulo>/Infraestructura/Http/
    Controllers/Web/<X>Controller.php               adaptador delgado (ADR 0008)
    Views/pages/<pantalla>.blade.php                LA PÁGINA — nunca en resources/views/
    Views/pdf/<documento>.blade.php                 plantillas de PDF del módulo
lang/es/<modulo>.php                                todo el copy — cero texto literal en Blade
```

`resources/views/` tiene 30 archivos y **ninguno es una página**; eso es correcto y no cambia cuando entren las 25 pantallas que faltan. Una página es de su módulo (ADR 0008), igual que su controlador — por eso `app/Http/` tiene un solo archivo.

### 1.1. Receta: pantalla nueva, de cero

1. **Ruta** en `routes/web.php`, dentro del grupo `auth:interno` → `rol.activo`. Nombre `panel.<recurso>.<accion>`.
2. **Controlador** en `app/Dominios/<Modulo>/Infraestructura/Http/Controllers/Web/`. Verifica el permiso contra el **rol activo** (no la unión de roles — invariante 10), arma la cáscara con `CascaraPanel` e invoca un caso de uso de `Aplicacion/`. Ninguna regla de negocio acá.
3. **Vista** en `app/Dominios/<Modulo>/Infraestructura/Http/Views/pages/`.
4. **Namespace de vista** — solo la primera vez que el módulo tiene una pantalla. En su `ServiceProvider::boot()`:
   ```php
   View::addNamespace('comercial', app_path('Dominios/Comercial/Infraestructura/Http/Views'));
   ```
   y se consume como `view('comercial::pages.clientes.index')`. Namespace, no `View::addLocation`: evita que dos módulos con una `index.blade.php` colisionen.
5. **Copy** en `lang/es/<modulo>.php`. **Ítem de menú** en `SecMenuSeeder`, con `ruta` y `codigoPermiso`.
6. **CSS de página**, si hace falta, en `resources/css/pages/<pantalla>.css` + su `@import` en `pages/index.css`. Si el patrón se repite en dos pantallas, ya no es CSS de página: es un componente del catálogo y se lo pedís a `design-ui`.

---

## 2. Atomic Design: cómo se decide el nivel

Antes de escribir markup, la pregunta es siempre la misma: **¿esto es una pieza del catálogo o es de esta pantalla?** Y si es del catálogo, **¿de qué nivel?**

El criterio no es el tamaño ni la importancia visual: es **qué compone**.

| Nivel | Regla | Ejemplos vigentes |
|---|---|---|
| **atom** | No compone ningún otro componente del catálogo. Es un control o una primitiva | `button`, `input`, `badge`, `icon`, `switch`, `logo` |
| **molecule** | Agrupa átomos en una unidad funcional, **sin orquestar otras moléculas** | `stat-card`, `form-section`, `menu-item`, `alert-strip`, `section-head`, `plan-card` |
| **organism** | Orquesta moléculas, y/o ocupa una zona completa de la pantalla, y/o tiene JS propio | `topbar`, `module-sidebar`, `module-rail`, `login-form`, `mapa-operativo` |
| **template** | Define la grilla de la pantalla y **no aporta contenido propio** | `panel-shell`, `panel-layout`, `auth-layout` |
| *page* | **No es catálogo.** Es la composición concreta, y vive en su módulo (§1) | `seguridad::pages.dashboard` |

Casos límite, resueltos:

- **`form-section` es molecule y no organism** aunque agrupe un formulario entero: solo aporta estructura y tipografía, no compone otros componentes ni tiene lógica.
- **`topbar` es organism aunque parezca "una barra"**: orquesta `menu-item`, `theme-toggle`, `badge` y tiene un popover con JS.
- **`role-selector` no existe como "lista de roles"**: el catálogo tiene `role-card` (el ítem) y cada contenedor arma su propia lista con un `@foreach`. Una molécula "lista de X" casi nunca se gana su lugar — la lista es de la página; el ítem es del catálogo.

Y las dos reglas de gobernanza:

- **Un patrón que aparece dos veces en la pantalla ya no es markup: es un componente del catálogo.** Nace así `form-section`, cuando `organizacion` repetía cuatro `<fieldset style="…">` idénticos.
- **El catálogo lo mantiene `design-ui`, las páginas `frontend`.** Si tu pantalla necesita una pieza que no existe, no la construís suelta dentro de la página: se la pedís a `design-ui`, entra al catálogo, y recién ahí la consumís. La lista de lo que hoy falta está en §6.3.

---

## 3. Clean code y SOLID en la capa de presentación

Los principios no cambian por ser Blade; cambia dónde se aplican. Cada uno de estos ya cobró un bug real en este repo.

**SRP — una responsabilidad por archivo.** Vista renderiza, controlador adapta, `Aplicacion/` decide, `resources/css/` viste. Ningún `@php` en una página calcula una regla de negocio: solo normaliza para pintar (lo que hace `panel-layout` resolviendo `route()` y marcando el ítem activo). Cero consultas en la vista — el controlador entrega el array ya listo, y con eso desaparece la clase entera de N+1 en Blade.

**OCP — extender sin modificar.** Un componente crece por **props y slots**, nunca editando a sus consumidores. `atoms/button` suma un `variant` nuevo en su CSS y ninguna página cambia; `alert-strip` acepta `<x-slot:action>` en lugar de tener un prop por cada acción imaginable. Corolario: si para agregar un caso hay que tocar cinco páginas, el diseño del componente está mal.

**LSP — un componente sustituye a su elemento raíz.** Todo componente **fusiona `$attributes` en su nodo raíz**: `{{ $attributes->class(['ag-x']) }}`. Sin eso, quien lo consuma pasando un `data-*`, un `id` o un `aria-*` lo pierde en silencio. Es exactamente el bug de `login-form`, que descartaba el `data-ag-login-form` que la página le pasaba y hacía que el login posteara nativo y mostrara el JSON crudo.

**ISP — props mínimas y honestas.** Un componente no recibe el objeto entero "por si acaso": recibe lo que pinta. Doce props opcionales suelen ser dos componentes mal fusionados.

**DIP — la dependencia apunta al catálogo, nunca al revés.** Un componente de `resources/views/components/` **no conoce ninguna ruta con nombre, ningún modelo Eloquent y ningún módulo de dominio**. Recibe datos ya resueltos. Es lo que rompió el sidebar cuando pasaba `panel.dashboard` (el nombre crudo de la ruta) a `menu-item`, que documenta esperar la URL ya resuelta: todos los links del menú quedaron rotos. La conversión `Route::has($r) ? route($r) : $r` es responsabilidad del template, no del átomo.

Y las convenciones que no son negociables aunque no sean "principios":

- **Dominio en español, infraestructura en inglés**: `hectareas_declaradas`, `Sesion`, `Mezcla`, pero `SyncController`, `Repository`. En Blade: clases CSS en inglés con prefijo `ag-` (`.ag-form-section__body`), copy en español vía `__()`.
- **BEM para las clases**: `.ag-<componente>__<parte>--<modificador>`. Una clase de página nunca estiliza un componente del catálogo por dentro; si hace falta, el componente necesita un prop.
- **El comentario de cabecera de cada componente dice qué es, qué NO es y por qué está en ese nivel.** Es la convención vigente y es lo que evita que el próximo agente reinvente `theme-toggle` como átomo.

---

## 4. El esqueleto: qué pone el layout y qué pone la página

Toda pantalla autenticada del panel se envuelve igual:

```blade
<x-templates.panel-shell :title="__('modulo.pantalla.titulo')" :tema="$tema">
    <x-templates.panel-layout :menu="$menu" :roles="$roles" ... :vista-actual="__('...')">
        {{-- Acá empieza tu pantalla --}}
    </x-templates.panel-layout>
</x-templates.panel-shell>
```

`panel-layout` ya resuelve el **chrome** completo — no lo redibujes:

- Nivel 1: riel de módulos (74px, oliva oscuro en ambos temas).
- Nivel 2: sidebar de ítems del módulo activo (252px, solo ≥1200px; en 768–1199 se convierte en banda de píldoras; en <768 en drawer).
- Header: breadcrumb, buscador, usuario + rol activo, tema, notificaciones, período, campaña.
- Pie mono con copyright y versión.

**Nivel 3 (pestañas dentro del contenido) sí es de la página.** El layout no las pone.

### 4.1. Regla de scroll — el chrome no scrollea, el contenido sí

`.ag-panel` fija `height: 100dvh; overflow: hidden`, y `.ag-panel__content` es el **único** contenedor con scroll (`flex: 1; min-height: 0; overflow-y: auto`). La ventana nunca debe scrollear: si `document.documentElement.scrollHeight > window.innerHeight`, hay un bug.

Lo que rompe esta regla y no se ve venir: **un `position: absolute` cuyo ancestro no está posicionado**. Su bloque contenedor pasa a ser el bloque contenedor inicial, no la caja que lo rodea, y entonces `overflow` no lo recorta: el elemento estira el documento y arrastra el panel de altura fija fuera de la pantalla. Es lo que le pasó a `atoms/switch` — la mitad `position: relative` faltaba en `.ag-switch__control`, y `/panel/organizacion` desbordaba 886px con un viewport de 900, dejando media pantalla en blanco.

> **Regla:** todo `position: absolute` declara explícitamente cuál es su ancestro `position: relative`. Vale siempre, y muy en particular para la técnica *visually-hidden* de los controles de formulario (`switch`, `plan-card`), donde el input absoluto no se ve y el síntoma aparece a 800px de distancia.

Se verifica con una línea, y está en el checklist de §8.

---

## 5. Anatomía común: la cabecera de página

Las tres pantallas arquetipo empiezan igual (referencia: dashboard "Operación de hoy" y el canvas "Registro de la compañía"):

```
┌───────────────────────────────────────────────────────────────────────┐
│  Título de la pantalla                        [Secundaria] [Primaria] │   h1 + acciones
│  Bajada de una línea · qué decide el usuario acá                      │   subtítulo muted
├───────────────────────────────────────────────────────────────────────┤
│  ⚑ Aviso bloqueante, si lo hay                            [Resolver]  │   alert-strip
├───────────────────────────────────────────────────────────────────────┤
│  Resumen   Mapa   Por lote   Multimedia                               │   tabs (nivel 3)
└───────────────────────────────────────────────────────────────────────┘
```

Reglas fijas:

- **Un solo botón sólido sobre el pliegue.** La acción primaria es `variant="primary"`; todo lo demás es `outline`. (Regla ya validada, `sistema_diseno_panel.md` §10.4.)
- **Alertas antes del contenido, ordenadas por criticidad** (`danger` → `warning` → `info`), con `molecules/alert-strip`. Un aviso no bloqueante no merece un strip completo: va como chip junto al subtítulo (lo que hace el dashboard con la ventana volable).
- **El título es `h1` y hay exactamente uno.** Los rótulos de sector son `h2` vía `molecules/section-head`.
- **Cero texto literal en Blade** — todo por `__('modulo.pantalla.clave')` (ADR 0013).

### 5.1. Estado vacío: dos casos distintos, dos piezas distintas

No es una sola pieza con variantes — son dos situaciones distintas que se leen distinto:

- **La sección tiene datos en general, pero el filtro elegido no trae nada** (p. ej. "sin sesiones de este cliente" habiendo sesiones de otros). Sigue siendo `molecules/alert-strip variant="info"` con ícono, en línea con el resto del contenido — precedente: `comercial::pages.reportes-comerciales.index` ("sin resultados coincidentes"), `personal::pages.personas.desempeno` (sección "Sesiones" filtrada).
- **La pantalla (o un bloque completo suyo) no tiene NADA que mostrar**, más allá de cualquier filtro — p. ej. un rol recién creado sin permisos, o una persona sin ninguna sesión registrada. Ahí no alcanza un alert-strip: es una tarjeta centrada con ícono grande (`atoms/icon size="lg"`), título (`h2`) y una línea de detalle que explica qué hace falta para que deje de estar vacío. Precedente: `seguridad::pages.dashboard._sin-secciones`, `personal::pages.personas.desempeno` (bloque `.ag-persona-desempeno__vacio`, cuando `$sinDatosEnRango`).

Esta segunda pieza **todavía no es del catálogo** — cada página la arma con su propia clase BEM (`.ag-dash__vacio`, `.ag-persona-desempeno__vacio`), sin `.ag-card` compartida porque esa clase hoy es local a `dashboard.css`. Si una tercera pantalla necesita este patrón, ya son tres repeticiones: se lo pedís a `design-ui` como `molecules/empty-state` (ícono + título + detalle + slot de acción opcional) en vez de copiar la clase una cuarta vez.

---

## 6. Los tres arquetipos

### 6.1. Tablero (dashboard de módulo)

Referencia viva: `seguridad::pages.dashboard`.

Cabecera → tabs → por cada sector: `molecules/section-head` (barra de color + rótulo mono uppercase + contador opcional) y debajo su contenido. Gráficos con `molecules/apex-chart` (import dinámico, ver `sistema_diseno_panel.md` §13.1); indicadores con `molecules/stat-card`; distribuciones con `molecules/distribution-bar` (**nunca** un donut nuevo: `donut-chart` se retiró).

### 6.2. Listado

Referencia viva: la tabla "Clientes" del dashboard y `operaciones::pages.trabajos.index`.

Orden fijo de secciones: cabecera → filtros → tabla → paginación. La tabla usa `--ag-color-bg-table-head`, `--ag-color-border-row` y `--ag-color-bg-row-hover`; los estados por fila son `atoms/badge`, y las cifras van en `--ag-font-family-mono` para que aliñen en columna.

Responsive: la tabla no scrollea horizontalmente en móvil — colapsa. El dashboard ya tiene el patrón resuelto en tres variantes (`_tabla-sesiones` / lista de dos líneas en tablet / `_fichas-sesiones` en móvil); copiá ese patrón, no inventes uno nuevo.

### 6.3. Formulario — **la referencia canónica es el canvas "Registro de la compañía"**

Es el arquetipo que faltaba definir. Anatomía:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Registro de la compañía                      [Descartar] [Guardar]     │
│  Gestión centralizada de tu organización.                               │
│  ⚑ Vista previa. Los cambios no se persisten todavía.                   │
│  Organización │ Usuarios y roles │ Facturación                          │
├──────────────────────────────────────┬──────────────────────────────────┤
│ ▌DATOS DE EMPRESA          3 CAMPOS  │ ▌PERFIL COMPLETO                 │
│ ┌──────────────┬──────────────┐      │  83%  5 de 6 campos              │
│ │ Nombre       │ Rubro        │      │  ▓▓▓▓▓▓▓▓▓▓▓▓░░                  │
│ │ [__________] │ [__________] │      │  ✓ Nombre  ✓ Rubro  ! Domicilio  │
│ │ ayuda        │ ayuda        │      │                                  │
│ └──────────────┴──────────────┘      │ ▌SUSCRIPCIÓN                     │
│ ┌─────────────────────────────┐      │  Plan          Operación Pro     │
│ │ Logo (campo ancho completo) │      │  Estado           [Vigente]      │
│ └─────────────────────────────┘      │  Renueva        01/10/2026       │
├──────────────────────────────────────┤  Dispositivos        6 / 10      │
│ ▌DATOS DE CONTACTO         3 CAMPOS  │  [   Ver facturación   ]         │
│  …                                   │                                  │
├──────────────────────────────────────┴──────────────────────────────────┤
│ AGROCOM SRL · 2026        Cambios sin guardar  [Descartar] [Guardar]    │  barra pegajosa
└─────────────────────────────────────────────────────────────────────────┘
```

Las seis reglas que salen de ahí:

1. **Cada sección es una tarjeta, no un `<fieldset>` desnudo.** Superficie `--ag-color-surface-card`, borde `--ag-color-border-card`, radio `--ag-radius-lg`, y un header separado por borde que es exactamente `molecules/section-head` (la barra de color + el rótulo mono uppercase + el contador — el contador dice cuántos campos tiene la sección).
2. **Dos columnas de campos dentro de la tarjeta, no una, y nunca más de dos.** `grid-template-columns: repeat(auto-fit, minmax(max(14rem, calc(50% - var(--ag-space-4) / 2)), 1fr))`; el techo en 50% es lo que impide que en pantallas anchas el `auto-fit` meta 4-6 columnas angostas — un `minmax(14rem, 1fr)` sin ese techo no alcanza. Un campo que necesita el ancho completo (dirección, logo, textarea) declara `grid-column: 1 / -1`. Un formulario de una sola columna angosta desperdicia toda la mitad derecha de la pantalla — es lo que hace hoy `organizacion` con su `max-width: 600px`.
3. **Cada campo son tres piezas: label, control, ayuda.** El texto de ayuda no es opcional cuando el campo tiene una consecuencia que el usuario no puede adivinar ("Razón social visible en órdenes y reportes exportados"). Es `atoms/input` con su prop `help`.
4. **Columna lateral pegajosa (`position: sticky`) para lo que no se edita**: progreso de completitud, resumen del plan, metadatos. `flex: 1 1 19rem; max-width: 20rem`. Nunca campos ahí.
5. **Barra de acciones pegajosa al pie**, con el estado de guardado en texto ("Sin cambios pendientes" / "Cambios sin guardar") y las mismas dos acciones de la cabecera. En un formulario largo, el usuario no debería scrollear para guardar.
6. **Cifras y datos técnicos en `--ag-font-family-mono`** (teléfono, fechas, "6 / 10"); prosa y labels en la familia base.

#### Qué falta en el catálogo para armar esto

Ninguna de estas piezas se construye suelta dentro de una página: se le piden a `design-ui`.

| Pieza | Nivel | Estado |
|---|---|---|
| `form-section` como **tarjeta** con `section-head` de header | molecule | **Existe, hay que evolucionarlo** — hoy es `<fieldset>`+`<legend>` sin chrome ni grid de dos columnas |
| `page-header` (h1 + bajada + acciones) | organism | **Falta** — hoy cada página repite el markup (`ag-dash__header`, `ag-organizacion__intro`…) |
| `tabs` | molecule | **Falta como componente** — el CSS (`tabs.css`) existe y el dashboard lo usa a mano con `data-bs-toggle` |
| `form-actions-bar` pegajosa con estado dirty | organism | **Falta** |
| `summary-card` (lista etiqueta→valor + acción al pie) | molecule | **Falta** — es la tarjeta de "Suscripción" |
| `progress-meter` (porcentaje + barra + checklist) | molecule | **Falta** — es "Perfil completo" |
| `file-field` (preview + reemplazar/quitar) | molecule | **Falta** — hoy es markup suelto en `organizacion.css` |

---

## 7. Traducción del canvas a tokens — **nunca copiar un hex del mockup**

Del mockup se toma **estructura y medidas**; el vocabulario sale de la especificación y el color de los tokens derivados de los logos oficiales. Un canvas de Claude Design trae su propia paleta y sus propias fuentes: son referencia de composición, no valores a pegar.

| En el canvas | En el panel |
|---|---|
| `#f4f3ec` fondo de página | `var(--ag-color-bg)` |
| `#fbfaf5` superficie de tarjeta | `var(--ag-color-surface-card)` |
| `#e3e1d6` borde de tarjeta | `var(--ag-color-border-card)` |
| `#1c6b2c` verde de acción | `var(--ag-color-primary)` |
| `#2f9642` barra del rótulo de sector | `var(--ag-color-primary-emphasis)` |
| `#7d8476` texto secundario | `var(--ag-color-text-muted)` |
| `#e4f2e0` / `#c6e2bf` chip "Vigente" | `var(--ag-color-success-subtle)` / `var(--ag-color-success-border)` |
| `#fdf4e3` / `#d79b2b` tira de aviso | `molecules/alert-strip variant="warning"` |
| `"Public Sans"` | `var(--ag-font-family-base)` |
| `"IBM Plex Mono"` | `var(--ag-font-family-mono)` |
| `14px` / `13px` / `12px` | `--ag-font-size-sm` (0.875rem) / `--ag-font-size-sm` / `--ag-font-size-xs` |
| `38px` título | `--ag-font-size-2xl` con `--ag-font-family-display` |
| `18px` / `20px` / `26px` separaciones | `--ag-space-4` (1rem) / `--ag-space-5` (1.5rem) / `--ag-space-6` (2rem) |
| `10px` / `14px` radios | `--ag-radius-md` (0.5rem) / `--ag-radius-lg` (1rem) |

Las medidas del canvas están en px y la escala del panel en rem sobre base 8: **redondeá al peldaño de la escala**, no agregues un token nuevo para clavar los 18px exactos. Si de verdad falta un peldaño, se lo pedís a `design-ui` y se agrega al primitivo, nunca en la hoja de la página.

Y el aviso que ya cobró dos veces: un canvas es un mockup, no la implementación. Nada de su HTML se copia literal — se traduce a componentes del catálogo con tokens.

---

## 8. Checklist de cierre (antes de dar la pantalla por terminada)

**Arquitectura**
- [ ] La página está en `Views/pages/` de su módulo, no en `resources/views/`.
- [ ] El controlador no tiene reglas de negocio; invoca `Aplicacion/`.
- [ ] El permiso se verifica contra el **rol activo**, y el ítem del menú lleva `codigoPermiso`.

**Atomic Design** (§2)
- [ ] Cada pieza nueva está en el nivel que le corresponde por **qué compone**, no por su tamaño, y su comentario de cabecera dice qué es y qué no es.
- [ ] Ningún patrón repetido dos veces quedó como markup suelto: o es del catálogo, o se justifica por qué no.
- [ ] Ninguna pieza del catálogo se construyó dentro de la página — las nuevas pasaron por `design-ui` y quedaron registradas en `sistema_diseno_panel.md` §3.

**Clean code / SOLID** (§3)
- [ ] Ningún `@php` de la página calcula una regla de negocio, y no hay una sola consulta en la vista.
- [ ] Todo componente nuevo fusiona `$attributes` en su nodo raíz (`{{ $attributes->class([...]) }}`) — LSP.
- [ ] Ningún componente del catálogo nombra una ruta, un modelo Eloquent o un módulo de dominio: recibe datos ya resueltos — DIP.
- [ ] Ninguna clase CSS de página estiliza por dentro a un componente del catálogo; si hizo falta, el componente ganó un prop.
- [ ] Nombres: dominio en español, infraestructura en inglés; clases BEM con prefijo `ag-`.

**Diseño**
- [ ] Cero color, tamaño, radio o duración literal en Blade y CSS: todo por token (invariante 11). Verificable con `grep -nE '#[0-9a-fA-F]{3,8}|[0-9]+px' resources/css/pages/<pantalla>.css` — las únicas apariciones legítimas son el hairline `1px solid`, los breakpoints de la escala (768/1200) y los comentarios. Todo lo demás (un `max-width: 600px`, un `minmax(250px, …)`) es una medida inventada que se le escapó al sistema.
- [ ] Cero texto literal en Blade: todo por `__()`, con las claves en `lang/es/<modulo>.php`.
- [ ] Un solo botón sólido sobre el pliegue.

**Layout**
- [ ] La ventana no scrollea. Verificable en consola: `document.documentElement.scrollHeight - window.innerHeight` debe dar **0**.
- [ ] Todo `position: absolute` tiene un ancestro `position: relative` explícito.
- [ ] Se ve bien en los tres breakpoints: ≥1200, 768–1199, <768.

**Verificación visual** (obligatoria, el cálculo en papel no alcanza)
- [ ] Vista en navegador real, **en tema claro y en tema oscuro**, logueado con `carlos.ferrufino` / `password`.
- [ ] Contraste AA (4.5:1) verificado en las combinaciones nuevas, y anotado en `sistema_diseno_panel.md` §1.3 si el par no estaba.
- [ ] `bin/verify` en verde (ver skill `verificacion`).

**Documentación**
- [ ] Si se agregó o renombró un componente, la tabla de `sistema_diseno_panel.md` §3 quedó al día. Verificable:
  ```sh
  diff <(find resources/views/components -name '*.blade.php' | sed 's|.*/||;s|\.blade\.php||' | sort) \
       <(grep -oE 'components/(atoms|molecules|organisms|templates)/[a-z-]+' docs/diseno/sistema_diseno_panel.md | sed 's|.*/||' | sort -u)
  ```

---

## Referencias

- `docs/diseno/sistema_diseno_panel.md` — valores exactos de tokens y catálogo.
- `docs/decisiones/0002-...`, `0003-...`, `0008-...` — por qué el panel está armado así.
- `.claude/skills/panel-design-ui/SKILL.md` — mapa rápido y reglas de pulido ya confirmadas.
- Canvas "Registro de la compañía" (Claude Design) — referencia de composición del arquetipo formulario, §6.3.
