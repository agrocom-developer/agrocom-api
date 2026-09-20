{{--
    Page: personas/index (GET /panel/personas, panel.personas.index)
    Listado de personas operativas (HU-26, tarea 37): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Homogeneizado con el patrón de Clientes (tarea 112): tabla en
    `molecules/index-table`, acciones en `organisms/row-actions` y la baja con
    `molecules/confirm-modal`. Mismo molde que bases/index.blade.php, con dos
    columnas más (rol, base) y la tarifa. Sin columna de estado: activo/inactivo
    no se muestra en un listado de catálogo (guía §6.2). El listado solo busca
    por nombre — no filtra por rol ni por base —, así que no lleva
    `filter-panel` (mismo criterio que Campañas y Bases).

    Datos esperados (ver PersonasController::index()): la cáscara de
    CascaraPanel, más:
    - $personas (LengthAwarePaginator<PerPersona>, con `base` precargada):
      nombre ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `personal.persona.ver`, verificado server-side en el
    controlador. Los botones "Nueva persona"/"Desempeño"/"Editar"/"Eliminar"
    se ocultan con `@puede` (presentación, no autorización — el servidor
    revalida en PersonasController). El desempeño es el «Ver» de este
    listado: lleva a una ficha aparte, no a un detalle de la persona.

    Estilos en resources/css/pages/personas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('personal.personas.titulo')" :tema="$tema">
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
        :vista-actual="__('personal.personas.titulo')"
    >
        <div class="ag-personas">
            <x-organisms.page-header
                :title="__('personal.personas.titulo')"
                :subtitle="__('personal.personas.subtitulo')"
            >
                @puede('personal.persona.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.personas.create')" variant="primary" icon="add">
                            {{ __('personal.personas.nueva') }}
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

            @if ($hayFiltrosActivos || $personas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.personas.index')"
                        :value="$filtros['q']"
                        :placeholder="__('personal.personas.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($personas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('personal.personas.filtro_vacio_titulo')"
                        :detail="__('personal.personas.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="badge"
                        :title="__('personal.personas.vacio_titulo')"
                        :detail="__('personal.personas.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 1.2fr) minmax(0, 1.2fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_nombre') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_rol') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_base') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_tarifa') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($personas as $persona)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($personas->currentPage() - 1) * $personas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-personas__nombre">{{ $persona->nombre }}</span>
                            <span role="cell">{{ __('personal.roles.'.$persona->rol->value) }}</span>
                            <span role="cell">{{ $persona->base?->nombre ?? __('personal.personas.sin_base') }}</span>
                            <span role="cell" class="ag-personas__tarifa">
                                {{ $persona->tarifa_ha !== null ? __('personal.personas.tarifa_valor', ['monto' => $persona->tarifa_ha]) : __('personal.personas.sin_tarifa') }}
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $formIdEliminar = "persona-eliminar-{$persona->id}";
                                    $modalIdEliminar = "persona-eliminar-modal-{$persona->id}";
                                @endphp

                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock), así que
                                     un <form> o un modal con id ahí adentro se duplicaría — y el que
                                     cae dentro del menú ⋮ queda oculto con él y nunca abre. El
                                     disparador vive adentro (es un botón sin id propio, se duplica sin
                                     problema); el modal y el form, una sola vez, acá. Mismo criterio
                                     que bases/index y campanias/index. --}}
                                @puede('personal.persona.eliminar')
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.personas.destroy', $persona) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('personal.personas.confirmar_eliminar_titulo')"
                                        :message="__('personal.personas.confirmar_baja')"
                                        :confirm-label="__('personal.personas.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('personal.persona.desempenio')
                                        <x-atoms.button :href="route('panel.personas.desempenio', $persona)" variant="info-outline" size="sm" icon="insights">
                                            {{ __('personal.personas.desempenio.ver') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('personal.persona.editar')
                                        <x-atoms.button :href="route('panel.personas.edit', $persona)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('personal.personas.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('personal.persona.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdEliminar }}"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('personal.personas.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$personas" :aria-label="__('personal.personas.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
