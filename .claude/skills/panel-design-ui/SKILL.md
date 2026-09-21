---
name: panel-design-ui
description: Contexto de diseño visual del panel de agrocom-api (tokens, catálogo, reglas de pulido ya confirmadas, verificación visual). Usar antes de tocar CSS/Blade del panel — sidebar, header, tarjetas, formularios, tema claro/oscuro — para no releer todo el historial de commits en cada iteración.
---

# Diseño visual del panel — agrocom-api

Este skill es un mapa de dónde vive el sistema de diseño y qué reglas ya están validadas con el usuario. No reemplaza los documentos fuente — apunta a ellos.

## Dónde está cada cosa

- **Receta para construir una pantalla nueva**: `docs/diseno/guia_pantalla_panel.md`. Empezá por acá si vas a armar una pantalla — dónde va cada archivo, anatomía de los tres arquetipos (tablero / listado / formulario), traducción de un mockup a tokens y el checklist de cierre. Los otros dos documentos de `docs/diseno/` son de consulta, no de receta.
- **Tokens CSS**: `resources/css/tokens/`. Dos capas: `primitives/` (hex, nunca usados directo por componentes) y `semantic/theme-light.css` / `theme-dark.css` (lo que de verdad referencian los componentes, vía `--ag-color-*`, `--ag-space-*`, `--ag-radius-*`, etc.). El tema activo se resuelve reasignando estos tokens con `[data-bs-theme="light"|"dark"]` en `<html>` — nunca duplicando una regla de componente por tema.
- **Catálogo vigente de tokens y componentes**: `docs/diseno/sistema_diseno_panel.md`. Es la fuente de verdad de VALORES concretos (qué token existe, qué componente está implementado, en qué archivo). Se actualiza cada vez que cambia el catálogo.
- **Checklist genérico de gobernanza**: `docs/diseno/diseno-laravel.md`. Es un checklist de proceso (Atomic Design, cero hardcode, patrón template+content) — **nunca** la fuente de valores concretos de ejemplo que trae ese archivo.
- **ADR de arquitectura del panel**: `docs/decisiones/0002-panel-web-adminlte-livewire-atomic-design.md` (Bootstrap/AdminLTE + Material + Atomic Design). No se reabre sin un ADR nuevo.
- **Layout de 3 niveles** (riel de módulos + sidebar del módulo + header): `docs/diseno/sistema_diseno_panel.md` §7 y `docs/gestion/plan_dashboard_rediseno.md` §1.1 para el estado exacto del código.

## Homogeneización en curso (desde el 20/9/2026)

Los listados y formularios se están llevando al patrón de las pantallas de referencia (Campaña, Cliente, Propiedad, Lote, Contrato, Orden de aplicación, Orden de trabajo, Estadías, Cuadrillas). El alcance y las reglas están en `docs/gestion/plan_homogeneizacion_panel.md`; lo que falta, en `docs/diseno/panel_homogeneo_pendientes.txt`. `tests/Unit/PanelHomogeneoTest.php` (parte de `bin/verify`) exige el patrón a toda pantalla que no figure en esa lista: al terminar una, sácala de ahí; nunca sumes una para que el test pase.

## Reglas fijas de pulido UI (no redescubrir por prueba y error)

Documentadas completas en `docs/diseno/sistema_diseno_panel.md` §8. Resumen:

1. Todo consumidor de `--ag-font-family-display` declara `font-weight` explícito con un token — nunca hereda el peso por defecto de Bootstrap para headings.
2. Estado de selección (borde/ícono de una tarjeta) y chips informativos que se repiten fila a fila comparten el eje gris↔verde — **nunca ámbar** para lo segundo. El ámbar de marca es solo para acentos editoriales puntuales que no se repiten por fila (ver memoria `color-estado-seleccion-vs-etiquetas`).
3. Todo chip/pill lleva borde del mismo tono que su texto, no solo fondo tenue + texto.
4. Un chip/contenedor sobre un fondo ya tintado necesita borde propio — el `-subtle` solo no alcanza cuando la superficie base ya tiene color.
5. Hover de una acción secundaria en texto plano necesita fondo sutil + transición, no solo cambio de color de texto.
6. Transición nativa entre navegaciones del mismo flujo: `@view-transition { navigation: auto; }` una sola vez en `app.css`, con `prefers-reduced-motion` sobre `::view-transition-*`.
7. En una fila de controles (barra de filtros, formulario horizontal) los
   átomos de campo van con `margin-bottom: 0` y todos con el mismo
   `min-width` — nombrando a los siete (`.ag-input`, `.ag-select`,
   `.ag-textarea`, `.ag-date`, `.ag-time-range`, `.ag-switch`,
   `.ag-checkbox`), no solo al que la pantalla use hoy. El margen de apilado alinea el botón contra un
   borde fantasma 16px más abajo.

