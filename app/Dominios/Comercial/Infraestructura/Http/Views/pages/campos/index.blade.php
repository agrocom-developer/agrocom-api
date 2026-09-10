{{--
    Page: campos/index (GET /panel/campos, panel.campos.index)
    Listado de campos (HU-24, tarea 35): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que clientes/index.blade.php (tarea 33).

    Datos esperados (ver CamposController::index()): la cáscara de
    CascaraPanel, más:
    - $campos (LengthAwarePaginator<Campo>, con `propiedad.cliente` cargada,
      `lotes_count` y `hectareas_totales` precargados vía withCount/withSum):
      nombre ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `comercial.campo.ver`, verificado server-side en el
    controlador. Los botones "Nuevo campo"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    CamposController).

    Estilos en resources/css/pages/campos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.campos.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.campos.titulo')"
    >
        <div class="ag-campos">
            <x-organisms.page-header
                :title="__('comercial.campos.titulo')"
                :subtitle="__('comercial.campos.subtitulo')"
            >
                @puede('comercial.campo.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.campos.create') }}" variant="primary" icon="add">
                            {{ __('comercial.campos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-campos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.campos.index') }}" class="ag-filtros ag-campos__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('comercial.campos.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('comercial.campos.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <div class="ag-filtros__acciones ag-campos__filtros-acciones">
                    {{-- outline, no primary: "Nuevo campo" ya es el único botón
                         sólido del pliegue (§5 de la guía de pantalla). --}}
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('comercial.campos.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '')
                        <x-atoms.button href="{{ route('panel.campos.index') }}" variant="text" size="md">
                            {{ __('comercial.campos.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($campos->isEmpty())
                <x-molecules.alert-strip variant="info" icon="map" class="ag-campos__aviso">
                    {{ __($filtros['q'] !== '' ? 'comercial.campos.filtro_vacio' : 'comercial.campos.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-campos__tabla" role="table">
                    <div class="ag-campos__head" role="row">
                        <span role="columnheader">{{ __('comercial.campos.col_nombre') }}</span>
                        <span role="columnheader">{{ __('comercial.campos.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.campos.col_lotes') }}</span>
                        <span role="columnheader">{{ __('comercial.campos.col_hectareas') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($campos as $campo)
                        <div class="ag-campos__fila" role="row">
                            <span role="cell" class="ag-campos__nombre">{{ $campo->nombre }}</span>
                            <span role="cell">{{ $campo->propiedad->cliente->razon_social }}</span>
                            <span role="cell">{{ __('comercial.campos.lotes_cantidad', ['cantidad' => $campo->lotes_count]) }}</span>
                            <span role="cell" class="ag-campos__hectareas">{{ __('comercial.campos.hectareas_valor', ['cantidad' => number_format((float) $campo->hectareas_totales, 2, ',', '.')]) }}</span>

                            <span role="cell" class="ag-campos__acciones">
                                @puede('comercial.campo.editar')
                                    <x-atoms.button href="{{ route('panel.campos.siembra', $campo) }}" variant="outline" size="sm" icon="grass">
                                        {{ __('comercial.campos.siembra') }}
                                    </x-atoms.button>

                                    <x-atoms.button href="{{ route('panel.campos.edit', $campo) }}" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('comercial.campos.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('comercial.campo.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.campos.destroy', $campo) }}"
                                        onsubmit="return confirm('{{ __('comercial.campos.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('comercial.campos.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($campos->hasPages())
                    <nav class="ag-campos__paginacion" aria-label="{{ __('comercial.campos.paginacion_aria') }}">
                        @if (! $campos->onFirstPage())
                            <x-atoms.button href="{{ $campos->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('comercial.campos.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-campos__paginacion-info">
                            {{ __('comercial.campos.paginacion_info', ['actual' => $campos->currentPage(), 'total' => $campos->lastPage()]) }}
                        </span>

                        @if ($campos->hasMorePages())
                            <x-atoms.button href="{{ $campos->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('comercial.campos.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
