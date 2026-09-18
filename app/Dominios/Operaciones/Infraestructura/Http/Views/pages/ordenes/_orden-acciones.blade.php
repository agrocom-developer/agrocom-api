{{--
    Partial: acciones de una orden — Ver/Editar/Activar/Eliminar. Compartido
    por la fila de `molecules/index-table` (vista lista) y
    `_orden-card.blade.php` (vista grilla, homogeneización 17/9/2026) para no
    duplicar el mismo bloque de forms+modales+row-actions en dos lugares —
    mismo criterio de "Ver" primero, antes de "Editar" (pedido explícito).

    Forms + `molecules/confirm-modal` FUERA de `row-actions` a propósito: ese
    organism repite su slot dos veces (visible/menú, ver su docblock) — un
    `<form>` o un modal con `id` ahí adentro se duplicaría, HTML inválido
    (mismo bug ya documentado en `contratos/index.blade.php`).

    Espera: $orden (OrdenAplicacion), $puedeActivar (bool, ya resuelto por el
    controlador) — el resto de los permisos se resuelve acá con `@puede`,
    igual que el resto del listado.
    - $contexto (string, default ''): prefijo para los ids de forms/modales.
      Desde la homogeneización del toggle lista/grilla a client-side
      (17/9/2026), AMBAS vistas conviven siempre en el DOM (una oculta con
      `hidden`, nunca desmontada) — sin este prefijo, incluir este partial
      dos veces por orden (una en la fila de tabla, otra en la tarjeta de
      grilla) generaría el mismo id de `<form>`/modal DOS VECES en el
      documento, HTML inválido y el botón "Confirmar" del modal enviando
      el form equivocado (el navegador resuelve `document.getElementById`
      al PRIMERO, sin importar cuál está visible).
--}}
@php
    $contexto ??= '';
    $estadoValor = $orden->estado->value;
    $formIdActivar = "orden-activar-{$contexto}{$orden->id}";
    $formIdEliminar = "orden-eliminar-{$contexto}{$orden->id}";
    $modalIdActivar = "orden-activar-modal-{$contexto}{$orden->id}";
    $modalIdEliminar = "orden-eliminar-modal-{$contexto}{$orden->id}";
@endphp

@if ($puedeActivar && $estadoValor === 'emitida')
    <form id="{{ $formIdActivar }}" method="POST" action="{{ route('panel.ordenes.activar', $orden) }}">
        @csrf
    </form>

    <x-molecules.confirm-modal
        :id="$modalIdActivar"
        :form-id="$formIdActivar"
        :title="__('operaciones.ordenes.confirmar_activar_titulo')"
        :message="__('operaciones.ordenes.confirmar_activar')"
        :confirm-label="__('operaciones.ordenes.activar_accion')"
        tone="success"
    />
@endif

@puede('operaciones.orden.eliminar')
    @if ($estadoValor !== 'vigente')
        <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.ordenes.destroy', $orden) }}">
            @csrf
            @method('DELETE')
        </form>

        <x-molecules.confirm-modal
            :id="$modalIdEliminar"
            :form-id="$formIdEliminar"
            :title="__('operaciones.ordenes.confirmar_eliminar_titulo')"
            :message="__('operaciones.ordenes.confirmar_baja')"
            :confirm-label="__('operaciones.ordenes.eliminar_accion')"
            tone="danger"
        />
    @endif
@endpuede

<x-organisms.row-actions>
    <x-atoms.button :href="route('panel.ordenes.show', $orden)" variant="info-outline" size="sm" icon="visibility">
        {{ __('operaciones.ordenes.ver_accion') }}
    </x-atoms.button>

    @puede('operaciones.orden.editar')
        @if ($estadoValor === 'emitida')
            <x-atoms.button :href="route('panel.ordenes.edit', $orden)" variant="warning-outline" size="sm" icon="edit">
                {{ __('operaciones.ordenes.editar') }}
            </x-atoms.button>
        @endif
    @endpuede

    @if ($puedeActivar && $estadoValor === 'emitida')
        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdActivar }}" variant="success-outline" size="sm" icon="check_circle">
            {{ __('operaciones.ordenes.activar_accion') }}
        </x-atoms.button>
    @endif

    @puede('operaciones.orden.eliminar')
        @if ($estadoValor !== 'vigente')
            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdEliminar }}" variant="danger-outline" size="sm" icon="delete">
                {{ __('operaciones.ordenes.eliminar_accion') }}
            </x-atoms.button>
        @endif
    @endpuede
</x-organisms.row-actions>
