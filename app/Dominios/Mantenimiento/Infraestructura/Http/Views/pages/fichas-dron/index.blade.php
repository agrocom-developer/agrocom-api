{{--
    Page: fichas-dron/index (GET /panel/fichas-dron, panel.fichas-dron.index)
    Listado de fichas de inventario de dron (HU-82, tarea 97): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar →
    tabla → paginación. Homogeneizado con el patrón de Drones (tarea 115):
    tabla en `molecules/index-table`, acciones en `organisms/row-actions` y la
    baja con `molecules/confirm-modal` (antes tabla propia y `confirm()`
    nativo). Una ficha es un ABM plano —serie, chasis, software, región y
    accesorios—: sin columna de estado ni KPI, y sin más filtro que el
    buscador, así que no lleva `filter-panel` (mismo criterio que Drones,
    Campañas y Bases).

    Datos esperados (ver FichasDronController::index()): la cáscara de
    CascaraPanel, más:
    - $fichas (LengthAwarePaginator<FichaDron>): identificador_dron
      ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con su valor tras el submit.

    Gateada por `mantenimiento.ficha_dron.ver`, verificado server-side en el
    controlador. Los botones "Nueva ficha"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    FichasDronController).

    Estilos en resources/css/pages/fichas-dron.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('mantenimiento.fichas_dron.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.fichas_dron.titulo')"
    >
        <div class="ag-fichas-dron">
            <x-organisms.page-header
                :title="__('mantenimiento.fichas_dron.titulo')"
                :subtitle="__('mantenimiento.fichas_dron.subtitulo')"
            >
                @puede('mantenimiento.ficha_dron.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.fichas-dron.create')" variant="primary" icon="add">
                            {{ __('mantenimiento.fichas_dron.nuevo') }}
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

            @if ($hayFiltrosActivos || $fichas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.fichas-dron.index')"
                        :value="$filtros['q']"
                        :placeholder="__('mantenimiento.fichas_dron.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($fichas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('mantenimiento.fichas_dron.filtro_vacio_titulo')"
                        :detail="__('mantenimiento.fichas_dron.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="memory"
                        :title="__('mantenimiento.fichas_dron.vacio_titulo')"
                        :detail="__('mantenimiento.fichas_dron.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 1.4fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_identificador') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_numero_serie') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_chasis') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_version_software') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_region') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.fichas_dron.col_accesorios') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($fichas as $ficha)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($fichas->currentPage() - 1) * $fichas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-fichas-dron__identificador">{{ $ficha->identificador_dron }}</span>
                            <span role="cell" class="ag-fichas-dron__serie">{{ $ficha->numero_serie ?? __('mantenimiento.fichas_dron.sin_dato') }}</span>
                            <span role="cell" class="ag-fichas-dron__serie">{{ $ficha->chasis ?? __('mantenimiento.fichas_dron.sin_dato') }}</span>
                            <span role="cell">{{ $ficha->version_software ?? __('mantenimiento.fichas_dron.sin_dato') }}</span>
                            <span role="cell">{{ $ficha->region ?? __('mantenimiento.fichas_dron.sin_dato') }}</span>
                            <span role="cell" class="ag-fichas-dron__accesorios">
                                @if (! $ficha->tiene_cargador_control && ! $ficha->tiene_modem && ! $ficha->tiene_maletin)
                                    {{ __('mantenimiento.fichas_dron.sin_accesorios') }}
                                @endif
                                @if ($ficha->tiene_cargador_control)
                                    <x-atoms.badge variant="neutral">{{ __('mantenimiento.fichas_dron.accesorio_cargador_control') }}</x-atoms.badge>
                                @endif
                                @if ($ficha->tiene_modem)
                                    <x-atoms.badge variant="neutral">{{ __('mantenimiento.fichas_dron.accesorio_modem') }}</x-atoms.badge>
                                @endif
                                @if ($ficha->tiene_maletin)
                                    <x-atoms.badge variant="neutral">{{ __('mantenimiento.fichas_dron.accesorio_maletin') }}</x-atoms.badge>
                                @endif
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $formIdEliminar = "ficha-dron-eliminar-{$ficha->id}";
                                    $modalIdEliminar = "ficha-dron-eliminar-modal-{$ficha->id}";
                                @endphp

                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock), así que
                                     un <form> o un modal con id ahí adentro se duplicaría — y el que
                                     cae dentro del menú ⋮ queda oculto con él y nunca abre. El
                                     disparador vive adentro (es un botón sin id propio, se duplica sin
                                     problema); el modal y el form, una sola vez, acá. Mismo criterio
                                     que campanias/index, bases/index y drones/index. --}}
                                @puede('mantenimiento.ficha_dron.eliminar')
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.fichas-dron.destroy', $ficha) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('mantenimiento.fichas_dron.confirmar_eliminar_titulo')"
                                        :message="__('mantenimiento.fichas_dron.confirmar_baja')"
                                        :confirm-label="__('mantenimiento.fichas_dron.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('mantenimiento.ficha_dron.editar')
                                        <x-atoms.button :href="route('panel.fichas-dron.edit', $ficha)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('mantenimiento.fichas_dron.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('mantenimiento.ficha_dron.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdEliminar }}"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('mantenimiento.fichas_dron.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$fichas" :aria-label="__('mantenimiento.fichas_dron.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
