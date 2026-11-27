{{--
    Organism: row-actions (`.ag-row-actions`) — el ÚNICO componente de acciones de
    fila del panel.

    Fuerza el orden en todas las tablas, sin importar en qué orden las declare el
    llamador: Ver, Editar, Aprobar, transiciones de la máquina de estados, Eliminar
    (Eliminar siempre al final y siempre en peligro). Cada acción es un
    `<x-molecules.row-action>` dentro del slot con nombre que le corresponde:

        <x-organisms.row-actions>
            <x-slot:ver><x-molecules.row-action kind="ver" … /></x-slot:ver>
            <x-slot:editar>…</x-slot:editar>
            <x-slot:aprobar>…</x-slot:aprobar>
            <x-slot:transiciones>…varias, ya filtradas por estado y permiso…</x-slot:transiciones>
            <x-slot:eliminar>…</x-slot:eliminar>
        </x-organisms.row-actions>

    Cada acción se pinta con ícono + texto, nunca solo ícono (pedido explícito, 15/9/2026).
    El contenido se renderiza DOS VECES a propósito: una vez en `.ag-row-actions__visible`
    y otra dentro del menú «⋮» (`.ag-row-actions__menu`); el CSS decide por breakpoint
    cuáles se ven en cada mitad (≥768px, hasta 2 sueltas; en móvil, todas en el menú).
    El slot por defecto no existe: una acción suelta no tiene lugar en el orden.

    Los `<form>` y los `confirm-modal` van FUERA de este componente, en la celda: el
    contenido se duplica, y un id repetido o un form dentro del menú oculto no funciona.

    Props:
    - label (string|null): nombre accesible del grupo; por omisión `ui.tabla.acciones_fila`.
--}}
@props([
    'label' => null,
])

@php
    // El orden vive acá y solo acá (la prueba RowActionsOrdenTest lo congela).
    $porOrden = [
        'ver' => isset($ver) ? trim((string) $ver) : '',
        'editar' => isset($editar) ? trim((string) $editar) : '',
        'aprobar' => isset($aprobar) ? trim((string) $aprobar) : '',
        'transiciones' => isset($transiciones) ? trim((string) $transiciones) : '',
        'eliminar' => isset($eliminar) ? trim((string) $eliminar) : '',
    ];
    $contenido = implode("\n", array_filter($porOrden, fn (string $html): bool => $html !== ''));
@endphp

@if ($contenido !== '')
    <div
        {{ $attributes->class(['ag-row-actions']) }}
        role="group"
        aria-label="{{ $label ?? __('ui.tabla.acciones_fila') }}"
    >
        <div class="ag-row-actions__visible">
            {!! $contenido !!}
        </div>
        <div class="dropdown ag-row-actions__more">
            <button
                type="button"
                class="ag-button ag-button--outline ag-button--sm ag-row-actions__more-btn"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="{{ __('ui.tabla.mas_acciones') }}"
                title="{{ __('ui.tabla.mas_acciones') }}"
            >
                <x-atoms.icon name="more_vert" size="sm" />
            </button>
            <div class="dropdown-menu dropdown-menu-end ag-row-actions__menu">
                {!! $contenido !!}
            </div>
        </div>
    </div>
@endif
