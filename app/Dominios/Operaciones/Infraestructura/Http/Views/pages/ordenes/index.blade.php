{{--
    Page: ordenes/index (GET /panel/ordenes, panel.ordenes.index)
    Listado de órdenes de aplicación (HU-25, tarea 38): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que contratos/index.blade.php (tarea 34): una
    máquina de estados encima, acción de cambio de estado (acá "Activar")
    separada de "Editar".

    Datos esperados (ver OrdenesController::index()): la cáscara de
    CascaraPanel, más:
    - $ordenes (LengthAwarePaginator<OrdenAplicacion>): fecha de emisión
      descendente.
    - $etiquetasContrato / $etiquetasLote (array<int, string>): etiquetas
      legibles por id, ya resueltas por el controlador — la vista nunca
      consulta Comercial (ADR 0003 regla 3, ninguna relación Eloquent desde
      OrdenAplicacion). Un id sin etiqueta (contrato/lote borrado después)
      cae al `#id` crudo.
    - $loteIdsPorOrden (array<int, list<int>>): lotes de CADA orden (HU-92,
      tarea 107 — antes un único `lote_id` por orden), clave `orden->id`.
    - $filtros (array{estado: ?string, tipo_aplicacion: ?string}): filtros
      aplicados, para dejar los selects con el valor tras el submit.
    - $puedeActivar (bool): si el rol activo tiene `operaciones.orden.activar`
      — sin él, la fila no ofrece el botón (el servidor revalida igual en
      OrdenesController::activar()).

    Gateada por `operaciones.orden.ver`. El botón "Editar" solo se ofrece
    para una orden `emitida` (una `vigente` no es editable — ver
    `Aplicacion/ActualizarOrden`); "Activar" solo para `emitida` y con el
    permiso; "Eliminar" para cualquier estado salvo `vigente` (ver
    `Aplicacion/EliminarOrden`). Presentación, no autorización: el servidor
    revalida las tres reglas.

    Estilos en resources/css/pages/ordenes.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $variantePorEstado = [
        'emitida' => 'neutral',
        'vigente' => 'success',
        'consumida' => 'info',
        'vencida' => 'danger',
    ];
@endphp
<x-templates.panel-shell :title="__('operaciones.ordenes.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('operaciones.ordenes.titulo')"
    >
        <div class="ag-ordenes">
            <x-organisms.page-header
                :title="__('operaciones.ordenes.titulo')"
                :subtitle="__('operaciones.ordenes.subtitulo')"
            >
                @puede('operaciones.orden.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.ordenes.create') }}" variant="primary" icon="add">
                            {{ __('operaciones.ordenes.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-ordenes__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-ordenes__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.ordenes.index') }}" class="ag-filtros ag-ordenes__filtros">
                @php
                    $opcionesEstado = collect($variantePorEstado)->mapWithKeys(fn ($variante, $valor) => [
                        $valor => __('operaciones.estado.'.$valor)
                    ])->all();
                @endphp

                <x-atoms.select
                    name="estado"
                    id="filtro-estado"
                    label="{{ __('operaciones.ordenes.filtro_estado') }}"
                    :options="$opcionesEstado"
                    :value="$filtros['estado']"
                    :placeholder="__('operaciones.ordenes.filtro_todos')"
                />

                @php
                    $opcionesTipoAplicacion = collect(\App\Dominios\Operaciones\Dominio\TipoAplicacion::cases())
                        ->mapWithKeys(fn ($caso) => [$caso->value => __('operaciones.tipo_aplicacion.'.$caso->value)]);
                @endphp

                <x-atoms.select
                    name="tipo_aplicacion"
                    id="filtro-tipo-aplicacion"
                    label="{{ __('operaciones.ordenes.filtro_tipo_aplicacion') }}"
                    :options="$opcionesTipoAplicacion"
                    :value="$filtros['tipo_aplicacion']"
                    :placeholder="__('operaciones.ordenes.filtro_todos')"
                />

                <div class="ag-filtros__acciones ag-ordenes__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('operaciones.ordenes.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['estado'] !== null || $filtros['tipo_aplicacion'] !== null)
                        <x-atoms.button href="{{ route('panel.ordenes.index') }}" variant="text" size="md">
                            {{ __('operaciones.ordenes.limpiar_filtros') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($ordenes->isEmpty())
                <x-molecules.alert-strip variant="info" icon="assignment" class="ag-ordenes__aviso">
                    {{ __(($filtros['estado'] !== null || $filtros['tipo_aplicacion'] !== null) ? 'operaciones.ordenes.filtro_vacio' : 'operaciones.ordenes.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-ordenes__tabla" role="table">
                    <div class="ag-ordenes__head" role="row">
                        <span role="columnheader">{{ __('operaciones.ordenes.col_contrato') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes.col_lote') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes.col_aplicacion') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes.col_tipo_aplicacion') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes.col_litros_ha') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes.col_fecha_emision') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($ordenes as $orden)
                        @php
                            $estadoValor = $orden->estado->value;
                            $lotesTexto = collect($loteIdsPorOrden[$orden->id] ?? [])
                                ->map(fn ($loteId) => $etiquetasLote[$loteId] ?? "#{$loteId}")
                                ->implode(', ');
                        @endphp
                        <div class="ag-ordenes__fila" role="row">
                            <span role="cell">{{ $etiquetasContrato[$orden->contrato_id] ?? "#{$orden->contrato_id}" }}</span>
                            <span role="cell">{{ $lotesTexto }}</span>
                            <span role="cell" class="ag-ordenes__mono">{{ $orden->nro_aplicacion }}</span>
                            <span role="cell">{{ __('operaciones.tipo_aplicacion.'.$orden->tipo_aplicacion->value) }}</span>
                            <span role="cell" class="ag-ordenes__mono">{{ number_format((float) $orden->litros_ha, 2, ',', '.') }}</span>
                            <span role="cell" class="ag-ordenes__mono">{{ $orden->fecha_emision->format('d/m/Y') }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                                    {{ __('operaciones.estado.'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-ordenes__acciones">
                                @puede('operaciones.orden.editar')
                                    @if ($estadoValor === 'emitida')
                                        <x-atoms.button href="{{ route('panel.ordenes.edit', $orden) }}" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('operaciones.ordenes.editar') }}
                                        </x-atoms.button>
                                    @endif
                                @endpuede

                                @if ($puedeActivar && $estadoValor === 'emitida')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.ordenes.activar', $orden) }}"
                                        onsubmit="return confirm('{{ __('operaciones.ordenes.confirmar_activar') }}')"
                                    >
                                        @csrf
                                        <x-atoms.button type="submit" variant="outline" size="sm" icon="check_circle">
                                            {{ __('operaciones.ordenes.activar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endif

                                @puede('operaciones.orden.eliminar')
                                    @if ($estadoValor !== 'vigente')
                                        <form
                                            method="POST"
                                            action="{{ route('panel.ordenes.destroy', $orden) }}"
                                            onsubmit="return confirm('{{ __('operaciones.ordenes.confirmar_baja') }}')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                                {{ __('operaciones.ordenes.eliminar_accion') }}
                                            </x-atoms.button>
                                        </form>
                                    @endif
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($ordenes->hasPages())
                    <nav class="ag-ordenes__paginacion" aria-label="{{ __('operaciones.ordenes.paginacion_aria') }}">
                        @if (! $ordenes->onFirstPage())
                            <x-atoms.button href="{{ $ordenes->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('operaciones.ordenes.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-ordenes__paginacion-info">
                            {{ __('operaciones.ordenes.paginacion_info', ['actual' => $ordenes->currentPage(), 'total' => $ordenes->lastPage()]) }}
                        </span>

                        @if ($ordenes->hasMorePages())
                            <x-atoms.button href="{{ $ordenes->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('operaciones.ordenes.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
