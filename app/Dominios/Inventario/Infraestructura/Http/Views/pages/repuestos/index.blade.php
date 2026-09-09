{{--
    Page: repuestos/index (GET /panel/repuestos, panel.repuestos.index)
    Listado del catálogo de repuestos (HU-36, tarea 52): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que baterias/index.blade.php (tarea 51), sin
    columna de alerta: la alerta de mínimo es de `inv_stock` (por base), no
    del catálogo — se muestra en stock/index.blade.php.

    Datos esperados (ver RepuestosController::index()): la cáscara de
    CascaraPanel, más:
    - $repuestos (LengthAwarePaginator<Repuesto>): código ascendente.
    - $filtros (array{q: string}): filtro aplicado, para dejar el campo con
      su valor tras el submit.

    Gateada por `inventario.repuesto.ver`, verificado server-side en el
    controlador. Los botones "Nuevo repuesto"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    RepuestosController).

    Estilos en resources/css/pages/repuestos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('inventario.repuestos.titulo')" :tema="$tema">
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
        :vista-actual="__('inventario.repuestos.titulo')"
    >
        <div class="ag-repuestos">
            <x-organisms.page-header
                :title="__('inventario.repuestos.titulo')"
                :subtitle="__('inventario.repuestos.subtitulo')"
            >
                @puede('inventario.repuesto.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.repuestos.create') }}" variant="primary" icon="add">
                            {{ __('inventario.repuestos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-repuestos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.repuestos.index') }}" class="ag-filtros ag-repuestos__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('inventario.repuestos.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('inventario.repuestos.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <div class="ag-filtros__acciones ag-repuestos__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('inventario.repuestos.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '')
                        <x-atoms.button href="{{ route('panel.repuestos.index') }}" variant="text" size="md">
                            {{ __('inventario.repuestos.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($repuestos->isEmpty())
                <x-molecules.alert-strip variant="info" icon="construction" class="ag-repuestos__aviso">
                    {{ __($filtros['q'] !== '' ? 'inventario.repuestos.filtro_vacio' : 'inventario.repuestos.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-repuestos__tabla" role="table">
                    <div class="ag-repuestos__head" role="row">
                        <span role="columnheader">{{ __('inventario.repuestos.col_codigo') }}</span>
                        <span role="columnheader">{{ __('inventario.repuestos.col_descripcion') }}</span>
                        <span role="columnheader">{{ __('inventario.repuestos.col_unidad') }}</span>
                        <span role="columnheader">{{ __('inventario.repuestos.col_costo') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($repuestos as $repuesto)
                        <div class="ag-repuestos__fila" role="row">
                            <span role="cell" class="ag-repuestos__codigo">{{ $repuesto->codigo }}</span>
                            <span role="cell">{{ $repuesto->descripcion }}</span>
                            <span role="cell">{{ $repuesto->unidad }}</span>
                            <span role="cell">{{ $repuesto->costo_unitario !== null ? $repuesto->costo_unitario : __('inventario.repuestos.sin_costo') }}</span>

                            <span role="cell" class="ag-repuestos__acciones">
                                @puede('inventario.repuesto.editar')
                                    <x-atoms.button href="{{ route('panel.repuestos.edit', $repuesto) }}" variant="outline" size="sm" icon="edit">
                                        {{ __('inventario.repuestos.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('inventario.repuesto.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.repuestos.destroy', $repuesto) }}"
                                        onsubmit="return confirm('{{ __('inventario.repuestos.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('inventario.repuestos.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($repuestos->hasPages())
                    <nav class="ag-repuestos__paginacion" aria-label="{{ __('inventario.repuestos.paginacion_aria') }}">
                        @if (! $repuestos->onFirstPage())
                            <x-atoms.button href="{{ $repuestos->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('inventario.repuestos.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-repuestos__paginacion-info">
                            {{ __('inventario.repuestos.paginacion_info', ['actual' => $repuestos->currentPage(), 'total' => $repuestos->lastPage()]) }}
                        </span>

                        @if ($repuestos->hasMorePages())
                            <x-atoms.button href="{{ $repuestos->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('inventario.repuestos.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
