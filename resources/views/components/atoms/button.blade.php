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
    - icon: nombre de ícono Material Symbols, al inicio del botón.
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
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
        @endif
        <span class="ag-button__label">{{ $slot }}</span>
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class($classes) }}
        @if ($loading) aria-busy="true" disabled @endif
    >
        @if ($loading)
            <span class="ag-button__spinner" aria-hidden="true"></span>
        @elseif ($icon)
            <x-atoms.icon :name="$icon" size="sm" class="ag-button__icon" />
        @endif
        <span class="ag-button__label">{{ $slot }}</span>
    </button>
@endif
