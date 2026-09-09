# Plan — Rediseño del dashboard admin (panel de 3 niveles) + sincronización de sistema de diseño

**Estado: CERRADO (28/8/2026).** Este documento se escribió en lugar de implementar directamente porque el pedido original (sesión del 28/8/2026) toca demasiadas piezas a la vez para una sola pasada segura: sidebar, header, KPIs, gráficas, alertas, vistas de Sesiones/Pausas, contraste de tema oscuro, y además la sincronización de reglas de diseño ya confirmadas en login/selección de rol hacia agentes/memoria/documentación. Se investigó todo el contexto necesario (código actual + referencia visual + catálogo vigente) para que la próxima iteración pueda ejecutar directamente, fase por fase, sin tener que re-descubrir nada.

**Progreso:** Anexo A ✅ · Fase 1 (sidebar) ✅ · Fase 2 (header) ✅ · Fase 7 (contraste oscuro) ✅ · Fase 3 (KPI cards + sectorización) ✅ · Fase 4 (gráfica mock, donut sesiones por estado) ✅ · Fase 5 (reordenar alertas) ✅ · Fase 6 (detalle de Sesiones/Pausas) ✅ · Fase 8 (auditoría final) ✅ — las ocho verificadas en navegador (Playwright, claro/oscuro/móvil). Detalle de qué cambió en cada una: `docs/diseno/sistema_diseno_panel.md` §9 (fases 1-6) y §10 (fase 8). **La Fase 6 fue reemplazada el 29/8/2026 (novena vuelta) — ver §6, más abajo.**

**Decisión de Fase 6 (confirmada 28/8/2026):** enriquecer los tabs "Sesiones"/"Pausas" que ya existen dentro del dashboard — NO rutas propias. No tocó `SecMenuSeeder` ni creó controllers nuevos.

**Fase 8 (auditoría final, cerrada 28/8/2026, rama `feature/auditoria-visual`):** una revisión de diseño externa llegó justo después de que este plan dejara la Fase 8 pendiente — la cierra y la amplía con 9 observaciones nuevas que van más allá de lo que tocaron las fases 1-6 (tokens de color, contraste AA, botones, `donut-chart` → `distribution-bar`, grilla, tipografía, elevación en oscuro reverificada, detalles menores). Además, durante la misma sesión el usuario pidió dos ajustes puntuales fuera de la lista original de la revisión: tooltip en los ítems del menú (nivel 3) cuando el sidebar está colapsado, y refuerzo del feedback visual de "presionado/abierto" en los popups del header (avatar, notificaciones, período). Detalle completo: `docs/diseno/sistema_diseno_panel.md` §10.

**Cómo usar este documento:** cada fase es idealmente una iteración/PR separada (ver `iterar-en-el-mismo-pr` en memoria — varias fases SÍ pueden ir en el mismo PR si se hacen en la misma sesión, pero cada fase es un checkpoint verificable por separado). El Anexo A (sincronización de reglas ya confirmadas) no depende de ninguna fase del dashboard y puede ejecutarse primero, en cualquier momento.

---

## 0. Alcance del pedido original (28/8/2026)

1. Sidebar nivel 2 (nombre+descripción del módulo): agregar collapse/expand con ícono de hamburguesa — **no existe hoy**.
2. Badges de los ítems de menú (nivel 3): mostrar solo el número + tooltip con el texto completo + el número.
3. Color ámbar de los badges y verde de selección del menú: "bajo contraste" en ambos temas, en particular tema oscuro — corregir para que se vean "más nítidos".
4. Header: reagrupar explícitamente — izquierda = módulo/breadcrumb + menú + búsqueda; derecha = usuario, tema, notificación, fecha/campaña (en ese orden).
5. Tarjetas KPI: adoptar el lenguaje visual de `docs/ganadosoft-dashboard.html` (tipografía de título de sector, barra lateral de color antes del título, anatomía de la tarjeta) — separando el dashboard en sectores.
6. Agregar gráficas/estadísticas mock, ubicadas antes de "Programación de hoy", "Pausas" y "Stock".
7. El alert "2 sesiones cerradas sin captura del RC..." debe estar al INICIO de la página (hoy está al final del tab Resumen), y todas las alertas en orden de criticidad.
8. Tabs "Sesiones" y "Pausas": más detalle y otro estilo de vista, inspirado en el mismo dashboard de referencia.
9. Revisar contraste general en tema oscuro (evidencia: captura de pantalla del 28/8/2026, ver §3) — "todo casi del mismo tono, confunde y no resalta lo importante".
10. Todo lo anterior debe cumplir: tokens (cero hardcode), Atomic Design, clean code, y el patrón "template constante + content variable por página" (ya vigente vía `panel-layout.blade.php`, confirmar que se respeta).
11. **Aparte, en paralelo:** sincronizar en agentes/memoria/documentación las reglas de diseño ya aplicadas y confirmadas en login + selección de rol (sesión del 28/8/2026, ver Anexo A) — y crear skill(s) de proyecto para no releer todo el contexto de diseño en cada iteración futura.

