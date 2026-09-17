{{--
    Molecule: stat-card (quinta vuelta — tarjeta KPI de las maquetas
    4a/5a/5b; sexta vuelta parte 2 — ícono en contenedor + `state`,
    lenguaje visual de docs/ganadosoft-dashboard.html §2.2): rótulo
    uppercase + ícono en contenedor 40×40 a la derecha (17/9/2026: subido
    de 34×34/ícono `sm` a 40×40/ícono `md` — se veía chico apenas se
    empezó a usar `state` de verdad, primer consumidor real
    `ordenes/show.blade.php`; sin cambios para los consumidores existentes,
    solo más grande), cifra grande en la
    cifra grande en sans + tabular-nums (auditoría visual externa, obs. #7 —
    ya no la fuente display, ver stat-card.css §.ag-stat-card__value; puede
    llevar un sufijo muted, "ha" o "/ 48"),
    y una línea de pie con ícono y tono semántico (éxito/aviso/muted).

    Sin lógica de negocio: no calcula ni formatea nada — el llamador pasa
    todo ya formateado por el llamador (los casos de uso de cada módulo, no
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
    - state (success|warning|danger|info|null, default null): tono del
      CONTENEDOR del ícono — independiente de footTone. Con cualquier valor
      (17/9/2026: antes solo warning/danger, corregido — varias tarjetas de
      la misma fila con `state` distinto y solo algunas con barra se leía
      como inconsistencia, no como jerarquía) pinta también una barra
      izquierda de 4px del mismo color; sin `state` (`null`, default) la
      tarjeta queda neutra y sin barra (no todo KPI necesita un color).
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
    'state' => null,
    'hero' => false,
])

<div
    {{ $attributes->class(['ag-stat-card', $hero ? 'ag-stat-card--hero' : '']) }}
    @if ($state) data-state="{{ $state }}" @endif
>
    <div class="ag-stat-card__head">
        <span class="ag-stat-card__label">{{ $label }}</span>
        @if ($icon)
            <span class="ag-stat-card__icon-box">
                <x-atoms.icon :name="$icon" size="md" class="ag-stat-card__icon" />
            </span>
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
