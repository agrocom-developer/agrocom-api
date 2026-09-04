<!-- ciclo: critica=no turno-noche=1 rama=feature/estabilidad-header-panel etapas=2 descongela=tests -->

# Tarea 61 — eliminar el no determinismo de `rendiciones.spec.ts → show`

## Por qué esta tarea

No es una HU ni una TE de `plan_sprints.md`: es deuda técnica concreta,
documentada explícitamente por varias tareas anteriores, con criterio de
aceptación ejecutable — mismo tipo de fila que las tareas 28, 29 y 30.

Con la tarea 60 (badges reales) se regeneraron ~100 snapshots de Playwright
de una sola vez (el sidebar cambió en casi toda pantalla), lo que de paso
volvió a poner en verde los ~30 specs que las tareas 56 a 59 venían
documentando como "drift de fuentes/antialiasing preexistente" — no se
investigaron ni se tocaron a propósito en esas tareas, porque el gate real
de CI/auto-merge (`ci.yml` → `laravel-tests`) no corre Playwright, así que
nunca bloquearon un merge.

Queda un solo rojo, y es de otra naturaleza: `rendiciones.spec.ts → show`
(claro **u** oscuro, alterna cuál de los dos falla, nunca ambos, nunca
ninguno de forma estable) — confirmado en `runs/60.md` con `git stash` de
los cambios de esa tarea: falla exactamente igual sin ellos, así que es
preexistente y no relacionado con badges. El síntoma descrito ahí: la fila
superior completa del panel (breadcrumb, buscador, avatar, notificaciones,
fecha, campaña) aparece corrida horizontalmente unos pocos píxeles — el
sidebar y el cuerpo de la página salen pixel-perfectos, solo la fila del
topbar se desplaza. Es un candidato claro a condición de carrera de
scrollbar (el contenido de esta pantalla en particular puede quedar justo
en el borde de necesitar o no el scroll interno de `.ag-panel__content`,
`overflow-y: auto`), pero **no está confirmado** — es la hipótesis con la
que arranca esta tarea, no el diagnóstico.

## Qué hacer

Cargá el skill `verificacion` antes de empezar (ejecución de `bin/verify`
en este entorno, qué NO se toca para hacerlo pasar) y el skill
`panel-design-ui` si termina siendo un fix de CSS del chrome compartido.

1. **Reproducir de forma confiable antes de tocar nada.** Corré
   `tests/Visual/rendiciones.spec.ts` (el describe `show`, claro y oscuro)
   varias veces seguidas hasta capturar tanto una corrida que pase como una
   que falle — `--repeat-each` o un loop de shell alcanza. Con `trace:
   'retain-on-failure'` ya activo en `playwright.config.ts`, el trace de la
   corrida fallida es la primera fuente de evidencia real, no la hipótesis
   del párrafo anterior.
2. **Confirmar la causa raíz con evidencia**, no dar por buena la hipótesis
   de scrollbar sin comprobarla. Cosas concretas a mirar: si
   `.ag-panel__content` (único contenedor con `overflow-y: auto` en
   `panel-layout.css`) tiene o no una barra de scroll visible en la corrida
   que falla vs. la que pasa; si el ancho de `.ag-topbar` o de
   `document.documentElement.clientWidth` difiere entre ambas capturas
   (`page.evaluate` para medirlo si hace falta); si `fullPage: true` en el
   `expect(page).toHaveScreenshot(...)` de este spec interactúa distinto
   con el layout fijo de `.ag-panel` (`height: 100dvh; overflow: hidden`)
   que en el resto de los specs que usan la misma opción sin flaquear.
3. **Aplicar el fix mínimo**, priorizando corregir el layout real sobre
   maquillar el test: si la causa es que el ancho disponible depende de si
   hay o no scrollbar (un layout que un usuario real también vería
   temblar), corregilo en CSS — es un bug de producción, no solo de test.
   Si la causa es específica de cómo Playwright captura esta pantalla en
   particular (contenido más alto que el resto, borde exacto de un salto de
   línea en una celda de tabla, etc.), el fix puede vivir en el spec
   (esperar a que el layout se asiente, fijar el tamaño de viewport si no
   lo está ya, etc.) — pero solo después de entender por qué, no por
   ensayo y error.
4. **Confirmar determinismo real**: mínimo 5 corridas seguidas de
   `rendiciones.spec.ts → show`, claro y oscuro, todas en verde. Una sola
   corrida en verde no alcanza — es exactamente lo que ya pasó en tareas
   anteriores (alternaba cuál de los dos temas fallaba de una corrida a
   otra).
5. `./bin/verify` completo, para confirmar que el fix no le movió un solo
   píxel a ninguno de los ~100 snapshots que la tarea 60 dejó en verde
   (comparalos contra `runs/60-verify-2.log` si hace falta un baseline).

## Qué NO hacer

- **No regenerar el snapshot de `rendiciones-show` sin haber entendido y
  corregido la causa.** Eso oculta el síntoma, no lo resuelve — es
  exactamente el error que las tareas 56 a 59 evitaron a propósito con este
  mismo rojo, documentándolo en vez de taparlo.
- **No agregar `retries` en `playwright.config.ts`.** `retries: 0` es
  deliberado (ver el docblock del archivo); un test que necesita
  reintentarse para pasar no está arreglado, solo tiene el síntoma
  escondido.
- **No tocar ningún otro snapshot** de los ~100 que la tarea 60 regeneró. Si
  el fix de CSS mueve un píxel de alguna otra pantalla que comparte
  `.ag-topbar`/`.ag-panel`, es señal de que el fix tiene más blast radius
  del que esta tarea puede asumir sola — acotalo hasta que no los toque, o
  documentá por qué el movimiento es correcto y esperado antes de
  regenerarlos.
- **No tocar código de dominio de `Rendiciones`** (controlador, caso de
  uso, modelo) — es un bug de layout/test, no de negocio.
- No ampliar esto a un rediseño del topbar ni a "aprovechar y limpiar" CSS
  no relacionado con el flake.

## Criterio de aceptación

- `tests/Visual/rendiciones.spec.ts`, describe `show`, claro y oscuro, en
  **5 corridas seguidas** sin un solo fallo (`npx playwright test
  tests/Visual/rendiciones.spec.ts --grep show --repeat-each=5` desde el
  host, o el mecanismo equivalente).
- `./bin/verify` = 0, con la etapa de Playwright completa en verde y sin
  ningún snapshot fuera de `rendiciones.spec.ts` modificado.

## Cómo repartir las etapas

- **Etapa 1**: reproducir de forma confiable, diagnosticar con evidencia
  (no solo la hipótesis de este prompt), aplicar el fix.
- **Etapa 2**: confirmar determinismo (5 corridas seguidas) y correr
  `bin/verify` completo antes de cerrar.

Es chica — si la causa se confirma rápido, puede cerrarse entera en la
etapa 1.

## Cierre obligatorio de cada etapa

`runs/61.estado` con una sola palabra (`PARCIAL`/`OK`/`BLOQUEADA`).
`runs/61.md` con la causa raíz encontrada (con evidencia, no solo la
hipótesis de este prompt) y el fix aplicado — o, si quedó `PARCIAL`, qué
falta para la etapa siguiente. Al cerrar con `OK`, `runs/61.pr.md` con
título y cuerpo del PR.

## Commits

Agrupados por función: el commit del diagnóstico/fix por un lado (con el
porqué, no el qué — qué causaba el corrimiento, no "corrige CSS"), y si
hace falta tocar el spec, un commit aparte. Sin trailer `Co-Authored-By`.