---

## 1. Estado actual del código (investigado, no modificado)

### 1.1 Template y layout de 3 niveles

- `resources/views/components/templates/panel-layout.blade.php` — **ya es** el template constante (sidebar + header + `<main>{{ $slot }}</main>` + footer) que exige el enfoque de `docs/diseno/diseno-laravel.md` §5. Cada página del panel ya solo aporta su `$slot` — no hace falta reconstruir nada de este patrón, solo respetarlo en las fases nuevas.
- Nivel 1 (riel de módulos): `organisms/module-rail.blade.php` + `resources/css/components/module-rail.css`. 74px, solo íconos, superficie oliva oscura **constante en ambos temas** (`--ag-color-bg-rail*`).
- Nivel 2 (sidebar del módulo activo): `organisms/module-sidebar.blade.php` + `resources/css/components/module-sidebar.css`. Cabecera `.ag-module-sidebar__header` (líneas 19-26 del CSS): `<h2>` (nombre, serif 22px) + `<p>` (descripción). **No existe ningún botón de hamburguesa ni mecanismo de collapse/expand manual** — hoy solo se oculta automáticamente por breakpoint (`display:none` bajo 1200px). Hay que construirlo desde cero (Fase 1).
- Nivel 3 (ítems): `molecules/menu-item.blade.php` + `.css`; submenús anidados vía `organisms/collapsible-menu-group.blade.php` + `.css` (usa el `collapse` nativo de Bootstrap — mecanismo distinto al que hay que construir en Fase 1, que es para colapsar el sidebar completo, no un grupo de ítems).

### 1.2 Badges del menú y color de selección — estado y tokens exactos

- `menu-item.blade.php:54-56` / `menu-item.css:67-79`: el badge pinta el string completo tal cual llega (`{{ $badge }}`) — sin separar número/texto, sin tooltip.
- Ítem activo (`menu-item.css:48-53`): `background: var(--ag-color-primary-subtle); color: var(--ag-color-success-strong); font-weight: bold;`

| Token | Claro | Oscuro |
|---|---|---|
| `--ag-color-success-strong` (texto ítem activo) | `color-mix(green-700 50%, green-900 50%)` | `var(--ag-color-green-300)` |
| `--ag-color-primary-subtle` (fondo ítem activo) | `var(--ag-color-green-100)` | `rgba(140,198,63,0.16)` |
| `--ag-color-accent-subtle` (fondo badge) | `var(--ag-color-amber-100)` | `rgba(245,166,35,0.16)` |
| `--ag-color-accent-link` (texto badge) | `var(--ag-color-amber-800)` | `var(--ag-color-amber-300)` |

Diagnóstico: en tema oscuro, `success-strong` (verde-300) sobre `primary-subtle` (verde muy tenue) y `accent-link` (ámbar-300) sobre `accent-subtle` (ámbar muy tenue) quedan dentro de un sidebar que ya usa superficies oscuras de tono similar (`--ag-color-surface-card`/`--ag-color-bg-chrome`) — coincide con la queja de "todo casi el mismo tono" y con lo que muestra la captura (§3). **No es necesariamente un fallo de ratio AA aislado** (habría que remedir contra el fondo real del sidebar, no solo contra su propio `-subtle`) — es un problema de **jerarquía/diferenciación de superficies** en conjunto. Ver Fase 7.

