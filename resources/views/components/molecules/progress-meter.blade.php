{{--
    Molecule: progress-meter (tarea 31 — arquetipo formulario, tarjeta
    "Perfil completo" del canvas "Registro de la compañía")
    Porcentaje + barra + checklist de ítems cumplidos/faltantes, para la
    columna lateral pegajosa del formulario. Header `section-head`, mismo
    criterio que `form-section`/`summary-card`.

    Props:
    - title (requerido): rótulo de la tarjeta, ya traducido — se reenvía a
      `section-head`.
    - percent (requerido, int 0-100): porcentaje ya calculado por el
      llamador — este componente no calcula nada.
    - summaryLabel (nullable): texto junto al porcentaje, ya formateado
      (p. ej. "5 de 6 campos").
    - items (default []): checklist, array de `['label' => '...', 'complete' => bool]`.
--}}
@props([
    'title',
    'percent',
    'summaryLabel' => null,
    'items' => [],
])

<div {{ $attributes->class(['ag-progress-meter']) }}>
    {{-- accent fijo en distintivo-1 (18/9/2026, pedido explícito del
        usuario): mismo tono que `__percent`/`__bar-fill` de abajo — esta
        tarjeta es una unidad visual propia, no una sección más de la
        pantalla que la contiene. Antes cae en el verde por defecto de
        `section-head`, que en `ordenes/show.blade.php` coincide por
        casualidad con "Datos de la orden" (el usuario lo notó como "se
        repite desde 0"). Fijo en el componente, no un prop — TODO
        progress-meter comparte el mismo trío de colores. --}}
    <x-molecules.section-head :title="$title" accent="distintivo-1" class="ag-progress-meter__head" />

    <div class="ag-progress-meter__stat">
        <span class="ag-progress-meter__percent">{{ $percent }}%</span>
        @if ($summaryLabel)
            <span class="ag-progress-meter__summary">{{ $summaryLabel }}</span>
        @endif
    </div>

    <div
        class="ag-progress-meter__bar"
        role="progressbar"
        aria-valuenow="{{ $percent }}"
        aria-valuemin="0"
        aria-valuemax="100"
        style="--ag-progress-meter-percent: {{ (int) $percent }}"
    >
        <div class="ag-progress-meter__bar-fill"></div>
    </div>

    @if (count($items))
        <ul class="ag-progress-meter__checklist">
            @foreach ($items as $item)
                @php $complete = $item['complete'] ?? false; @endphp
                <li class="ag-progress-meter__check-item {{ $complete ? 'is-complete' : 'is-pending' }}">
                    <x-atoms.icon :name="$complete ? 'check_circle' : 'error'" size="sm" />
                    {{ $item['label'] }}
                </li>
            @endforeach
        </ul>
    @endif
</div>
