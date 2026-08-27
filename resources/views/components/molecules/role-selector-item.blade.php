{{--
    Molecule: role-selector-item
    (docs/diseno/sistema_diseno_panel.md §4.1 — decisión de composición: el
    catálogo especificaba un único molecule "role-selector" con la lista +
    botón de confirmar; HU-02 lo partió en este ítem reutilizable porque se
    usa en dos contextos distintos con contenedores distintos (la pantalla de
    selección inicial tras el login, armada por `frontend`, y el popover de
    "cambiar de rol activo" de `organisms/topbar`/`organisms/sidebar-nav`) —
    cada contenedor arma su propia lista con un `@foreach` simple, sin una
    molécula "lista" intermedia.

    Sin lógica de negocio: no decide a qué ruta postea ni valida — expone un
    botón con `data-ag-role-id` para que quien lo use (Livewire/JS) enganche
    la acción real (ADR 0004 extensión, punto 4). `label`/`description` son
    vocabulario del dominio (`sec_role.name`/`description`) y NO se traducen
    acá (ADR 0013 punto 3) — se imprimen tal cual llegan.

    Props:
    - id (requerido): id de `sec_role`.
    - label (requerido): `sec_role.name`, sin traducir.
    - description (nullable): `sec_role.description`, sin traducir. Si no se
      pasa, no se renderiza la línea (uso en el popover de cambio de rol,
      donde el catálogo de referencia no muestra descripción).
    - icon (default "badge"): nombre de ícono Material Symbols. `sec_role` no
      tiene columna de ícono — si el llamador quiere uno por rol, lo resuelve
      él (no es dato de este módulo).
    - selected (bool, default false): estado visual + atributo aria según
      `variant`.
    - variant ("pick"|"switch", default "pick"): "pick" es la selección
      inicial (single-select entre opciones aún no confirmadas) → aria-pressed;
      "switch" es el rol YA activo en el popover de cambio → aria-current.
--}}
@props([
    'id',
    'label',
    'description' => null,
    'icon' => 'badge',
    'selected' => false,
    'variant' => 'pick',
])

<button
    type="button"
    data-ag-role-id="{{ $id }}"
    {{ $attributes->class(['ag-role-item', $selected ? 'is-selected' : '']) }}
    @if ($variant === 'switch')
        aria-current="{{ $selected ? 'true' : 'false' }}"
    @else
        aria-pressed="{{ $selected ? 'true' : 'false' }}"
    @endif
>
    <span class="ag-role-item__icon" aria-hidden="true">
        <x-atoms.icon :name="$icon" size="md" />
    </span>

    <span class="ag-role-item__body">
        <span class="ag-role-item__name">{{ $label }}</span>
        @if ($description)
            <span class="ag-role-item__desc">{{ $description }}</span>
        @endif
    </span>

    <span class="ag-role-item__check" aria-hidden="true">
        <x-atoms.icon name="check_circle" size="md" />
    </span>
</button>
