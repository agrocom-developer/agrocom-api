<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/input-attributes etapas=2 -->

# Tarea 32 — `atoms/input` no fusiona `$attributes` en su raíz (bug de LSP)

## Por qué

`docs/diseno/guia_pantalla_panel.md` §3 (LSP) es explícito: "todo componente
fusiona `$attributes` en su nodo raíz [...] Sin eso, quien lo consuma pasando
un `data-*`, un `id` o un `aria-*` lo pierde en silencio." `atoms/input`
(`resources/views/components/atoms/input.blade.php`) lo incumple: el `<div>`
raíz (línea 48) no toca `$attributes`, solo el `<input>` interno lo recibe
(línea 71, `{{ $attributes->class(['ag-input__field']) }}`).

La tarea 31 lo encontró al montar `/panel/organizacion`: pasarle
`class="ag-form-section__field--full"` (la utilidad de `form-section` para un
campo de ancho completo, `resources/css/components/form-section.css:30-32`) a
`<x-atoms.input>` no mueve esa clase al hijo directo del grid — el `<div
class="ag-input">` que la necesita. Se resolvió al vuelo envolviendo ese único
campo (`contacto_direccion`) en un `<div>` propio en la página
(`app/Dominios/Seguridad/Infraestructura/Http/Views/pages/organizacion/index.blade.php:113-121`),
documentado como deuda a propósito (`docs/diseno/sistema_diseno_panel.md` §14)
porque el átomo lo consume todo el panel.

Sprint 7 va a construir siete pantallas de formulario (HU-22 a HU-27, HU-45,
ver tareas 33 en adelante) sobre el arquetipo `form-section` de la tarea 31.
Cada una que necesite un campo ancho (dirección, observaciones, textarea)
tropieza con el mismo bug y repite el mismo envoltorio a mano si no se arregla
antes. Por eso va primera, aunque no sea de las tres agentes.

## Qué hacer

Cargá la skill `panel-design-ui` antes de tocar el componente.

1. **Arreglá `atoms/input.blade.php`.** El `<div>` raíz (línea 48) tiene que
   fusionar `$attributes` (la clase de layout que le pasa el llamador,
   `ag-form-section__field--full` o cualquier otra). El `<input>` interno
   (línea 71) sigue necesitando su clase fija `ag-input__field` — pero **no**
   fusiones ahí `$attributes` completo: nadie hoy le pasa al input interno
   nada más que lo que ya cubren los props declarados (`type`, `name`, `id`,
   `value`, `placeholder`, `required`), así que forzar `ag-input__field` como
   clase literal (no derivada de `$attributes`) alcanza. No hay ningún
   componente ya en el catálogo con esta estructura dual (root envolvente +
   control interno real) que ya lo resuelva — no busques un patrón a copiar,
   es la primera vez que se resuelve.
2. **Simplificá `organizacion/index.blade.php`**: sacá el `<div
   class="ag-form-section__field--full">` de las líneas 113-121 y pasá la
   clase directo a `<x-atoms.input>`, mismo criterio que ya usa
   `<x-molecules.file-field class="ag-form-section__field--full" ...>` en la
   línea 81 de la misma vista (que sí fusiona bien porque tiene un único
   elemento raíz).
3. **Actualizá el docblock de `atoms/input.blade.php`** y el hallazgo de
   `docs/diseno/sistema_diseno_panel.md` §14 ("Bug de LSP encontrado...") para
   que digan que está resuelto, no que sigue pendiente.

## Qué NO hacer

- **No toques `atoms/switch.blade.php`.** Tiene la misma estructura (raíz
  `<div class="ag-switch">` sin `$attributes`, línea 38; el `<input
  type="checkbox">` interno sí lo recibe, línea 47) y por lo tanto el mismo
  bug latente — pero ningún consumidor de hoy le pasa una clase de layout al
  componente (a diferencia de `atoms/input`, que ya tiene un caso de uso real
  bloqueado). Mismo criterio de blast radius que documentó la tarea 31:
  arreglalo solo cuando aparezca la necesidad real, no antes. Dejá anotado en
  `sistema_diseno_panel.md` que el bug existe ahí también, para que no haya
  que redescubrirlo.
- No migres `atoms/input` a fusionar `$attributes` completo en el `<input>`
  interno también (duplicar `class` en dos lugares generaría clases repetidas
  o inconsistentes). La clase de layout va al root; el resto de props del
  input los sigue resolviendo sus props declarados.
- No agregues props nuevos al componente para esto (`wrapperClass` o
  similar) — el fix es fusionar `$attributes` donde corresponde, no inventar
  una API paralela.
- No toques ningún otro componente del catálogo ni ninguna otra página fuera
  de `organizacion`.

## Criterio de aceptación

- `./bin/verify` = 0, incluida la etapa de Playwright.
- `tests/Visual/organizacion.spec.ts` sigue pasando en claro y oscuro. Si el
  cambio de markup desplaza un píxel y el snapshot no matchea, regenerá los
  dos (`npx playwright test --update-snapshots`) solo después de confirmar a
  ojo en el navegador que el resultado visual sigue siendo el mismo grid de
  dos columnas — un snapshot que cambia porque el campo `contacto_direccion`
  dejó de ocupar el ancho completo es una regresión, no algo a aceptar.
- `grep -n "ag-form-section__field--full" app/Dominios/Seguridad/Infraestructura/Http/Views/pages/organizacion/index.blade.php`
  encuentra la clase en el componente `<x-atoms.input>`, no en un `<div>`
  envolvente.

## Puede tocar

`resources/views/components/atoms/input.blade.php`,
`app/Dominios/Seguridad/Infraestructura/Http/Views/pages/organizacion/index.blade.php`,
`docs/diseno/sistema_diseno_panel.md`, `tests/Visual/**` (solo snapshots, si
hace falta regenerarlos).
