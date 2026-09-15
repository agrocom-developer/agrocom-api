{{--
    Page: lotes/index (GET /panel/lotes, panel.lotes.index)
    Listado de lotes (tarea 77, HU-54, etapa 2; actualizado ADR 0020): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros →
    tabla → paginación. Antes de esta tarea un lote solo se podía ver entrando
    por su propiedad; esta pantalla es su ficha propia.

    Datos esperados (ver LotesController::index()): la cáscara de
    CascaraPanel, más:
    - $lotes (LengthAwarePaginator<Lote>, con `propiedad.cliente` cargada):
      código ascendente.
    - $filtros (array{q: string, cliente_id: ?int, propiedad_id: ?int}): filtros
      aplicados, para dejarlos con el valor tras el submit.
    - $clientesDisponibles (Collection<int, string>), $propiedadesDisponibles
      (Collection<int, Propiedad>): opciones de los selects de filtro (ADR 0020:
      cascade cliente → propiedad).

    Gateada por `comercial.lote.ver`, verificado server-side en el
    controlador. Los botones "Nuevo lote"/"Editar"/"Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    LotesController).

    Estilos en resources/css/pages/lotes.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.lotes.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.lotes.titulo')"
    >
        <div class="ag-lotes">
            <x-organisms.page-header
                :title="__('comercial.lotes.titulo')"
                :subtitle="__('comercial.lotes.subtitulo')"
            >
                @puede('comercial.lote.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.lotes.create') }}" variant="primary" icon="add">
                            {{ __('comercial.lotes.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-lotes__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('lote'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-lotes__aviso">
                    {{ $errors->first('lote') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = $filtros['q'] !== '' || $filtros['cliente_id'] !== null || $filtros['propiedad_id'] !== null;
                $propiedadesOptions = $propiedadesDisponibles->mapWithKeys(fn ($propiedad) => [
                    $propiedad->id => $propiedad->nombre,
                ]);
            @endphp

            <form method="GET" action="{{ route('panel.lotes.index') }}" class="ag-filtros ag-lotes__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('comercial.lotes.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('comercial.lotes.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <x-atoms.select
                    name="cliente_id"
                    id="filtro-cliente"
                    label="{{ __('comercial.lotes.filtro_cliente') }}"
                    :options="$clientesDisponibles"
                    :value="$filtros['cliente_id']"
                    placeholder="{{ __('comercial.lotes.filtro_todos') }}"
                />

                <x-atoms.select
                    name="propiedad_id"
                    id="filtro-propiedad"
                    label="{{ __('comercial.lotes.filtro_propiedad') }}"
                    :options="$propiedadesOptions"
                    :value="$filtros['propiedad_id']"
                    placeholder="{{ __('comercial.lotes.filtro_todos') }}"
                />

                <div class="ag-filtros__acciones ag-lotes__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('comercial.lotes.filtrar') }}
                    </x-atoms.button>

                    @if ($hayFiltrosActivos)
                        <x-atoms.button href="{{ route('panel.lotes.index') }}" variant="text" size="md">
                            {{ __('comercial.lotes.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($lotes->isEmpty())
                <x-molecules.alert-strip variant="info" icon="grid_view" class="ag-lotes__aviso">
                    {{ __($hayFiltrosActivos ? 'comercial.lotes.filtro_vacio' : 'comercial.lotes.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-lotes__tabla" role="table">
                    <div class="ag-lotes__head" role="row">
                        <span role="columnheader">{{ __('comercial.lotes.col_codigo') }}</span>
                        <span role="columnheader">{{ __('comercial.lotes.col_propiedad') }}</span>
                        <span role="columnheader">{{ __('comercial.lotes.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.lotes.col_hectareas') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($lotes as $lote)
                        <div class="ag-lotes__fila" role="row">
                            <span role="cell" class="ag-lotes__codigo">{{ $lote->codigo }}</span>
                            <span role="cell">{{ $lote->propiedad->nombre }}</span>
                            <span role="cell">{{ $lote->propiedad->cliente->razon_social }}</span>
                            <span role="cell" class="ag-lotes__hectareas">{{ __('comercial.lotes.hectareas_valor', ['cantidad' => number_format((float) $lote->hectareas, 2, ',', '.')]) }}</span>

                            <span role="cell" class="ag-lotes__acciones">
                                @puede('comercial.lote.editar')
                                    <x-atoms.button href="{{ route('panel.lotes.edit', $lote) }}" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('comercial.lotes.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('comercial.lote.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.lotes.destroy', $lote) }}"
                                        onsubmit="return confirm('{{ __('comercial.lotes.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('comercial.lotes.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($lotes->hasPages())
                    <nav class="ag-lotes__paginacion" aria-label="{{ __('comercial.lotes.paginacion_aria') }}">
                        @if (! $lotes->onFirstPage())
                            <x-atoms.button href="{{ $lotes->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('comercial.lotes.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-lotes__paginacion-info">
                            {{ __('comercial.lotes.paginacion_info', ['actual' => $lotes->currentPage(), 'total' => $lotes->lastPage()]) }}
                        </span>

                        @if ($lotes->hasMorePages())
                            <x-atoms.button href="{{ $lotes->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('comercial.lotes.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
