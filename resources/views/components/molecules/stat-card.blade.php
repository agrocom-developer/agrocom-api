{{--
    Molecule: stat-card
    Tarjeta de métrica para dashboards: ícono + cifra grande + label + acento
    de color + tendencia opcional. Compone `atoms/icon` (dos veces: chip
    circular y flecha de tendencia) — no orquesta otras moléculas, por eso es
    molecule y no organism. Pensada para grillas de 4-5 tarjetas por
    pantalla; el grid en sí (columnas responsivas) es Bootstrap y lo arma
    quien componga el dashboard, esta molécula no se envuelve a sí misma en
    un contenedor de grid.

    Sin lógica de negocio: no calcula ni formatea la cifra ni el delta — el
    llamador pasa `value`/`trend` ya formateados (miles, decimales, moneda,
    unidad, signo).

    Props:
    - icon (nullable): ícono Material Symbols para el chip circular.
    - value (requerido): cifra grande, ya formateada. Tipografía monoespaciada
      (`--ag-font-family-mono`, cifras tabulares — mismo criterio que el
      resto del sistema para hectáreas/montos/códigos, catálogo §2).
    - label (requerido): texto ya traducido bajo la cifra.
    - variant: success|warning|info|danger|neutral|accent (default
      "neutral") — mismo set que `atoms/badge`. Colorea la barra superior
      (color de marca crudo, decorativo) y el chip de ícono (fondo `-subtle`
      + texto `--ag-color-text`/`--ag-color-accent-contrast`, mismo criterio
      de contraste que badge — ver su docblock).
    - trend (nullable): texto de tendencia ya formateado (p. ej. "+12%").
    - trendDirection: up|down|neutral (default "neutral") — decide el ícono
      (trending_up/trending_down/trending_flat) y el color del texto de
      tendencia (éxito/peligro/muted). Independiente de `variant`: el acento
      de la tarjeta y la dirección de la tendencia son dos ejes distintos
      (una tarjeta "accent" puede tener una tendencia a la baja).
--}}
@props([
    'icon' => null,
    'value',
    'label',
    'variant' => 'neutral',
    'trend' => null,
    'trendDirection' => 'neutral',
])

@php
    $trendIcon = match ($trendDirection) {
        'up' => 'trending_up',
        'down' => 'trending_down',
        default => 'trending_flat',
    };
@endphp

<div {{ $attributes->class(['ag-stat-card', "ag-stat-card--{$variant}"]) }}>
    <span class="ag-stat-card__bar" aria-hidden="true"></span>

    @if ($icon)
        <span class="ag-stat-card__icon" aria-hidden="true">
            <x-atoms.icon :name="$icon" size="md" />
        </span>
    @endif

    <p class="ag-stat-card__value">{{ $value }}</p>
    <p class="ag-stat-card__label">{{ $label }}</p>

    @if ($trend !== null)
        <p class="ag-stat-card__trend ag-stat-card__trend--{{ $trendDirection }}">
            <x-atoms.icon :name="$trendIcon" size="sm" class="ag-stat-card__trend-icon" />
            <span>{{ $trend }}</span>
        </p>
    @endif
</div>
