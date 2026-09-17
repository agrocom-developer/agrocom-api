{{--
    Page: clientes/index (GET /panel/clientes, panel.clientes.index)
    Listado de clientes (HU-22, tarea 33): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Primer ABM completo del panel: molde de HU-23 a HU-27 y HU-45.

    Datos esperados (ver ClientesController::index()): la cáscara de
    CascaraPanel, más:
    - $clientes (LengthAwarePaginator<Cliente>, con `contactos_count`
      precargado): razón social ascendente.
    - $tiposPersonaFiltro (list<TipoPersonaCliente>), $tiposContactoFiltro
      (list<TipoContactoCliente>), $campaniasDisponibles (Collection<int,
      string>, id => código): opciones de los tres <select> del filtro
      (ronda de homogeneización del 17/9/2026 — mismo `organisms/filter-panel`
      que ya usan Contratos/Propiedades/Lotes). `campania_id` es indirecto
      (clientes con algún contrato en esa campaña, ver
      `ListarClientes::ejecutar()`); `tipo_contacto` filtra por el tipo de al
      menos un contacto del cliente.
    - $filtros (array{q: string, tipo_persona: string|null, campania_id:
      int|null, tipo_contacto: string|null}): filtros aplicados, para dejar
      los campos con el valor tras el submit.

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
                        <x-atoms.button :href="route('panel.clientes.create')" variant="primary" icon="add">
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
                $filtrosPanelActivos = collect(['tipo_persona', 'campania_id', 'tipo_contacto'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            @if ($hayFiltrosActivos || $clientes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.clientes.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="tipo_persona"
                            id="filtro-tipo-persona"
                            :label="__('comercial.clientes.filtro_tipo_persona')"
                            :options="collect($tiposPersonaFiltro)->mapWithKeys(fn ($tipo) => [$tipo->value => __('comercial.clientes.tipo_persona_opcion.'.$tipo->value)])"
                            :value="$filtros['tipo_persona']"
                            :placeholder="__('comercial.clientes.filtro_tipo_persona_placeholder')"
                        />

                        <x-atoms.select
                            name="campania_id"
                            id="filtro-campania"
                            :label="__('comercial.clientes.filtro_campania')"
                            :options="$campaniasDisponibles"
                            :value="$filtros['campania_id']"
                            :placeholder="__('comercial.clientes.filtro_campania_placeholder')"
                        />

                        <x-atoms.select
                            name="tipo_contacto"
                            id="filtro-tipo-contacto"
                            :label="__('comercial.clientes.filtro_tipo_contacto')"
                            :options="collect($tiposContactoFiltro)->mapWithKeys(fn ($tipo) => [$tipo->value => __('comercial.clientes.contacto_tipo_opcion.'.$tipo->value)])"
                            :value="$filtros['tipo_contacto']"
                            :placeholder="__('comercial.clientes.filtro_tipo_contacto_placeholder')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.clientes.index')"
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
                <x-molecules.index-table columns="3rem 2fr 1fr 1fr var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('comercial.clientes.col_razon_social') }}</span>
                        <span role="columnheader">{{ __('comercial.clientes.col_nit') }}</span>
                        <span role="columnheader">{{ __('comercial.clientes.col_contactos') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($clientes as $cliente)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($clientes->currentPage() - 1) * $clientes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-clientes__razon-social">{{ $cliente->razon_social }}</span>
                            <span role="cell" class="ag-clientes__nit">{{ $cliente->nit ?? __('comercial.clientes.sin_nit') }}</span>
                            <span role="cell">{{ __('comercial.clientes.contactos_cantidad', ['cantidad' => $cliente->contactos_count]) }}</span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Form FUERA de row-actions a propósito: ese organism repite su
                                     slot dos veces (visible/menú, ver su docblock) — un <form> con id
                                     ahí adentro se duplicaría con el mismo id, HTML inválido. El botón
                                     de confirm-button lo envía por su atributo `form`, sin importar
                                     dónde viva en el documento. --}}
                                @puede('comercial.cliente.eliminar')
                                    <form
                                        id="cliente-eliminar-{{ $cliente->id }}"
                                        method="POST"
                                        action="{{ route('panel.clientes.destroy', $cliente) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('comercial.cliente.editar')
                                        <x-atoms.button :href="route('panel.clientes.edit', $cliente)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('comercial.clientes.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('comercial.cliente.eliminar')
                                        <span class="ag-row-actions__item">
                                            <x-molecules.confirm-button
                                                :form-id="'cliente-eliminar-' . $cliente->id"
                                                :title="__('comercial.clientes.confirmar_eliminar_titulo')"
                                                :message="__('comercial.clientes.confirmar_baja')"
                                                :confirm-label="__('comercial.clientes.eliminar_accion')"
                                                variant="danger-outline"
                                                size="sm"
                                                icon="delete"
                                            >
                                                {{ __('comercial.clientes.eliminar_accion') }}
                                            </x-molecules.confirm-button>
                                        </span>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$clientes" :aria-label="__('comercial.clientes.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
