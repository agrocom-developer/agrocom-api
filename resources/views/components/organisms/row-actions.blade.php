{{--
    Organism: row-actions (`.ag-row-actions`) — el ÚNICO componente de acciones de
    fila del panel.

    Fuerza el orden en todas las tablas, sin importar en qué orden las declare el
    llamador: Ver, Editar, Permisos, Aprobar, transiciones de la máquina de estados,
    Eliminar (Eliminar siempre al final y siempre en peligro). Cada acción es un
    `<x-molecules.row-action>` dentro del slot con nombre que le corresponde:

        <x-organisms.row-actions>
            <x-slot:ver><x-molecules.row-action kind="ver" … /></x-slot:ver>
            <x-slot:editar>…</x-slot:editar>
            <x-slot:permisos>…solo Roles: lleva a la asignación de permisos…</x-slot:permisos>
            <x-slot:aprobar>…</x-slot:aprobar>
            <x-slot:transiciones>…varias, ya filtradas por estado y permiso…</x-slot:transiciones>
            <x-slot:eliminar>…</x-slot:eliminar>
        </x-organisms.row-actions>

    Hasta `maxIconos` acciones (tres por omisión) se ven como botones de ícono (con
    `title` y `aria-label`); con más, todas pasan al menú «⋮» de la fila, con ícono y texto. El slot por
    defecto no existe: una acción suelta no tiene lugar en el orden.

    Los `<form>` y los `confirm-modal` van FUERA de este componente, en la celda: aquí
    cada acción se pinta una sola vez, pero un form dentro del menú queda oculto con él.

    Props:
    - maxIconos (int, default 3): cuántas acciones caben como botones de ícono.
    - label (string|null): nombre accesible del grupo; por omisión `ui.tabla.acciones_fila`.
--}}
@props([
    'maxIconos' => 3,
    'label' => null,
])

@php
    // El orden vive acá y solo acá (la prueba RowActionsOrdenTest lo congela).
    $porOrden = [
        'ver' => isset($ver) ? trim((string) $ver) : '',
        'editar' => isset($editar) ? trim((string) $editar) : '',
        'permisos' => isset($permisos) ? trim((string) $permisos) : '',
        'aprobar' => isset($aprobar) ? trim((string) $aprobar) : '',
        'transiciones' => isset($transiciones) ? trim((string) $transiciones) : '',
        'eliminar' => isset($eliminar) ? trim((string) $eliminar) : '',
    ];
    $contenido = implode("\n", array_filter($porOrden, fn (string $html): bool => $html !== ''));
    $cantidad = substr_count($contenido, 'data-ag-row-action=');
    $enMenu = $cantidad > $maxIconos;
@endphp

@if ($cantidad > 0)
    <div
        {{ $attributes->class(['ag-row-actions', $enMenu ? 'ag-row-actions--menu' : 'ag-row-actions--iconos']) }}
        role="group"
        aria-label="{{ $label ?? __('ui.tabla.acciones_fila') }}"
    >
        @if ($enMenu)
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
        @else
            {!! $contenido !!}
        @endif
    </div>
@endif
