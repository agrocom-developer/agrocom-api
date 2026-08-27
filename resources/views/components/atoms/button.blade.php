{{--
    Atom: button
    Sin lógica de negocio ni wiring a ningún caso de uso: quien lo consume
    decide la acción (atributo `type`, `href`, o los `wire:click`/`onclick`
    que agregue vía `$attributes`, que este átomo respeta y no pisa).

    Props:
    - variant: primary|accent|outline|text|danger (default "primary").
    - size: sm|md|lg (default "md").
    - type: button|submit|reset (default "button"), ignorado si se pasa `href`.
    - href: si se pasa, renderiza <a> en vez de <button>.
    - icon: nombre de ícono Material Symbols.
    - iconPosition: "start" (default, ícono antes del texto — comportamiento
      de siempre, sin cambios para ningún consumidor existente) | "end"
      (ícono después del texto — p. ej. "Iniciar sesión" + `arrow_forward`
      al final, rediseño de login HU-02 tercera vuelta). No cambia CSS: el
      `gap` del flex de `.ag-button` ya se aplica sea cual sea el orden de
      los hijos, así que alcanza con invertir el orden de render en Blade.
    - loading (bool): reemplaza el ícono por un spinner y deshabilita el botón
      (solo estado visual — el llamador decide cuándo está en `loading`).
    - block (bool): ocupa el 100% del ancho disponible.
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'start',
    'loading' => false,
    'block' => false,
])

@php
    $classes = [
        'ag-button',
        "ag-button--{$variant}",
        "ag-button--{$size}",
        $block ? 'ag-button--block' : '',
    ];
    $iconAtEnd = $iconPosition === 'end';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($iconAtEnd)
            <span class="ag-button__label">{{ $slot }}</span>
            @if ($icon)
                <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
            @endif
        @else
            @if ($icon)
                <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
            @endif
            <span class="ag-button__label">{{ $slot }}</span>
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class($classes) }}
        @if ($loading) aria-busy="true" disabled @endif
    >
        @if ($iconAtEnd)
            <span class="ag-button__label">{{ $slot }}</span>
            @if ($loading)
                <span class="ag-button__spinner" aria-hidden="true"></span>
            @elseif ($icon)
                <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
            @endif
        @else
            @if ($loading)
                <span class="ag-button__spinner" aria-hidden="true"></span>
            @elseif ($icon)
                <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
            @endif
            <span class="ag-button__label">{{ $slot }}</span>
        @endif
    </button>
@endif
