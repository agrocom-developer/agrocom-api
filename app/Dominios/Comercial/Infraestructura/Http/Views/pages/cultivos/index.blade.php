{{--
    Page: cultivos/index (GET /panel/cultivos, panel.cultivos.index)
    Listado del catálogo de cultivos (HU-48, tarea 71): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que bases/index.blade.php, con una columna de
    estado (activo/inactivo) en vez de ubicación (mismo patrón que
    personas/index.blade.php).

    Datos esperados (ver CultivosController::index()): la cáscara de
    CascaraPanel, más:
    - $cultivos (LengthAwarePaginator<Cultivo>): nombre ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `comercial.cultivo.ver`, verificado server-side en el
    controlador. Los botones "Nuevo cultivo"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    CultivosController).

    Estilos en resources/css/pages/cultivos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.cultivos.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('comercial.cultivos.titulo')"
    >
        <div class="ag-cultivos">
            <x-organisms.page-header
                :title="__('comercial.cultivos.titulo')"
                :subtitle="__('comercial.cultivos.subtitulo')"
            >
                @puede('comercial.cultivo.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.cultivos.create') }}" variant="primary" icon="add">
                            {{ __('comercial.cultivos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-cultivos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.cultivos.index') }}" class="ag-filtros ag-cultivos__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('comercial.cultivos.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('comercial.cultivos.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <div class="ag-filtros__acciones ag-cultivos__filtros-acciones">
                    {{-- outline, no primary: "Nuevo cultivo" ya es el único
                         botón sólido del pliegue (§5 de la guía de pantalla). --}}
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('comercial.cultivos.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '')
                        <x-atoms.button href="{{ route('panel.cultivos.index') }}" variant="text" size="md">
                            {{ __('comercial.cultivos.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($cultivos->isEmpty())
                <x-molecules.alert-strip variant="info" icon="grass" class="ag-cultivos__aviso">
                    {{ __($filtros['q'] !== '' ? 'comercial.cultivos.filtro_vacio' : 'comercial.cultivos.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-cultivos__tabla" role="table">
                    <div class="ag-cultivos__head" role="row">
                        <span role="columnheader">{{ __('comercial.cultivos.col_nombre') }}</span>
                        <span role="columnheader">{{ __('comercial.cultivos.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($cultivos as $cultivo)
                        <div class="ag-cultivos__fila" role="row">
                            <span role="cell" class="ag-cultivos__nombre">{{ $cultivo->nombre }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$cultivo->activo ? 'success' : 'neutral'">
                                    {{ __($cultivo->activo ? 'comercial.cultivos.estado_activo' : 'comercial.cultivos.estado_inactivo') }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-cultivos__acciones">
                                @puede('comercial.cultivo.editar')
                                    <x-atoms.button href="{{ route('panel.cultivos.edit', $cultivo) }}" variant="outline" size="sm" icon="edit">
                                        {{ __('comercial.cultivos.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('comercial.cultivo.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.cultivos.destroy', $cultivo) }}"
                                        onsubmit="return confirm('{{ __('comercial.cultivos.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('comercial.cultivos.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($cultivos->hasPages())
                    <nav class="ag-cultivos__paginacion" aria-label="{{ __('comercial.cultivos.paginacion_aria') }}">
                        @if (! $cultivos->onFirstPage())
                            <x-atoms.button href="{{ $cultivos->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('comercial.cultivos.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-cultivos__paginacion-info">
                            {{ __('comercial.cultivos.paginacion_info', ['actual' => $cultivos->currentPage(), 'total' => $cultivos->lastPage()]) }}
                        </span>

                        @if ($cultivos->hasMorePages())
                            <x-atoms.button href="{{ $cultivos->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('comercial.cultivos.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