### 1.3 Header/topbar actual

`organisms/topbar.blade.php` + `resources/css/components/topbar.css`. Orden actual, una sola fila sin agrupación explícita en dos bloques:

```
breadcrumb (módulo › vista) → buscador (flex:1 1 300px, es lo único que crece) → chip campaña → selector período → campana notificaciones → theme-toggle → usuario (avatar+nombre+rol)
```

El buscador con `flex:1` ya empuja todo lo posterior a la derecha "por accidente" — hoy funciona aproximadamente como el usuario pide (izquierda = breadcrumb+búsqueda, derecha = resto), pero:
- Falta una agrupación CSS explícita en dos bloques (`.ag-topbar__left`/`.ag-topbar__right` o similar), no depender solo del `flex:1` del buscador.
- El orden DENTRO del grupo derecho pedido es: usuario, tema, notificación, fecha/período, campaña — el actual es: campaña, período, notificaciones, tema, usuario (orden inverso). Confirmar este orden exacto con el usuario antes de tocar el markup (es un cambio de bajo riesgo pero vale la pena la confirmación rápida en vez de asumir).

### 1.4 Dashboard actual: estructura, KPIs, alertas, tabs

- Ruta `panel.dashboard` → `App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\DashboardController::index()` → vista `app/Dominios/Seguridad/Infraestructura/Http/Views/pages/dashboard.blade.php` + parciales `_tabla-sesiones.blade.php`, `_fichas-sesiones.blade.php`, `_barras-pausas.blade.php` (misma carpeta).
- Orden real hoy: header (título+bajada+botones) → tabs "Resumen/Sesiones/Pausas" → **tab Resumen**: alert-strip ámbar "ventana volable" → 4 KPI (`molecules/stat-card`, fila única) → grid tabla-sesiones + aside (pausas/stock) → **alert-strip danger "sesiones sin RC" AL FINAL** (líneas 158-164, después de todo lo demás) → **tab Sesiones**: filtros decorativos + tabla + fichas móvil → **tab Pausas**: barras + alert-strip "sin causa".
- Datos: 100% mock, centralizados en `app/Dominios/Seguridad/Infraestructura/Http/Demo/DatosDemoPanel.php` (ya documentado como MOCK explícito, "a reemplazar cuando existan los casos de uso reales"). Ningún dato hardcodeado en la vista — este patrón hay que conservarlo en todas las fases nuevas.
- `molecules/stat-card`: props `label, icon, value, valueSuffix, foot, footIcon, footTone(success|warning|muted), hero`. Estructura plana: head (label+ícono) → valor grande (display) → pie con ícono+tono. **No tiene** riel lateral de color ni ícono en contenedor con `data-state` — es más plano que la referencia ganadosoft (§2).
- `alert-strip` (`molecules/alert-strip.blade.php` + `.css`): solo 2 variantes hoy, `accent` (ámbar, borde izq 3px) y `danger` — no hay variante `warning` separada ni ningún mecanismo de orden/prioridad entre alertas.
- Tab Sesiones: lista plana de 5 filas (hora, lote, piloto, dron, hectáreas, estado) — sin drill-down, sin columna de evidencia/RC.
- Tab Pausas: solo el agregado por causa (barras `<div>` con `width` inline) — sin tabla de eventos individuales.
- **Ninguna librería de gráficos** en `package.json` (ni Chart.js, ni ApexCharts). Las "barras" actuales son divs, no un chart real.
- **Módulos del menú sin página propia**: `SecMenuSeeder.php:60-66`, módulo `operacion` → `programacion` (única con `ruta: panel.dashboard`), y `ordenes, trabajos, sesiones, pausas, mezclas, evidencias` **sin `ruta` asignada** — hoy "Sesiones" y "Pausas" solo existen como tabs *dentro* del dashboard, no como rutas propias. `app/Dominios/Operaciones/` existe como carpeta de dominio pero sin `Infraestructura/Http/Controllers/Web` todavía.

