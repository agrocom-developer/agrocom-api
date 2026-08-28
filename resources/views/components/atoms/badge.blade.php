{{--
    Atom: badge
    Chip/pill de estado (quinta vuelta — chips de la tabla de sesiones de
    las maquetas 4a/5a/5b). Sin lógica de negocio ni texto propio: el
    contenido va por el slot, ya traducido por quien lo consume (mismo
    criterio que `atoms/button`).

    Cada variante resuelve fondo `-subtle` + texto `-strong` de su estado
    (par verificado AA 4.5:1 en ambos temas — sistema_diseno_panel.md §1.3):
    nunca el token de estado crudo como texto (amarillo sobre amarillo
    pálido ronda 1.4:1) ni `--ag-color-text` genérico (el chip perdería el
    color del estado, que en las maquetas ES el texto). El punto de color de
    la versión anterior desaparece: el texto tintado ya comunica el estado.

    Props:
    - variant: success|warning|info|danger|neutral|accent (default
      "neutral"). Mismo set que consumen los estados de sesión del
      dashboard.
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
