{{--
    Molecule: alert-strip (quinta vuelta — maquetas 4a/5a/5b; sexta vuelta
    parte 2 — feedback directo del 28/8/2026: las 4 variantes semánticas
    estándar del catálogo, TODAS con la misma anatomía (gradiente + borde
    izquierdo 3px + esquinas 0/10/10/0), solo cambia el tono — antes solo
    existían "accent" (ámbar) y "danger" (recuadro completo, distinto del
    resto). "accent" se renombró a "warning" (es lo que siempre fue
    visualmente) y se agregaron "success"/"info".

    Props:
    - variant (success|warning|info|danger, default "warning").
    - icon (nullable): ícono Material Symbols a la izquierda.

    Slots: default (contenido), action (botón/link a la derecha, opcional).
--}}
@props([
    'variant' => 'warning',
    'icon' => null,
])

<div {{ $attributes->class(['ag-alert-strip', "ag-alert-strip--{$variant}"]) }}>
    @if ($icon)
        <x-atoms.icon :name="$icon" size="sm" class="ag-alert-strip__icon" />
    @endif

    <div class="ag-alert-strip__body">{{ $slot }}</div>

    @isset($action)
        <div class="ag-alert-strip__action">{{ $action }}</div>
    @endisset
</div>