**Decisión pendiente antes de la Fase 6** (no se puede resolver solo con este documento — confirmar con el usuario en la próxima iteración): ¿"más detalle y otro estilo de vista" en Sesiones/Pausas significa enriquecer los tabs actuales dentro del dashboard, o crear rutas/páginas propias (`/panel/operacion/sesiones`, `/panel/operacion/pausas`) con su propio controller+vista sobre `panel-layout`? Esto cambia el tamaño de la Fase 6 considerablemente.

---

## 2. Referencia visual — qué tomar de `docs/ganadosoft-dashboard.html`

Extraído del CSS embebido del archivo (leído completo, 456 líneas). **Nada de esto se copia literal** (los hex de ese archivo son de OTRA marca) — se traduce a los tokens `--ag-color-*`/`--ag-space-*`/`--ag-radius-*` ya existentes en `agrocom-api`, mismo criterio que la quinta vuelta aplicó al mockup `Login Agro Drones.dc.html`.

### 2.1 Título de sector + barra lateral

```css
.section-head{display:flex;align-items:center;gap:10px;margin-bottom:13px}
.section-head .bar{width:4px;height:16px;border-radius:3px;background:var(--primary)}
.section-head h2{font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:var(--muted)}
.section-head .count{font-family:'IBM Plex Mono';font-size:11px;color:var(--muted-2);margin-left:auto}
```

Traducción directa: barra `width:4px;height:16px;border-radius:3px;background:var(--ag-color-primary)` + `<h2>` en `--ag-font-family-base` (NO display/Fraunces — este es un label uppercase chico, no un titular), `font-size: var(--ag-font-size-xs)` (11px, más cerca del original 12px que cualquier otro token), `font-weight: var(--ag-font-weight-bold)`, `letter-spacing` ancho, `color: var(--ag-color-text-muted)`. El contador a la derecha en `--ag-font-family-mono`, `--ag-color-text-faint` o `-muted`.

### 2.2 Tarjeta KPI (`.tile`)

```css
.tile{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:17px 17px 15px;position:relative;overflow:hidden;transition:transform .18s,box-shadow .18s,border-color .18s}
.tile:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover)}
.tile-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
.tile-ic{width:34px;height:34px;border-radius:9px;display:grid;place-items:center;background:var(--surface-2);color:var(--muted)}
.tile-value{font-family:'IBM Plex Mono';font-size:30px;font-weight:600;letter-spacing:-1px}
.tile-foot{font-size:11px;color:var(--muted-2);margin-top:8px;display:flex;align-items:center;gap:5px}
/* estado: el borde izquierdo de 4px + el color del ícono cambian por data-state */
.tile[data-state="warn"]::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--warn)}
.tile[data-state="warn"] .tile-ic{background:var(--warn-bg);color:var(--warn)}
```

Traducción: `stat-card` necesita una nueva prop `state` (o reutilizar `variant` si el nombre ya existe — **verificar el prop real de `molecules/stat-card.blade.php` antes de tocarlo**, no asumir) que controle: (a) el color del ícono dentro de `.tile-ic` (contenedor 34×34, radio `--ag-radius-md`, fondo `-subtle` del estado), y (b) opcionalmente una barra izquierda de 4px con el color crudo del estado, solo para estados que requieren atención (`warn`/`crit`/`danger`) — igual que el original, que NO pinta el borde para `calm`/`good`. Ratio hover `translateY(-2px)` + `--ag-shadow-md` ya existen como tokens, reutilizar.

### 2.3 Bottom row: acciones pendientes + donut

