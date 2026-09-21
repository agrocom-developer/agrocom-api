{{--
    Partial: acciones de una orden — Ver/Editar/Activar/Pausar/Reanudar/Cerrar/
    Cancelar/Eliminar. Compartido por la fila de `molecules/index-table` (vista
    lista) y `_orden-card.blade.php` (vista grilla, homogeneización 17/9/2026)
    para no duplicar el mismo bloque de forms+modales+row-actions en dos
    lugares — mismo criterio de "Ver" primero, antes de "Editar" (pedido
    explícito).

    Los forms + `molecules/confirm-modal` viven en `_orden-modales.blade.php` y
    quedan FUERA de `row-actions` a propósito: ese organism repite su slot dos
    veces (visible/menú, ver su docblock) — un `<form>` o un modal con `id` ahí
    adentro se duplicaría, HTML inválido (mismo bug ya documentado en
    `contratos/index.blade.php`). Acá solo van los botones que los abren.

    Cada botón usa el color del ESTADO DE LLEGADA de su transición (principio
    del catálogo, ver `atoms/button`): Activar/Reanudar → success (vigente),
    Pausar → warning (pausada), Cerrar → info (consumida), Cancelar → danger
    (cancelada).

    «Orden de trabajo» (21/9/2026) no es un cambio de estado sino el paso
    siguiente de una orden vigente: sin ninguna Orden de Trabajo lleva DIRECTO
    al alta con esta orden ya elegida (`?orden_id=`); si ya tiene, al listado
    de las suyas. Por eso lleva un tono propio (`primary-2-outline`), no el de
    un estado.

    Espera: $orden (OrdenAplicacion). Los permisos se resuelven acá con
    `@puede`, igual que el resto del listado.
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
    $sufijo = "{$contexto}{$orden->id}";
    $ordenesTrabajo = (int) ($resumen[$orden->id]['ordenes_trabajo'] ?? 0);
@endphp

@include('operaciones::pages.ordenes._orden-modales', ['orden' => $orden, 'contexto' => $contexto, 'resumenOrden' => $resumen[$orden->id] ?? null])

<x-organisms.row-actions>
    <x-atoms.button :href="route('panel.ordenes.show', $orden)" variant="info-outline" size="sm" icon="visibility">
        {{ __('operaciones.ordenes.ver_accion') }}
    </x-atoms.button>

    @puede('operaciones.orden.editar')
        @if (\App\Dominios\Operaciones\Dominio\PoliticaEdicionOrden::admiteEdicion($orden->estado))
            <x-atoms.button :href="route('panel.ordenes.edit', $orden)" variant="warning-outline" size="sm" icon="edit">
                {{ __('operaciones.ordenes.editar') }}
            </x-atoms.button>
        @endif
    @endpuede

    @if ($ordenesTrabajo > 0)
        @puede('operaciones.trabajo.ver')
            <x-atoms.button :href="route('panel.trabajos.index', ['orden_id' => $orden->id])" variant="primary-2-outline" size="sm" icon="work_history">
                {{ __('operaciones.ordenes.ordenes_trabajo_accion') }}
            </x-atoms.button>
        @endpuede
    @elseif ($estadoValor === 'vigente')
        @puede('operaciones.trabajo.crear')
            <x-atoms.button :href="route('panel.trabajos.create', ['orden_id' => $orden->id])" variant="primary-2-outline" size="sm" icon="add_task">
                {{ __('operaciones.ordenes.orden_trabajo_crear_accion') }}
            </x-atoms.button>
        @endpuede
    @endif

    @puede('operaciones.orden.activar')
        @if ($estadoValor === 'emitida')
            <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-activar-modal-'.$sufijo" variant="success-outline" size="sm" icon="check_circle">
                {{ __('operaciones.ordenes.activar_accion') }}
            </x-atoms.button>
        @endif
    @endpuede

    @puede('operaciones.orden.pausar')
        @if ($estadoValor === 'vigente')
            <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-pausar-modal-'.$sufijo" variant="warning-outline" size="sm" icon="pause_circle">
                {{ __('operaciones.ordenes.pausar_accion') }}
            </x-atoms.button>
        @endif

        @if ($estadoValor === 'pausada')
            <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-reanudar-modal-'.$sufijo" variant="success-outline" size="sm" icon="play_circle">
                {{ __('operaciones.ordenes.reanudar_accion') }}
            </x-atoms.button>
        @endif
    @endpuede

    @puede('operaciones.orden.cerrar')
        @if ($estadoValor === 'vigente')
            <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-cerrar-modal-'.$sufijo" variant="info-outline" size="sm" icon="done_all">
                {{ __('operaciones.ordenes.cerrar_accion') }}
            </x-atoms.button>
        @endif
    @endpuede

    @puede('operaciones.orden.cancelar')
        @if ($estadoValor === 'vigente' || $estadoValor === 'pausada')
            <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-cancelar-modal-'.$sufijo" variant="danger-outline" size="sm" icon="cancel">
                {{ __('operaciones.ordenes.cancelar_accion') }}
            </x-atoms.button>
        @endif
    @endpuede

    @puede('operaciones.orden.eliminar')
        @if ($estadoValor === 'emitida')
            <x-atoms.button type="button" data-bs-toggle="modal" :data-bs-target="'#orden-eliminar-modal-'.$sufijo" variant="danger-outline" size="sm" icon="delete">
                {{ __('operaciones.ordenes.eliminar_accion') }}
            </x-atoms.button>
        @endif
    @endpuede
</x-organisms.row-actions>
