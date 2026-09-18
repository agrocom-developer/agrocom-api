{{--
    Atom: badge
    Chip/pill de estado (quinta vuelta — chips de la tabla de sesiones de
    las maquetas 4a/5a/5b). Sin lógica de negocio ni texto propio: el
    contenido va por el slot, ya traducido por quien lo consume (mismo
    criterio que `atoms/button`).

    success|warning|info|danger|distintivo-1|distintivo-2|distintivo-3|
    primary-2|alert resuelven relleno SÓLIDO (`-contrast-fill`, constante
    entre temas) + texto blanco, salvo warning que usa texto oscuro (ver
    badge.css — blanco sobre el ámbar no pasa AA). neutral|accent siguen
    con el par tenue `-subtle` + texto `-strong` de siempre. Contraste de
    cada variante verificado en sistema_diseno_panel.md §1.3.

    Props:
    - variant: success|warning|info|danger|distintivo-1|distintivo-2|
      distintivo-3|primary-2|alert|neutral|accent (default "neutral").
    - icon (nullable): ícono Material Symbols al inicio del pill.
--}}
@props([
    'variant' => 'neutral',
    'icon' => null,
])

<span {{ $attributes->class(['ag-badge', "ag-badge--{$variant}"]) }}>
    @if ($icon)
        <x-atoms.icon :name="$icon" size="sm" class="ag-badge__icon" />
    @endif

    <span class="ag-badge__label">{{ $slot }}</span>
</span>