- **Lista de acciones** (`.action-row`): ícono en contenedor 32×32 + texto + número en píldora mono + chevron — buen patrón para "Ver todas las pendientes" en cualquier panel lateral (aplica bien al panel de "Pausas por causa"/"Stock bajo mínimo" que ya existen en el dashboard de agrocom, se pueden migrar a este patrón).
- **Donut CSS puro** (sin librería):
```css
.donut{width:158px;height:158px;border-radius:50%;background:conic-gradient(#2F8F45 0 50.8%, #63B56E 50.8% 78.7%, #A6D79A 78.7% 90.2%, #E0A020 90.2% 100%)}
.donut::after{content:"";position:absolute;inset:26px;border-radius:50%;background:var(--surface)}
```
Anillo hueco con `conic-gradient` + leyenda (`dot` de color + nombre + valor mono + porcentaje). 100% replicable con los tokens categóricos que ya existen en agrocom (`--ag-color-success/warning/info/danger` o una escala propia) — **cero dependencia nueva**, coherente con "gráficas mock" del pedido. Candidato natural: distribución de sesiones por estado (Validada/Sin evidencia/En vuelo/Programada) o de hectáreas por piloto/lote.

### 2.4 Alert/attention strip

El "attention strip" de referencia agrupa VARIOS contadores a la derecha de un solo mensaje (`.a-items` → varios `.a-item` con número grande + label chico). El `alert-strip` actual de agrocom es más simple (un solo mensaje + botón). No hace falta copiar esa estructura de contadores múltiples salvo que el usuario la pida explícitamente — el pedido concreto sobre alertas fue de ORDEN/UBICACIÓN (Fase 5), no de rediseño de la tarjeta de alerta en sí.

### 2.5 Lo que NO se traduce

- La paleta completa (`--primary:#2F8F45`, sidebar `#193A22`, etc.) — es de otra marca, se ignora por completo.
- El sidebar de esa referencia es de 1 nivel con grupos de texto (`.nav-group`); agrocom ya tiene su propio sidebar de 3 niveles aprobado (quinta vuelta) — no se reemplaza la estructura, solo se toman patrones puntuales de KPI/sección.
- Los `.fin-card`/finance band (bloque de finanzas con tarjeta "hero" en gradiente) — no lo pidió el usuario, queda fuera de alcance salvo pedido explícito futuro.

---

## 3. Diagnóstico de contraste — tema oscuro

Evidencia: captura de pantalla revisada en esta sesión (dashboard real corriendo en `localhost:8000/panel/dashboard`, tema oscuro, usuario `camila.rojas`). Confirmado visualmente: el riel de módulos, el sidebar del módulo, el header, el fondo de contenido y las tarjetas KPI están todos en tonos oliva/verde oscuro muy cercanos entre sí — sin un salto de luminosidad claro entre "chrome" (riel/sidebar/header) y "contenido" (tarjetas/tablas). Los badges ámbar del menú ("Hoy · 3", "12 vigentes", etc.) y el estado "Sin evidencia" de la tabla se leen apagados contra ese fondo.

Esto coincide con el diagnóstico técnico de §1.2: varios tokens `-subtle` de tema oscuro (`primary-subtle`, `accent-subtle`) son overlays de **baja opacidad** (`rgba(x, 0.16)`) pensados para superficies de tarjeta (`--ag-color-surface-card`), pero el sidebar/header usan superficies de chrome (`--ag-color-bg-chrome`) de tono parecido — el overlay tenue no genera suficiente salto perceptual ahí.

**Línea de trabajo para la Fase 7** (a validar con capturas reales en el navegador, no solo cálculo): no es tocar un token aislado, es revisar el *sistema completo de elevación* en tema oscuro — cuántos escalones de luminosidad hay entre `--ag-color-bg` → `--ag-color-bg-chrome` → `--ag-color-surface-card` → estado activo/badge, y si hace falta separar más esos escalones o subir el peso del overlay de estado (`-subtle`) específicamente para las superficies de chrome. Coordinar con `design-ui` (tokens) + verificación visual con Playwright en ambos temas antes de dar por cerrada la fase (mismo procedimiento que se usó para login/selección de rol en esta sesión).

---

## 4. Fases de implementación propuestas

