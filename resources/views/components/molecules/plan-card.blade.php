{{--
    Molecule: plan-card
    Tarjeta seleccionable tipo radio para elegir un plan de suscripción (p.
    ej. 3 planes uno al lado del otro). Compone `atoms/icon` + (opcional)
    `atoms/badge` — no orquesta otras moléculas, por eso es molecule y no
    organism.

    Radio real (`<input type="radio">`), visualmente oculto con la técnica
    "visually-hidden" (no `display:none`, se mantiene en el árbol de
    accesibilidad/tabulación) pero semánticamente completo: no hace falta
    ningún `role` adicional, la asociación `<label for>` + `type="radio"`
    nativa ya es 100% accesible y navegable por teclado. La tarjeta entera
    (el `<label>`) es el target de click. El estado seleccionado se resuelve
    100% en CSS vía `:checked` + combinador de hermanos — sin JS propio,
    mismo criterio que `atoms/switch`.

    Quien arme el grupo de 2+ `plan-card` (mismo `name` en todas) debe
    envolverlas en un contenedor con `role="radiogroup"` y un `aria-label`
    ya traducido — esta molécula no lo hace por sí misma: no conoce cuántas
    tarjetas hermanas tiene ni el label del grupo (mismo criterio de
    responsabilidad que `role-selector-item`, que tampoco arma su propia
    lista contenedora).

    Sin lógica de negocio: no decide nombres/precios/features (el llamador
    los pasa ya traducidos/formateados) ni qué pasa al elegir un plan.

    Props:
    - name (requerido): name del grupo de radios (igual en las 2+ tarjetas
      del mismo grupo, como cualquier `<input type="radio">` nativo).
    - value (requerido): value de ESTE radio dentro del grupo.
    - id (nullable): id del `<input>`; por defecto se deriva de `name`+`value`
      (slug) — pasalo explícito si ese slug pudiera colisionar.
    - planName (requerido): nombre del plan, ya traducido.
    - price (requerido): cifra ya formateada por el llamador (sin lógica de
      moneda/decimales acá — mismo criterio que `molecules/stat-card`).
    - period (nullable): texto corto junto al precio (p. ej. "/mes"), ya
      traducido.
    - features (array, default []): lista de strings ya traducidos, una
      línea por feature con un ícono de check delante.
    - selected (bool, default false): estado inicial del radio.
    - disabled (bool, default false).
    - highlightedLabel (nullable): si se pasa, la tarjeta se marca como
      destacada (borde de acento) y muestra un `atoms/badge` con este texto
      ya traducido (p. ej. "Más elegido"). Sin este prop, la tarjeta no se
      distingue visualmente de las demás del grupo.
--}}
@props([
    'name',
    'value',
    'id' => null,
    'planName',
    'price',
    'period' => null,
    'features' => [],
    'selected' => false,
    'disabled' => false,
    'highlightedLabel' => null,
])

@php
    $inputId = $id ?? \Illuminate\Support\Str::slug("{$name}-{$value}");
@endphp

<div {{ $attributes->class(['ag-plan-card', $highlightedLabel ? 'ag-plan-card--highlighted' : '']) }}>
    <input
        type="radio"
        name="{{ $name }}"
        value="{{ $value }}"
        id="{{ $inputId }}"
        class="ag-plan-card__input"
        @checked($selected)
        @disabled($disabled)
    >

    <label for="{{ $inputId }}" class="ag-plan-card__label">
        @if ($highlightedLabel)
            <x-atoms.badge variant="accent" class="ag-plan-card__ribbon">
                {{ $highlightedLabel }}
            </x-atoms.badge>
        @endif

        <span class="ag-plan-card__name">{{ $planName }}</span>

        <span class="ag-plan-card__price">
            <span class="ag-plan-card__price-value">{{ $price }}</span>
            @if ($period)
                <span class="ag-plan-card__price-period">{{ $period }}</span>
            @endif
        </span>

        @if (count($features) > 0)
            <ul class="ag-plan-card__features">
                @foreach ($features as $feature)
                    <li class="ag-plan-card__feature">
                        <x-atoms.icon name="check" size="sm" class="ag-plan-card__feature-icon" />
                        <span>{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <span class="ag-plan-card__check" aria-hidden="true">
            <x-atoms.icon name="check_circle" size="md" />
        </span>
    </label>
</div>
