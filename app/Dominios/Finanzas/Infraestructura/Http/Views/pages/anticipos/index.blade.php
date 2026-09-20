{{--
    Page: anticipos/index (GET /panel/anticipos, panel.anticipos.index)
    Listado de anticipos (HU-29, tarea 41): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → KPI → toolbar → tabla →
    paginación. Homogeneizado en la tarea 118 con el patrón de Estadías:
    franja de KPI, `filter-panel`, `index-table`, `row-actions` y
    `confirm-modal` para la baja (antes era la confirmación nativa del
    navegador y el `<form class="ag-filtros">` anterior).

    Sin acción de editar (invariante de esta tarea: un anticipo es inmutable
    salvo baja) y sin buscador: los dos filtros (persona y período) son del
    panel. La única acción de la fila es «Eliminar», así que sin ese permiso
    la tabla no lleva columna de acciones.

    Un anticipo no tiene máquina de estados ni activo/inactivo: no lleva
    pasos, badge de estado ni columna de activo (guía §6.2).

    Datos esperados (ver AnticiposController::index()): la cáscara de
    CascaraPanel, más:
    - $anticipos (LengthAwarePaginator<Anticipo>): fecha descendente.
    - $etiquetasPersona (array<int, string>): persona_id => nombre, solo de
      las personas presentes en la página actual (evita un JOIN en el
      listado — mismo criterio que OrdenesController::etiquetasContrato()).
    - $personasDisponibles (Collection<int, string>): id => nombre, para el
      <select> del filtro.
    - $filtros (array{persona_id: int|null, periodo: string}): valores
      aplicados, para dejar los campos con el valor tras el submit.
    - $resumen (array{total, cantidad, personas, mayor}): las cifras de la
      franja de KPI, con el MISMO filtro que la tabla
      (ListarAnticipos::resumen). Los montos son DECIMAL sumados con
      BigDecimal: la vista solo los formatea (`FormatoMonto`, sin `float`),
      nunca calcula con ellos (invariante 6).
    - $puedeEliminar (bool): gatea el botón "Eliminar" por fila.

    Gateada por `finanzas.anticipo.ver`, verificado server-side en el
    controlador. El botón "Nuevo anticipo" y "Eliminar" se ocultan con
    `@puede` o `$puedeEliminar` (presentación, no autorización — el servidor
    revalida en AnticiposController).

    Estilos en resources/css/pages/anticipos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
<x-templates.panel-shell :title="__('finanzas.anticipos.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.anticipos.titulo')"
    >
        <div class="ag-anticipos">
            <x-organisms.page-header
                :title="__('finanzas.anticipos.titulo')"
                :subtitle="__('finanzas.anticipos.subtitulo')"
            >
                @puede('finanzas.anticipo.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.anticipos.create')" variant="primary" icon="add">
                            {{ __('finanzas.anticipos.nueva') }}
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
                $filtrosActivosCount = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
            @endphp

            {{-- KPI del listado: franja fija bajo la cabecera (plan §3.6). Responden
                 al filtro aplicado. Una cifra en cero va sin color. --}}
            @if ($hayFiltrosActivos || $anticipos->isNotEmpty())
                <div class="ag-anticipos__kpis">
                    <x-molecules.stat-card
                        :label="__('finanzas.anticipos.kpi_total')"
                        icon="payments"
                        :value="FormatoMonto::decimal($resumen['total'])"
                        :value-suffix="__('finanzas.anticipos.unidad_moneda')"
                        :state="$resumen['cantidad'] > 0 ? 'info' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.anticipos.kpi_cantidad')"
                        icon="receipt_long"
                        :value="$resumen['cantidad']"
                        :state="$resumen['cantidad'] > 0 ? 'distintivo-1' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.anticipos.kpi_personas')"
                        icon="groups"
                        :value="$resumen['personas']"
                        :foot="__('finanzas.anticipos.kpi_personas_pie')"
                        :state="$resumen['personas'] > 0 ? 'success' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.anticipos.kpi_mayor')"
                        icon="trending_up"
                        :value="FormatoMonto::decimal($resumen['mayor'])"
                        :value-suffix="__('finanzas.anticipos.unidad_moneda')"
                        :foot="__('finanzas.anticipos.kpi_mayor_pie')"
                        :state="$resumen['cantidad'] > 0 ? 'distintivo-2' : null"
                    />
                </div>
            @endif

            @if ($hayFiltrosActivos || $anticipos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.anticipos.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <x-atoms.select
                            name="persona_id"
                            id="filtro-persona"
                            :label="__('finanzas.anticipos.filtro_persona')"
                            :options="$personasDisponibles"
                            :value="(string) $filtros['persona_id']"
                            :placeholder="__('finanzas.anticipos.filtro_persona_placeholder')"
                        />

                        <x-atoms.input
                            type="month"
                            name="periodo"
                            id="filtro-periodo"
                            :label="__('finanzas.anticipos.filtro_periodo')"
                            :value="$filtros['periodo']"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($anticipos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('finanzas.anticipos.filtro_vacio_titulo')"
                        :detail="__('finanzas.anticipos.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="payments"
                        :title="__('finanzas.anticipos.vacio_titulo')"
                        :detail="__('finanzas.anticipos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table :columns="'3rem minmax(0, 1.4fr) minmax(0, 0.9fr) minmax(0, 1fr) minmax(0, 1.8fr)'.($puedeEliminar ? ' var(--ag-row-actions-width)' : '')">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('finanzas.anticipos.col_persona') }}</span>
                        <span role="columnheader">{{ __('finanzas.anticipos.col_fecha') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.anticipos.col_monto') }}</span>
                        <span role="columnheader">{{ __('finanzas.anticipos.col_motivo') }}</span>
                        @if ($puedeEliminar)
                            <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                        @endif
                    </x-slot:head>

                    @foreach ($anticipos as $anticipo)
                        @php
                            $formIdEliminar = "anticipo-eliminar-{$anticipo->id}";
                            $modalIdEliminar = "anticipo-eliminar-modal-{$anticipo->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($anticipos->currentPage() - 1) * $anticipos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-anticipos__persona">{{ $etiquetasPersona[$anticipo->persona_id] ?? "#{$anticipo->persona_id}" }}</span>
                            <span role="cell" class="ag-index-table__mono">{{ $anticipo->fecha->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.anticipos.monto_valor', ['monto' => FormatoMonto::decimal($anticipo->monto)]) }}</span>
                            <span role="cell">
                                @if ($anticipo->motivo !== null)
                                    {{ $anticipo->motivo }}
                                @else
                                    <span class="ag-anticipos__atenuado">{{ __('finanzas.anticipos.sin_motivo') }}</span>
                                @endif
                            </span>

                            @if ($puedeEliminar)
                                <span role="cell" class="ag-index-table__acciones">
                                    {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                         repite su slot dos veces (visible/menú, ver su docblock),
                                         así que un <form> o un modal con id ahí adentro se
                                         duplicaría — y el que cae dentro del menú ⋮ queda oculto
                                         con él y nunca abre. El disparador sí va adentro (es un
                                         botón sin id propio). Mismo criterio que repuestos/index. --}}
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.anticipos.destroy', $anticipo) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('finanzas.anticipos.confirmar_baja_titulo')"
                                        :message="__('finanzas.anticipos.confirmar_baja')"
                                        :confirm-label="__('finanzas.anticipos.eliminar_accion')"
                                    />

                                    <x-organisms.row-actions>
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdEliminar"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('finanzas.anticipos.eliminar_accion') }}
                                        </x-atoms.button>
                                    </x-organisms.row-actions>
                                </span>
                            @endif
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$anticipos" :aria-label="__('finanzas.anticipos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