| # | Fase | Qué incluye | Archivos principales | Agente sugerido |
|---|---|---|---|---|
| 1 | Sidebar: collapse + badges + contraste | Botón hamburguesa en `.ag-module-sidebar__header` (nuevo JS mínimo, patrón de delegación de eventos como `theme-toggle.js`); separar el dato del badge en `{numero, texto}` (o parsear con cuidado, prefiriendo cambiar el shape de datos); tooltip nativo de Bootstrap (`data-bs-toggle="tooltip"`, ya cargado) con el texto completo; re-verificar/ajustar contraste de `success-strong`/`accent-link` en tema oscuro contra el fondo real del sidebar (no solo contra su propio `-subtle`) | `module-sidebar.blade.php`/`.css` + JS nuevo, `menu-item.blade.php`/`.css`, `theme-dark.css` | `design-ui` (tokens/contraste) + `frontend` (wiring de datos) |
| 2 | Header: reagrupar izquierda/derecha | Bloques CSS explícitos `.ag-topbar__left`/`__right`; confirmar orden exacto del grupo derecho con el usuario antes de tocar el markup | `topbar.blade.php`/`.css` | `design-ui` |
| 3 | KPI cards estilo ganadosoft + sectorización | Prop nueva en `stat-card` para ícono en contenedor + estado con borde lateral opcional; reestructurar el dashboard en secciones con `section-head` (barra + título uppercase + contador), cada una con su grid de KPIs | `molecules/stat-card.blade.php`/`.css` (nuevo patrón `section-head` — decidir si es molecule nuevo o utilidad CSS de página), `dashboard.blade.php` | `design-ui` (catálogo) + `frontend` (ensamblado) |
| 4 | Gráficas mock | Donut CSS puro (`conic-gradient`, sin librería) + leyenda, ubicado antes de "Programación de hoy"/Pausas/Stock; dato candidato: sesiones por estado o hectáreas por piloto | `dashboard.blade.php`, posible `molecules/donut-chart.blade.php` nuevo, `DatosDemoPanel.php` (dato mock nuevo) | `frontend` |
| 5 | Reordenar alertas | Mover el alert "sin captura RC" al inicio de la página (antes de los KPIs); definir criterio de orden por criticidad si en el futuro hay más de 2 alertas simultáneas (danger > warning/accent) | `dashboard.blade.php` | `frontend` |
| 6 | Detalle de Sesiones/Pausas | **Depende de la decisión pendiente de §1.4** (tabs enriquecidos vs. rutas propias). Si son rutas propias: nuevo controller+vista en `app/Dominios/Operaciones/Infraestructura/Http/` sobre `panel-layout`, más ruta en `SecMenuSeeder` | Por definir según la decisión | `arquitectura` (si son rutas nuevas) + `frontend` |
| 7 | Contraste tema oscuro (sistémico) | Revisar escalones de elevación chrome→contenido→estado en oscuro (ver §3); verificar en navegador con Playwright en ambos temas antes de cerrar | `theme-dark.css`, cualquier componente afectado | `design-ui` |
| 8 | Auditoría final | Tokens (cero hardcode), Atomic Design, clean code, patrón template+content — checklist §5 | Todo lo tocado en fases 1-7 | `validador` |

**Orden sugerido:** 1 → 2 → 7 (el contraste de fondo conviene resolverlo temprano, antes de que las fases 3-6 construyan sobre un sistema de elevación que va a cambiar) → 3 → 4 → 5 → 6 (la más grande e incierta, al final) → 8 siempre al cierre.

---

## 5. Checklist de gobernanza (aplicar en cada fase, no solo al final)

Tomado de `docs/diseno/diseno-laravel.md` §11 + CLAUDE.md invariante 11, adaptado a lo que ya rige en este repo (los VALORES concretos son los de `sistema_diseno_panel.md`, nunca los de ejemplo de `diseno-laravel.md`):

