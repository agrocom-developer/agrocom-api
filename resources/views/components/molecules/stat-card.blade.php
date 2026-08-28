{{--
    Molecule: stat-card (quinta vuelta — tarjeta KPI de las maquetas
    4a/5a/5b): rótulo uppercase + ícono a la derecha, cifra grande en la
    fuente display (Fraunces — puede llevar un sufijo muted, "ha" o "/ 48"),
    y una línea de pie con ícono y tono semántico (éxito/aviso/muted).

    Sin lógica de negocio: no calcula ni formatea nada — el llamador pasa
    todo ya formateado (DatosDemoPanel mientras los módulos reales no
    existan).

    Props:
    - label (requerido): rótulo uppercase, ya traducido/resuelto.
    - icon (nullable): ícono Material Symbols junto al rótulo.
    - value (requerido): cifra, ya formateada.
    - valueSuffix (nullable): sufijo muted junto a la cifra ("ha", "/ 48").
    - foot (nullable): línea de pie, ya formateada.
    - footIcon (nullable): ícono de la línea de pie.
    - footTone (success|warning|muted, default "muted"): color del pie —
      tono semántico independiente del signo (una baja de costo es éxito).
    - hero (bool, default false): variante protagonista del móvil (maqueta
      5b — cifra 40px). El grid/columna lo decide el llamador.
--}}
@props([
    'label',
    'icon' => null,
    'value',
    'valueSuffix' => null,
    'foot' => null,
    'footIcon' => null,
    'footTone' => 'muted',
    'hero' => false,
])

<div {{ $attributes->class(['ag-stat-card', $hero ? 'ag-stat-card--hero' : '']) }}>
    <div class="ag-stat-card__head">
        <span class="ag-stat-card__label">{{ $label }}</span>
        @if ($icon)
            <x-atoms.icon :name="$icon" size="sm" class="ag-stat-card__icon" />
        @endif
    </div>

    <p class="ag-stat-card__value">
        {{ $value }}@if ($valueSuffix)<span class="ag-stat-card__value-suffix"> {{ $valueSuffix }}</span>@endif
    </p>

    @if ($foot !== null)
        <p class="ag-stat-card__foot ag-stat-card__foot--{{ $footTone }}">
            @if ($footIcon)
                <x-atoms.icon :name="$footIcon" size="sm" class="ag-stat-card__foot-icon" />
            @endif
            <span>{{ $foot }}</span>
        </p>
    @endif
</div>
