{{--
    Molecule: alert-strip (quinta vuelta — maquetas 4a/5a/5b)
    Franja de aviso en línea del dashboard, dos variantes del lenguaje
    aprobado:
    - "accent": franja ámbar con borde izquierdo de 3px (ventana volable,
      nota de pausas sin causa) — gradiente tenue, esquinas 0/10/10/0.
    - "danger": tarjeta rojiza con borde (sesiones sin captura del RC).

    Sin lógica: texto por slot (ya traducido), acción opcional por slot
    nombrado `action` (quien la pasa decide qué botón es).

    Props:
    - variant ("accent"|"danger", default "accent").
    - icon (nullable): ícono Material Symbols a la izquierda.

    Slots: default (contenido), action (botón/link a la derecha, opcional).
--}}
@props([
    'variant' => 'accent',
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