- [ ] ¿Todo color/tamaño/sombra/duración nuevo sale de un token `--ag-*` ya existente, o justifica un token nuevo (documentado en `sistema_diseno_panel.md`) en vez de un hex/px suelto?
- [ ] ¿El componente nuevo está en el nivel correcto de Atomic Design (atom/molecule/organism) y no mezcla render con lógica de datos?
- [ ] ¿La página sigue usando `panel-layout` + su propio `$slot` (nunca duplicando header/sidebar/footer)?
- [ ] ¿Los datos mock nuevos (gráficas, badges separados) tienen la forma que tendrá el dato real cuando exista el caso de uso (mismo criterio que `DatosDemoPanel` ya sigue)?
- [ ] ¿Toda animación nueva (hover de tile, collapse del sidebar) respeta `prefers-reduced-motion` con el mismo criterio del resto del sistema (acortar duración, nunca anular `transform` de golpe)?
- [ ] ¿Se verificó el resultado en el navegador (Playwright, ambos temas) antes de dar la fase por cerrada — no alcanza con el cálculo de contraste en papel?
- [ ] ¿Las claves i18n nuevas van al archivo de módulo correcto (`lang/es/operaciones.php` para vocabulario de Operaciones, no `seguridad.php`)?

---

## 6. Novena vuelta (29/8/2026) — el dashboard reemplaza Sesiones/Pausas/KPIs por Mapa/Resumen por lote/Multimedia

La Fase 6 (arriba, §4 y línea de Progreso) construyó tabs "Sesiones" y "Pausas" enriquecidos dentro del dashboard, más tarjetas KPI — y quedó cerrada ✅ el 28/8/2026. La novena vuelta (rama `feature/dashboard-agro`, commit `53ca4df` en adelante) **los quitó a propósito** y los reemplazó por tres tabs nuevos: **Mapa** (mapa satelital Leaflet con polígonos de lotes y sesiones georreferenciadas), **Resumen por lote** (cuadros informativos por lote) y **Multimedia** (galería/carrusel/tabla de capturas RC). El tab "Resumen" se quedó y se amplió con gráficas ApexCharts y detalle de clientes.

Esto no es un olvido ni una reversión accidental de la Fase 6: es una decisión de producto tomada en esa misma rama, documentada en el mensaje del commit `53ca4df` — el contenido esencial de Sesiones/Pausas ya vivía en el tab Resumen (tabla de sesiones, agregado de pausas), y las tarjetas KPI (`stat-card`) no desaparecieron del catálogo, quedan disponibles para páginas dedicadas futuras si Operaciones las necesita fuera del dashboard.

Este documento **no reescribe la Fase 6** (arriba queda intacta, como registro de lo que se construyó y se decidió en su momento) — esta sección la reemplaza en el dashboard actual. Detalle completo de la novena vuelta (componentes nuevos, convenciones de carga diferida de JS pesado, gotchas de ApexCharts/Leaflet en tabs ocultos, resultado de la auditoría de colores/tipos): `docs/diseno/sistema_diseno_panel.md` §13.

**Pendiente:** esta rama se integró a `develop` sin verificación visual manual en navegador (claro/oscuro) — ver la advertencia en el PR de integración. No cubre regresión visual; queda como trabajo de una tarea de gate visual posterior.

---

## Anexo A — Sincronización de reglas ya confirmadas (login + selección de rol) — NO depende del dashboard, listo para ejecutar en cualquier momento

Esta parte fue pedida aparte ("estos cambios ya se aplicaron... se tiene que actualizar en los agentes, la memoria del proyecto y la documentación"). No requiere ninguna de las fases de arriba — puede ejecutarse primero, sola, en una iteración corta. Contenido ya redactado, listo para pegar:

### A.1 — Agregar a `docs/diseno/sistema_diseno_panel.md`

Agregar como **§8** (después de la §7 "Quinta vuelta" existente, antes de que el archivo termine en la línea 419), con este contenido (seis reglas fijas derivadas de la sesión del 28/8/2026 sobre login/`seleccionar-rol`):

