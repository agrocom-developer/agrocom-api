{{--
    Page: bases/index (GET /panel/bases, panel.bases.index)
    Listado de bases operativas (HU-26, tarea 37): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Homogeneizado con el patrón de Clientes (tarea 112): tabla en
    `molecules/index-table`, acciones en `organisms/row-actions` y la baja con
    `molecules/confirm-modal`. Una base es catálogo simple (nombre,
    ubicación): sin filtros más que el buscador, así que no lleva
    `filter-panel` (mismo criterio que Campañas).

    Datos esperados (ver BasesController::index()): la cáscara de
    CascaraPanel, más:
    - $bases (LengthAwarePaginator<PerBase>): nombre ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `personal.base.ver`, verificado server-side en el
    controlador. Los botones "Nueva base"/"Editar"/"Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    BasesController).

    Estilos en resources/css/pages/bases.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('personal.bases.titulo')" :tema="$tema">
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
        :vista-actual="__('personal.bases.titulo')"
    >
        <div class="ag-bases">
            <x-organisms.page-header
                :title="__('personal.bases.titulo')"
                :subtitle="__('personal.bases.subtitulo')"
            >
                @puede('personal.base.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.bases.create')" variant="primary" icon="add">
                            {{ __('personal.bases.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $bases->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.bases.index')"
                        :value="$filtros['q']"
                        :placeholder="__('personal.bases.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($bases->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('personal.bases.filtro_vacio_titulo')"
                        :detail="__('personal.bases.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="home_work"
                        :title="__('personal.bases.vacio_titulo')"
                        :detail="__('personal.bases.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.5fr) minmax(0, 1.5fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('personal.bases.col_nombre') }}</span>
                        <span role="columnheader">{{ __('personal.bases.col_ubicacion') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($bases as $base)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($bases->currentPage() - 1) * $bases->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-bases__nombre">{{ $base->nombre }}</span>
                            <span role="cell">{{ $base->ubicacion ?? __('personal.bases.sin_ubicacion') }}</span>

                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $formIdEliminar = "base-eliminar-{$base->id}";
                                    $modalIdEliminar = "base-eliminar-modal-{$base->id}";
                                @endphp

                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock), así que
                                     un <form> o un modal con id ahí adentro se duplicaría — y el que
                                     cae dentro del menú ⋮ queda oculto con él y nunca abre. El
                                     disparador vive adentro (es un botón sin id propio, se duplica sin
                                     problema); el modal y el form, una sola vez, acá. Mismo criterio
                                     que campanias/index. --}}
                                @puede('personal.base.eliminar')
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.bases.destroy', $base) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('personal.bases.confirmar_eliminar_titulo')"
                                        :message="__('personal.bases.confirmar_baja')"
                                        :confirm-label="__('personal.bases.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('personal.base.editar')
                                        <x-atoms.button :href="route('panel.bases.edit', $base)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('personal.bases.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('personal.base.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdEliminar }}"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('personal.bases.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$bases" :aria-label="__('personal.bases.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
