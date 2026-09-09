{{--
    Page: clientes/index (GET /panel/clientes, panel.clientes.index)
    Listado de clientes (HU-22, tarea 33): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Primer ABM completo del panel: molde de HU-23 a HU-27 y HU-45.

    Datos esperados (ver ClientesController::index()): la cáscara de
    CascaraPanel, más:
    - $clientes (LengthAwarePaginator<Cliente>, con `contactos_count`
      precargado): razón social ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `comercial.cliente.ver`, verificado server-side en el
    controlador. Los botones "Nuevo cliente"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    ClientesController).

    Estilos en resources/css/pages/clientes.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.clientes.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.clientes.titulo')"
    >
        <div class="ag-clientes">
            <x-organisms.page-header
                :title="__('comercial.clientes.titulo')"
                :subtitle="__('comercial.clientes.subtitulo')"
            >
                @puede('comercial.cliente.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.clientes.create') }}" variant="primary" icon="add">
                            {{ __('comercial.clientes.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-clientes__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.clientes.index') }}" class="ag-filtros ag-clientes__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('comercial.clientes.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('comercial.clientes.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <div class="ag-filtros__acciones ag-clientes__filtros-acciones">
                    {{-- outline, no primary: "Nuevo cliente" ya es el único botón
                         sólido del pliegue (§5 de la guía de pantalla). --}}
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('comercial.clientes.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '')
                        <x-atoms.button href="{{ route('panel.clientes.index') }}" variant="text" size="md">
                            {{ __('comercial.clientes.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($clientes->isEmpty())
                <x-molecules.alert-strip variant="info" icon="contact_page" class="ag-clientes__aviso">
                    {{ __($filtros['q'] !== '' ? 'comercial.clientes.filtro_vacio' : 'comercial.clientes.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-clientes__tabla" role="table">
                    <div class="ag-clientes__head" role="row">
                        <span role="columnheader">{{ __('comercial.clientes.col_razon_social') }}</span>
                        <span role="columnheader">{{ __('comercial.clientes.col_nit') }}</span>
                        <span role="columnheader">{{ __('comercial.clientes.col_contactos') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($clientes as $cliente)
                        <div class="ag-clientes__fila" role="row">
                            <span role="cell" class="ag-clientes__razon-social">{{ $cliente->razon_social }}</span>
                            <span role="cell" class="ag-clientes__nit">{{ $cliente->nit ?? __('comercial.clientes.sin_nit') }}</span>
                            <span role="cell">{{ __('comercial.clientes.contactos_cantidad', ['cantidad' => $cliente->contactos_count]) }}</span>

                            <span role="cell" class="ag-clientes__acciones">
                                @puede('comercial.cliente.editar')
                                    <x-atoms.button href="{{ route('panel.clientes.edit', $cliente) }}" variant="outline" size="sm" icon="edit">
                                        {{ __('comercial.clientes.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('comercial.cliente.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.clientes.destroy', $cliente) }}"
                                        onsubmit="return confirm('{{ __('comercial.clientes.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('comercial.clientes.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($clientes->hasPages())
                    <nav class="ag-clientes__paginacion" aria-label="{{ __('comercial.clientes.paginacion_aria') }}">
                        @if (! $clientes->onFirstPage())
                            <x-atoms.button href="{{ $clientes->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('comercial.clientes.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-clientes__paginacion-info">
                            {{ __('comercial.clientes.paginacion_info', ['actual' => $clientes->currentPage(), 'total' => $clientes->lastPage()]) }}
                        </span>

                        @if ($clientes->hasMorePages())
                            <x-atoms.button href="{{ $clientes->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('comercial.clientes.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