1. **Tipografía display: `font-weight` siempre explícito.** `.ag-login-form__title` no lo declaraba (heredaba el 500 de Bootstrap Reboot); `.ag-role-select__title` sí, pero en `--ag-font-weight-regular` (400) a 2rem — un peldaño más chico/liviano que el login pese a compartir `auth-layout`. Igualados a 38px + `--ag-font-weight-bold` explícito en ambos. Regla: todo consumidor de `--ag-font-family-display` declara `font-weight` con el token, nunca depende del peso por defecto de Bootstrap para headings.
2. **Color de "estado seleccionado" vs. contenido informativo repetido — mismo eje, nunca ámbar para lo segundo.** En `role-card`, se probó ámbar de marca en los chips de permisos (dos intentos, incluyendo también el ícono) y ambos se revirtieron por feedback del usuario. Regla fija: el estado de selección de una tarjeta (ícono, borde) y cualquier chip informativo que se repite fila a fila comparten el mismo eje gris↔verde (nunca ámbar); para no repetir el mismo verde del borde/ícono (`--ag-color-primary`), el chip interno usa el par `success` (`--ag-color-success-strong`/`-subtle` + borde `--ag-color-primary-border-subtle`). El ámbar de marca queda reservado para acentos editoriales puntuales que NO se repiten por fila (badge "ÚLTIMO USADO", link "¿Olvidaste tu contraseña?").
3. **Todo chip/pill lleva borde del mismo tono que su texto** — no solo fondo tenue + texto (se veía "plano, sin relieve" sin él).
4. **Un chip/contenedor de texto sobre un fondo ya tintado (`--ag-color-bg-auth` u otro) necesita borde propio** para no fundirse — el `-subtle` solo no alcanza cuando la superficie base ya tiene color.
5. **Hover de una acción secundaria en texto plano (sin botón/fondo por defecto) necesita fondo sutil + transición**, no solo cambio de color de texto.
6. **Transición nativa entre navegaciones del mismo flujo**: `@view-transition { navigation: auto; }` declarado una sola vez en `app.css` (transversal, no por template), con el bloque `prefers-reduced-motion` correspondiente sobre `::view-transition-*`.

También: agregar la fila faltante `| Molecule | `role-card` | `resources/views/components/molecules/role-card.blade.php` | Implementado (2026-08-28, quinta vuelta) |` a la tabla de catálogo del §3 (línea ~114-118), que hoy no la lista pese a estar mencionada en §7.6.

### A.2 — Agregar a `.claude/agents/design-ui.md`

Nueva responsabilidad (punto 6, después del punto 5 actual "Páginas de sistema"): referenciar `sistema_diseno_panel.md` §8 como reglas fijas de color/tipografía/chips a aplicar por defecto en cualquier componente nuevo, en vez de redescubrirlas por prueba y error en cada pantalla.

### A.3 — Memoria del proyecto

Ya existe `color-estado-seleccion-vs-etiquetas.md` (memoria tipo `feedback`) documentando la regla #2 de arriba — verificar que sigue vigente, no duplicar. Evaluar si conviene una memoria adicional consolidando #1/#3/#4/#5 como checklist de "detalles de pulido UI" (o dejarlas solo en `sistema_diseno_panel.md` §8, que es la fuente de verdad versionada — la memoria de Claude es más volátil/personal, no debería duplicar contenido que ya vive en el repo).

### A.4 — Skill de proyecto nueva

Crear `.claude/skills/panel-design-ui/SKILL.md` (o nombre equivalente) con: dónde están los tokens (`resources/css/tokens/`), puntero a `sistema_diseno_panel.md` como catálogo vigente (nunca `diseno-laravel.md` para valores concretos, solo como checklist genérico), las reglas fijas de A.1, y el procedimiento de verificación visual ya usado en esta sesión (Playwright headless vía `NODE_PATH=<repo>/node_modules node <script>.js`, usuario demo `camila.rojas`/`password`, capturas en ambos temas antes de dar un cambio por cerrado). Objetivo: que una sesión futura de diseño UI cargue esto en vez de releer todo el historial de commits/CSS desde cero.

---

## Referencias

- Commits de contexto: `88fa2a1` (quinta vuelta, layout de 3 niveles), `5bb9d8c` (sexta vuelta, esta sesión, ajustes login/selección de rol).
- `docs/diseno/sistema_diseno_panel.md` §7 (quinta vuelta) — layout de 3 niveles, tokens y contraste ya documentados.
- `docs/diseno/diseno-laravel.md` — checklist genérico de gobernanza, NUNCA fuente de valores concretos (ver nota al inicio de ese archivo).
- `docs/ganadosoft-dashboard.html` — referencia visual puntual (tipografía de sector, tarjeta KPI, donut CSS), paleta descartada por completo.
