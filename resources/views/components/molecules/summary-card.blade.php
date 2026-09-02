{{--
    Molecule: summary-card (tarea 31 — arquetipo formulario, tarjeta
    "Suscripción" del canvas "Registro de la compañía")
    Lista etiqueta→valor de solo lectura + acción opcional al pie, para la
    columna lateral pegajosa del formulario (metadatos que no se editan acá:
    plan vigente, cupos, estado). Header `section-head`, mismo criterio que
    `form-section` — compone esa única molécula, sin lógica propia.

    Props:
    - title (requerido): rótulo de la tarjeta, ya traducido — se reenvía a
      `section-head`.
    - items (requerido): array de filas
      `['label' => '...', 'value' => '...', 'mono' => bool, 'badge' => bool, 'variant' => 'success']`.
      `label`/`value` ya traducidos/formateados por el llamador. `mono`
      alinea el valor en `--ag-font-family-mono` (fechas, cupos "6 / 10").
      `badge` renderiza el valor como `atoms/badge` (variant = `variant`,
      default "neutral") en vez de texto plano — el caso "Estado: Vigente".

    Slot con nombre:
    - action: botón al pie (`atoms/button`), p. ej. "Ver facturación".
--}}
@props([
    'title',
    'items' => [],
])

<div {{ $attributes->class(['ag-summary-card']) }}>
    <x-molecules.section-head :title="$title" class="ag-summary-card__head" />

    <dl class="ag-summary-card__list">
        @foreach ($items as $item)
            <div class="ag-summary-card__row">
                <dt class="ag-summary-card__label">{{ $item['label'] }}</dt>
                <dd class="ag-summary-card__value">
                    @if ($item['badge'] ?? false)
                        <x-atoms.badge :variant="$item['variant'] ?? 'neutral'">{{ $item['value'] }}</x-atoms.badge>
                    @else
                        <span class="{{ ($item['mono'] ?? false) ? 'ag-summary-card__value--mono' : '' }}">{{ $item['value'] }}</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>

    @isset($action)
        <div class="ag-summary-card__action">{{ $action }}</div>
    @endisset
</div>
