{{--
    Page: tarifas/index (GET /panel/tarifas, panel.tarifas.index)
    Listado del catálogo de tarifas de pago (ADR 0023): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar (table-search) → tabla →
    paginación. Buscador de nombre; no hay otros filtros.

    Datos esperados (ver TarifasController::index()): la cáscara de
    CascaraPanel, más:
    - $tarifas (LengthAwarePaginator<Tarifa>): nombre ascendente.
    - $filtros (array{q: string}): valores aplicados, para dejar el campo con su
      valor tras el submit.

    Gateada por `finanzas.tarifa.ver`, verificado server-side en el
    controlador. Los botones "Nueva tarifa"/"Editar"/"Dar de baja" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    TarifasController).

    Estilos en resources/css/pages/tarifas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.tarifas.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.tarifas.titulo')"
    >
        <div class="ag-tarifas">
            <x-organisms.page-header
                :title="__('finanzas.tarifas.titulo')"
                :subtitle="__('finanzas.tarifas.subtitulo')"
            >
                @puede('finanzas.tarifa.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.tarifas.create')" variant="primary" icon="add">
                            {{ __('finanzas.tarifas.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-tarifas__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = $filtros['q'] !== '';
            @endphp

            @if ($hayFiltrosActivos || $tarifas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.tarifas.index')"
                        :value="$filtros['q']"
                        :placeholder="__('finanzas.tarifas.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($tarifas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('finanzas.tarifas.filtro_vacio_titulo')"
                        :detail="__('finanzas.tarifas.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="payments"
                        :title="__('finanzas.tarifas.vacio_titulo')"
                        :detail="__('finanzas.tarifas.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem 2fr 1.2fr 0.9fr 0.9fr var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('finanzas.tarifas.col_nombre') }}</span>
                        <span role="columnheader">{{ __('finanzas.tarifas.col_modalidad') }}</span>
                        <span role="columnheader">{{ __('finanzas.tarifas.col_monto_piloto') }}</span>
                        <span role="columnheader">{{ __('finanzas.tarifas.col_monto_auxiliar') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($tarifas as $tarifa)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($tarifas->currentPage() - 1) * $tarifas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-tarifas__nombre-celda">
                                <span class="ag-tarifas__nombre">{{ $tarifa->nombre }}</span>
                                @if ($tarifa->predeterminada)
                                    <x-atoms.badge variant="neutral">
                                        {{ __('finanzas.tarifas.predeterminada') }}
                                    </x-atoms.badge>
                                @endif
                            </span>
                            <span role="cell">
                                @if ($tarifa->modalidad)
                                    <x-atoms.badge variant="neutral">
                                        {{ $tarifa->modalidad->etiqueta() }}
                                    </x-atoms.badge>
                                @endif
                            </span>
                            <span role="cell">
                                {{ __('finanzas.tarifas.monto_valor', ['monto' => number_format((float) $tarifa->monto_piloto, 2, ',', '.')]) }}
                            </span>
                            <span role="cell">
                                {{ __('finanzas.tarifas.monto_valor', ['monto' => number_format((float) $tarifa->monto_auxiliar, 2, ',', '.')]) }}
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Form y modal FUERA de row-actions a propósito: ese organism repite
                                     su slot dos veces (visible/menú). Un <form> o un modal con id ahí
                                     adentro se duplicaría (HTML inválido) y, con el modal dentro del
                                     menú ⋮, se abriría oculto con él. El disparador vive adentro (se
                                     duplica sin problema: es un botón sin id) y apunta al modal por
                                     `data-bs-target`; el modal envía el form por su atributo `form`. --}}
                                @puede('finanzas.tarifa.eliminar')
                                    <form
                                        id="tarifa-eliminar-{{ $tarifa->id }}"
                                        method="POST"
                                        action="{{ route('panel.tarifas.destroy', $tarifa) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="'tarifa-eliminar-modal-' . $tarifa->id"
                                        :form-id="'tarifa-eliminar-' . $tarifa->id"
                                        :title="__('finanzas.tarifas.eliminar_titulo')"
                                        :message="__('finanzas.tarifas.eliminar_detalle')"
                                        :confirm-label="__('finanzas.tarifas.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('finanzas.tarifa.editar')
                                        <x-atoms.button :href="route('panel.tarifas.edit', $tarifa)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('finanzas.tarifas.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('finanzas.tarifa.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#tarifa-eliminar-modal-' . $tarifa->id"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('finanzas.tarifas.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$tarifas" :aria-label="__('finanzas.tarifas.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
