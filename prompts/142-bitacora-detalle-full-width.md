<!-- ciclo: critica=no turno-noche=1 rama=feature/bitacora-detalle-filas etapas=2 -->

# Tarea 142 — el detalle de la bitácora se expande como columna, no como fila

## Qué hacer

Cargá el skill `verificacion` y, si hace falta repasar el patrón de listado en
grid (`role="table"/"row"/"cell"`), `panel-design-ui`.

En `/panel/bitacora`
(`app/Dominios/Seguridad/Infraestructura/Http/Views/pages/bitacora/index.blade.php`),
cada fila (`.ag-bitacora__fila`, `resources/css/pages/bitacora.css:28-35`) es
un CSS grid de 6 columnas (`grid-template-columns: 3rem 2fr 1.5fr 1.2fr 0.8fr
auto`). El toggle "Ver detalle" (`index.blade.php:162-188`) es un
`<details>/<summary>` metido DENTRO de la última celda (`detalle`, ancho
`auto`): al abrirse, la tabla `CAMPO/ANTES/DESPUÉS` (`.ag-bitacora__diff`,
`bitacora.css:107-145`) crece confinada a esa sola columna, que se ensancha
para darle lugar — es lo que deforma la fila y descuadra la cabecera contra el
resto de las filas cerradas (capturado por el dueño el 23/9/2026).

El resultado esperado: al expandir, la tabla de cambios pasa a ser una
**sub-fila propia, debajo de la fila base, ocupando el ancho completo** de
`.ag-bitacora__tabla` — no una columna que crece. Las demás filas (abiertas o
cerradas) no deben moverse ni cambiar de ancho cuando una se expande.

Técnica sugerida (no es la única válida, pero encaja con el grid que ya
existe): cada `.ag-bitacora__fila` ES su propio contenedor grid — no
comparte columnas con las demás filas — así que un hijo con `grid-column: 1 /
-1` dentro de esa misma fila cae en una línea nueva que ocupa todo su ancho,
sin tocar el layout de ninguna otra fila. Falta sacar la tabla de cambios de
adentro de la celda `detalle` y ponerla como hijo directo de `.ag-bitacora__fila`
(hermano de las 6 celdas), dejando en la celda `detalle` solo el disparador.
Si usás `<details>` para eso, ojo con separar el `<summary>` (que tiene que
seguir viéndose en la celda `detalle`) del contenido revelado (que tiene que
expandirse a ancho completo) — son children del mismo `<details>`, así que
puede hacer falta `display: contents` en el `<details>` para que sus hijos
caigan directo como ítems del grid de la fila, o replantear el marcado. Lo que
no puede perderse es que el toggle siga siendo operable por teclado (Enter/
Espacio), no solo por click.

Contemplá el breakpoint `@media (max-width: 992px)` (`bitacora.css:154-169`),
donde la fila ya colapsa a una sola columna — ahí probablemente no haga falta
ningún cambio porque el detalle ya ocupa todo el ancho, pero confirmalo.

## Cómo repartir las etapas

- Etapa 1: reestructurar el marcado y el CSS, con las dos capturas (abierto/
  cerrado) verificadas a mano en el compose.
- Etapa 2: el script Playwright del criterio de aceptación y el ajuste fino
  que salga de correrlo.

## Qué NO hacer

- No toques `usuarios/index.blade.php` ni ningún otro listado — el pedido es
  puntual de bitácora, aunque el patrón de grid se repita en otras pantallas.
- No conviertas el listado a una tabla HTML real (`<table>` con `<tr>`): el
  resto del panel usa el patrón `role="table"` con CSS grid a propósito
  (`panel-design-ui`), y cambiarlo acá lo desalinea del resto.
- No hardcodees ningún color nuevo (invariante 11) — la tabla de cambios ya
  usa tokens (`bitacora.css:107-145`); si necesitás uno nuevo para el fondo de
  la sub-fila, que sea un token existente.
- No toques `BitacoraController`, `ListarBitacora` ni `FilaBitacora` — el dato
  ya llega bien, esto es puro marcado y CSS de la vista.

## Criterio de aceptación

`./bin/verify` = 0, más un script Playwright (`runs/142-navegador.cjs`,
guardá las capturas con ruta absoluta al scratchpad) que:

- entra a `/panel/bitacora` con al menos una fila con diferencias,
- mide con `boundingBox()` el ancho de `.ag-bitacora__tabla` (o
  `.ag-bitacora__fila` correspondiente) ANTES de expandir,
- abre "Ver detalle" con `mouse.click()` sobre el disparador (no
  `locator.click()`, que puede enmascarar el problema real — ver la nota de
  la tarea de verificación de overflow),
- confirma que la sub-fila revelada tiene un ancho igual (o prácticamente
  igual, con el padding de la tarjeta) al de `.ag-bitacora__tabla`, y que la
  fila base y la cabecera **no cambiaron de ancho ni de posición** al abrirse,
- repite el chequeo en viewport de escritorio y en uno ≤992px,
- confirma que el toggle es operable con teclado (foco + Enter) además de con
  el mouse.

## Cierre obligatorio de cada etapa

`runs/142.estado` con una sola palabra — `PARCIAL` si avanzaste y commiteaste
pero la tarea sigue abierta, `OK` recién cuando está entera. `runs/142.md` con
qué se hizo y qué falta. Al cerrar con `OK`, `runs/142.pr.md` con el título del
PR en la primera línea y el cuerpo debajo.

## Commits

Agrupados por función (marcado, CSS, script de verificación), en español,
imperativo, explicando el porqué. Sin trailer `Co-Authored-By`.
