{{--
    Atom: badge
    Pill de estado (docs/diseno/sistema_diseno_panel.md §3). Sin lógica de
    negocio ni texto propio: el contenido va por el slot, ya traducido por
    quien lo consume (mismo criterio que `atoms/button`, que tampoco tiene
    una prop `label` — el texto es el slot).

    Set de variantes de color, todas resueltas contra el fondo `-subtle`
    correspondiente + `--ag-color-text` (o `--ag-color-accent-contrast` para
    "accent", que ya es el token que el resto del sistema usa como texto
    seguro sobre `--ag-color-accent-subtle` — ver `.ag-role-badge` en
    topbar.css y `.ag-menu-item__badge` en menu-item.css): usar el token de
    estado crudo (`--ag-color-warning`, por ejemplo) como COLOR DE TEXTO
    sobre su propio `-subtle` falla contraste en varios casos (amarillo
    sobre amarillo pálido es el peor: ratio ~1.4:1, muy por debajo de AA) —
    por eso el texto de las variantes de estado es siempre `--ag-color-text`
    (que sí pasa AA/AAA contra cualquier `-subtle`, en los dos temas: en
    claro el `-subtle` es un tinte pálido y el texto es oscuro; en oscuro el
    `-subtle` es un overlay de baja opacidad sobre una superficie oscura y el
    texto es claro). El color de marca de cada estado no se pierde: lo lleva
    el punto (`__dot`) o el ícono, elementos decorativos no-texto (exigencia
    de contraste más laxa, 3:1, y aun así secundarios a la etiqueta de texto,
    que ya comunica el estado por sí sola).

    Props:
    - variant: success|warning|info|danger|neutral|accent (default
      "neutral"). Mismo set que consume `molecules/stat-card`.
    - icon (nullable): ícono Material Symbols al inicio del pill. Si no se
      pasa icon Y la variante es de estado (success/warning/info/danger), se
      renderiza un punto de color en su lugar (indicador liviano). Las
      variantes "neutral"/"accent" no llevan punto (ya se distinguen por el
      tinte de fondo).
--}}
@props([
    'variant' => 'neutral',
    'icon' => null,
])

@php
    $estadoVariants = ['success', 'warning', 'info', 'danger'];
    $showDot = ! $icon && in_array($variant, $estadoVariants, true);
@endphp

<span {{ $attributes->class(['ag-badge', "ag-badge--{$variant}"]) }}>
    @if ($icon)
        <x-atoms.icon :name="$icon" size="sm" class="ag-badge__icon" />
    @elseif ($showDot)
        <span class="ag-badge__dot" aria-hidden="true"></span>
    @endif

    <span class="ag-badge__label">{{ $slot }}</span>
</span>
