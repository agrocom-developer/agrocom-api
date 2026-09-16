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

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $clientes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        action="{{ route('panel.clientes.index') }}"
                        :value="$filtros['q']"
                        :placeholder="__('comercial.clientes.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($clientes->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('comercial.clientes.filtro_vacio_titulo')"
                        :detail="__('comercial.clientes.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="contact_page"
                        :title="__('comercial.clientes.vacio_titulo')"
                        :detail="__('comercial.clientes.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-clientes__tabla" role="table">
                    <div class="ag-clientes__head" role="row">
                        <span role="columnheader" class="ag-clientes__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('comercial.clientes.col_razon_social') }}</span>
                        <span role="columnheader">{{ __('comercial.clientes.col_nit') }}</span>
                        <span role="columnheader">{{ __('comercial.clientes.col_contactos') }}</span>
                        <span role="columnheader" class="ag-clientes__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </div>

                    @foreach ($clientes as $cliente)
                        <div class="ag-clientes__fila" role="row">
                            <span role="cell" class="ag-clientes__indice">
                                {{ ($clientes->currentPage() - 1) * $clientes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-clientes__razon-social">{{ $cliente->razon_social }}</span>
                            <span role="cell" class="ag-clientes__nit">{{ $cliente->nit ?? __('comercial.clientes.sin_nit') }}</span>
                            <span role="cell">{{ __('comercial.clientes.contactos_cantidad', ['cantidad' => $cliente->contactos_count]) }}</span>

                            <span role="cell" class="ag-clientes__acciones">
                                <x-organisms.row-actions>
                                    @puede('comercial.cliente.editar')
                                        <x-atoms.button href="{{ route('panel.clientes.edit', $cliente) }}" variant="warning-outline" size="sm" icon="edit">
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
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$clientes" :aria-label="__('comercial.clientes.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
