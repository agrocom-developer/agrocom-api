{{--
    Molecule: state-transition (19/9/2026)
    La ficha «estado actual → estado destino» que va dentro de un
    `confirm-modal` de cambio de estado: dos `atoms/badge` con el color de
    cada estado y una flecha en medio, para ver antes de confirmar cuál
    cambio se está haciendo (§6.3.4 de docs/diseno/guia_pantalla_panel.md).

    Nació cuando el mismo marcado apareció en tres pantallas (contratos,
    cuadrillas y estadías): un patrón repetido es del catálogo, no de la
    página. Es molecule y no atom porque compone otros dos átomos (`badge`
    e `icon`). NO es el modal ni decide ninguna transición: solo dibuja.
    Tampoco conoce ningún enum ni archivo de idioma — recibe textos y tonos
    ya resueltos por quien lo usa.

    Props:
    - fromLabel, toLabel (requeridos): etiquetas de los dos estados, ya traducidas.
    - fromTone, toTone (default "neutral"): tonos de `atoms/badge`; los mismos
      del badge del listado y del paso de `step-arrow`.
    - label (requerido): nombre accesible del grupo, ya traducido
      (p. ej. «Cambio de estado: de En curso a Finalizada»).
--}}
@props([
    'fromLabel',
    'toLabel',
    'fromTone' => 'neutral',
    'toTone' => 'neutral',
    'label',
])

<div {{ $attributes->class(['ag-state-transition']) }} role="group" aria-label="{{ $label }}">
    <x-atoms.badge :variant="$fromTone">{{ $fromLabel }}</x-atoms.badge>
    <x-atoms.icon name="arrow_forward" size="sm" class="ag-state-transition__arrow" />
    <x-atoms.badge :variant="$toTone">{{ $toLabel }}</x-atoms.badge>
</div>
