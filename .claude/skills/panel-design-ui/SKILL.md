---
name: panel-design-ui
description: Contexto de diseño visual del panel de agrocom-api (tokens, catálogo, reglas de pulido ya confirmadas, verificación visual). Usar antes de tocar CSS/Blade del panel — sidebar, header, tarjetas, formularios, tema claro/oscuro — para no releer todo el historial de commits en cada iteración.
---

# Diseño visual del panel — agrocom-api

Este skill es un mapa de dónde vive el sistema de diseño y qué reglas ya están validadas con el usuario. No reemplaza los documentos fuente — apunta a ellos.

## Dónde está cada cosa

- **Tokens CSS**: `resources/css/tokens/`. Dos capas: `primitives/` (hex, nunca usados directo por componentes) y `semantic/theme-light.css` / `theme-dark.css` (lo que de verdad referencian los componentes, vía `--ag-color-*`, `--ag-space-*`, `--ag-radius-*`, etc.). El tema activo se resuelve reasignando estos tokens con `[data-bs-theme="light"|"dark"]` en `<html>` — nunca duplicando una regla de componente por tema.
- **Catálogo vigente de tokens y componentes**: `docs/diseno/sistema_diseno_panel.md`. Es la fuente de verdad de VALORES concretos (qué token existe, qué componente está implementado, en qué archivo). Se actualiza cada vez que cambia el catálogo.
- **Checklist genérico de gobernanza**: `docs/diseno/diseno-laravel.md`. Es un checklist de proceso (Atomic Design, cero hardcode, patrón template+content) — **nunca** la fuente de valores concretos de ejemplo que trae ese archivo.
- **ADR de arquitectura del panel**: `docs/decisiones/0002-panel-web-adminlte-livewire-atomic-design.md` (Bootstrap/AdminLTE + Material + Atomic Design). No se reabre sin un ADR nuevo.
- **Layout de 3 niveles** (riel de módulos + sidebar del módulo + header): `docs/diseno/sistema_diseno_panel.md` §7 y `docs/gestion/plan_dashboard_rediseno.md` §1.1 para el estado exacto del código.

## Reglas fijas de pulido UI (no redescubrir por prueba y error)

Documentadas completas en `docs/diseno/sistema_diseno_panel.md` §8. Resumen:

1. Todo consumidor de `--ag-font-family-display` declara `font-weight` explícito con un token — nunca hereda el peso por defecto de Bootstrap para headings.
2. Estado de selección (borde/ícono de una tarjeta) y chips informativos que se repiten fila a fila comparten el eje gris↔verde — **nunca ámbar** para lo segundo. El ámbar de marca es solo para acentos editoriales puntuales que no se repiten por fila (ver memoria `color-estado-seleccion-vs-etiquetas`).
3. Todo chip/pill lleva borde del mismo tono que su texto, no solo fondo tenue + texto.
4. Un chip/contenedor sobre un fondo ya tintado necesita borde propio — el `-subtle` solo no alcanza cuando la superficie base ya tiene color.
5. Hover de una acción secundaria en texto plano necesita fondo sutil + transición, no solo cambio de color de texto.
6. Transición nativa entre navegaciones del mismo flujo: `@view-transition { navigation: auto; }` una sola vez en `app.css`, con `prefers-reduced-motion` sobre `::view-transition-*`.

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
2. Loguear con el usuario demo multirol: `camila.rojas` / `password` (seeded por `Demo/PanelDemoSeeder`). **Los datos demo de la base del compose no se borran nunca** — ver el skill [verificacion].
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