8. Sobre un relleno de estado (`-contrast-fill`: badge, paso actual de
   `step-arrow`, ícono de `link-row`/`stat-card`) el texto y el ícono son
   SIEMPRE blancos (`--ag-color-gray-0`), **también en `warning`**. La
   excepción de texto oscuro sobre el ámbar se retiró el 19/9/2026 por
   decisión del dueño; no reintroducirla «por contraste» (el costo está
   anotado en `sistema_diseno_panel.md` §1.3).

## Explorar diseño antes de implementarlo

Cuando lo que hace falta es **decidir cómo se ve algo** —una pantalla nueva, un
rediseño, comparar dos direcciones visuales— conviene explorarlo antes de
escribir Blade y CSS. Para eso están las skills de diseño del entorno, que la
sesión principal puede invocar (los subagentes no: no tienen la herramienta
`Skill`):

| Skill | Para qué |
|---|---|
| `design` | Canvas de varios artboards en un Artifact: mockups de pantalla, flujos, variantes lado a lado. Es lo que reemplaza al mockup HTML suelto (`docs/ganadosoft-dashboard.html`, `Login Agro Drones.dc.html`) que se usó hasta ahora |
| `impeccable` | Revisión y pulido de una interfaz existente: jerarquía visual, carga cognitiva, accesibilidad, estados vacíos y de error |
| `emil-design-eng` | Detalles de interacción y animación — el pulido fino de un componente |
| `design-taste-frontend` | Dirección de diseño de cero, cuando no hay referencia y hay que proponer una |

Dos límites que no cambian por usar estas skills:

- **Del mockup se toma estructura y medidas, nunca el vocabulario ni la paleta.**
  Los módulos salen de la especificación y los colores de los tokens `--ag-color-*`
  derivados de los logos oficiales. Es la regla que ya se aplicó al mockup
  ganadosoft y al de login (ver memoria `maquetas-fijan-layout-no-vocabulario`).
- **Un canvas no es la implementación.** Lo aprobado se traduce a componentes
  Atomic Design con tokens; nada de HTML del mockup se copia literal.

## Verificación visual (obligatoria antes de cerrar un cambio)

El cálculo de contraste en papel no alcanza — hay que ver el resultado real en navegador, en ambos temas, antes de dar una fase por cerrada (checklist de `docs/diseno/diseno-laravel.md` §11 + `docs/gestion/plan_dashboard_rediseno.md` §5).

Procedimiento usado en la sesión del 28/8/2026 (login + selección de rol):

1. Levantar el panel local (`php artisan serve` o el server ya corriendo en `localhost:8000`).
2. Loguear con el único usuario sembrado en `local`/`staging`: `miguelo` / `0000` (`AdminPlataformaSeeder`, tarea 100 — la familia `Demo/` con `carlos.ferrufino` se retiró; el resto de los datos se carga a mano desde el panel). **Los datos demo de la base del compose no se borran nunca** — ver el skill [verificacion].
3. Correr un script Playwright headless con el binario ya instalado como dependencia del repo (no hace falta `npm install -g` ni un nuevo `package.json`):
   ```
   NODE_PATH=<repo>/node_modules node <script>.js
   ```
   El script navega a la vista en cuestión, toma captura en tema claro y en tema oscuro (alternando `data-bs-theme` o el toggle real de la UI), y guarda ambas imágenes para revisión.
4. Revisar las capturas antes de reportar la fase como terminada — especialmente contraste en tema oscuro, que es donde este panel tiende a fallar (ver `docs/gestion/plan_dashboard_rediseno.md` §3).

No hay un script committeado todavía (los usados hasta ahora fueron ad-hoc en el scratchpad de la sesión) — escribir uno nuevo por vista es aceptable mientras siga este patrón.

## Qué NO hacer

- No copiar literal ningún hex de una referencia visual externa (p. ej. `docs/ganadosoft-dashboard.html`) — traducir siempre a los tokens `--ag-*` existentes.
- No asumir el prop/shape de un componente existente (`stat-card`, `alert-strip`, etc.) sin leer el `.blade.php` real primero.
- No crear un componente nuevo del catálogo sin verificar antes si ya existe una composición equivalente (`design-ui` es quien decide si algo es atom/molecule/organism nuevo).
