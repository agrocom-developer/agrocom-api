{{--
    Page: planillas/index (GET /panel/planillas, panel.planillas.index)
    Listado de planillas del período (HU-30, tarea 44): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → KPI → toolbar →
    tabla → paginación. Homogeneizado en la tarea 119 con el patrón de
    Contratos: franja de KPI, `filter-panel`, `index-table`, `row-actions` y
    `confirm-modal` con la ficha «estado actual → destino».

    Una planilla tiene máquina de estados (`TransicionesPlanilla`:
    borrador → aprobada) pero NO ficha de edición: no lleva pasos. Lo que sí
    lleva es el badge del listado y la acción de fila que la aprueba, ambos
    con el tono de `PlanillasController::TONO_POR_ESTADO` — definido una sola
    vez y compartido con el modal (plan §3.1).

    "Generar planilla" es el alta de esta pantalla y vive en la cabecera,
    como en todo listado del panel. No hay pantalla de alta: el único campo
    (el período) viaja dentro del propio modal de confirmación, asociado al
    `<form>` de afuera por el atributo HTML `form` — el mecanismo que
    `molecules/confirm-modal` ya ofrece para los campos que acompañan una
    confirmación. Antes era una tarjeta con su propio grid entre la cabecera
    y la tabla.

    Datos esperados (ver PlanillasController::index()): la cáscara de
    CascaraPanel, más:
    - $planillas (LengthAwarePaginator<Planilla>): período descendente.
    - $resumen (array{total, cantidad, borradores, aprobadas}): las cifras de
      la franja de KPI, con el MISMO filtro que la tabla
      (ListarPlanillas::resumen). El monto es DECIMAL sumado con BigDecimal:
      la vista solo lo formatea (`FormatoMonto`, sin `float`), nunca calcula
      con él (invariante 6).
    - $filtros (array{estado}): valor aplicado, para dejar el campo con el
      valor tras el submit.
    - $tonoPorEstado (array<string, string>): estado → tono del badge.
    - $puedeGenerar (bool): gatea "Generar planilla".
    - $puedeAprobar (bool): gatea la acción de fila "Aprobar planilla".

    Gateada por `finanzas.planilla.ver`, verificado server-side en el
    controlador. Generar y aprobar se ocultan con `$puedeGenerar`/
    `$puedeAprobar` (presentación, no autorización — el servidor revalida en
    PlanillasController).

    Estilos en resources/css/pages/planilla.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
<x-templates.panel-shell :title="__('finanzas.planillas.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.planillas.titulo')"
    >
        <div class="ag-planillas">
            <x-organisms.page-header
                :title="__('finanzas.planillas.titulo')"
                :subtitle="__('finanzas.planillas.subtitulo')"
            >
                @if ($puedeGenerar)
                    <x-slot:actions>
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#planilla-generar-modal"
                            variant="primary"
                            icon="playlist_add_check"
                        >
                            {{ __('finanzas.planillas.generar_titulo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('periodo'))
                {{-- El modal se cerró al enviar: el error del período se ve acá,
                     además de dentro del campo cuando se lo vuelve a abrir. --}}
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('periodo') }}
                </x-molecules.alert-strip>
            @endif

            @if ($puedeGenerar)
                <form id="planilla-generar" method="POST" action="{{ route('panel.planillas.store') }}">
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="planilla-generar-modal"
                    form-id="planilla-generar"
                    :title="__('finanzas.planillas.generar_titulo')"
                    :message="__('finanzas.planillas.generar_ayuda')"
                    :confirm-label="__('finanzas.planillas.generar_boton')"
                    tone="info"
                    modal-icon="playlist_add_check"
                >
                    <x-atoms.input
                        type="month"
                        name="periodo"
                        id="planilla-periodo"
                        form="planilla-generar"
                        :label="__('finanzas.planillas.campo_periodo')"
                        :value="old('periodo')"
                        :error="$errors->first('periodo')"
                        required
                    />
                </x-molecules.confirm-modal>
            @endif

            @php
                $hayFiltroActivo = $filtros['estado'] !== null && $filtros['estado'] !== '';
            @endphp

            {{-- KPI del listado (plan §3.6): responden al filtro aplicado, así que
                 el monto de arriba es la suma exacta de la columna de abajo. Una
                 cifra en cero va sin color. --}}
            @if ($hayFiltroActivo || $planillas->isNotEmpty())
                <div class="ag-planillas__kpis">
                    <x-molecules.stat-card
                        :label="__('finanzas.planillas.kpi_total')"
                        icon="payments"
                        :value="FormatoMonto::decimal($resumen['total'])"
                        :value-suffix="__('finanzas.planillas.unidad_moneda')"
                        :state="$resumen['cantidad'] > 0 ? 'info' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.planillas.kpi_cantidad')"
                        icon="event_note"
                        :value="$resumen['cantidad']"
                        :state="$resumen['cantidad'] > 0 ? 'distintivo-1' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.planillas.kpi_borradores')"
                        icon="edit_note"
                        :value="$resumen['borradores']"
                        :foot="__('finanzas.planillas.kpi_borradores_pie')"
                        :state="$resumen['borradores'] > 0 ? 'warning' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.planillas.kpi_aprobadas')"
                        icon="task_alt"
                        :value="$resumen['aprobadas']"
                        :foot="__('finanzas.planillas.kpi_aprobadas_pie')"
                        :state="$resumen['aprobadas'] > 0 ? 'success' : null"
                    />
                </div>

                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.planillas.index')"
                        :active-count="$hayFiltroActivo ? 1 : 0"
                    >
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('finanzas.planillas.filtro_estado')"
                            :options="collect([
                                'borrador' => __('finanzas.planillas.estado.borrador'),
                                'aprobada' => __('finanzas.planillas.estado.aprobada'),
                            ])"
                            :value="$filtros['estado']"
                            :placeholder="__('finanzas.planillas.filtro_estado_placeholder')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($planillas->isEmpty())
                @if ($hayFiltroActivo)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('finanzas.planillas.filtro_vacio_titulo')"
                        :detail="__('finanzas.planillas.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="event_note"
                        :title="__('finanzas.planillas.vacio_titulo')"
                        :detail="__('finanzas.planillas.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.2fr) minmax(0, 1.2fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_periodo') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.planillas.col_total') }}</span>
                        <span role="columnheader">{{ __('finanzas.planillas.col_aprobacion') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($planillas as $planilla)
                        @php
                            $estadoValor = $planilla->estado->value;
                            $formIdAprobar = "planilla-aprobar-{$planilla->id}";
                            $modalIdAprobar = "planilla-aprobar-modal-{$planilla->id}";
                            // La acción de fila es la ÚNICA transición de la máquina
                            // (borrador → aprobada, `TransicionesPlanilla`): no se
                            // ofrece ninguna que el servidor vaya a rechazar.
                            $puedeAprobarEsta = $puedeAprobar && $estadoValor === 'borrador';
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($planillas->currentPage() - 1) * $planillas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-index-table__mono">{{ $planilla->periodo }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">
                                    {{ __('finanzas.planillas.estado.'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.planillas.monto_valor', ['monto' => FormatoMonto::decimal($planilla->total)]) }}</span>
                            <span role="cell" class="ag-index-table__mono">
                                {{ $planilla->aprobada_en?->format('d/m/Y') ?? __('finanzas.planillas.sin_aprobacion') }}
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock),
                                     así que un <form> o un modal con id ahí adentro se
                                     duplicaría — y el que cae dentro del menú ⋮ queda oculto
                                     con él y nunca abre. El disparador sí va adentro (es un
                                     botón sin id propio). Mismo criterio que contratos/index. --}}
                                @if ($puedeAprobarEsta)
                                    <form id="{{ $formIdAprobar }}" method="POST" action="{{ route('panel.planillas.aprobar', $planilla) }}">
                                        @csrf
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdAprobar"
                                        :form-id="$formIdAprobar"
                                        :title="__('finanzas.planillas.confirmar_aprobar_titulo')"
                                        :message="__('finanzas.planillas.confirmar_aprobar')"
                                        :confirm-label="__('finanzas.planillas.aprobar_accion')"
                                        :tone="$tonoPorEstado['aprobada']"
                                    >
                                        @include('finanzas::pages.planillas._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'aprobada'])
                                    </x-molecules.confirm-modal>
                                @endif

                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.planillas.show', $planilla)" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('finanzas.planillas.ver_accion') }}
                                    </x-atoms.button>

                                    @if ($puedeAprobarEsta)
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdAprobar"
                                            variant="success-outline"
                                            size="sm"
                                            icon="check_circle"
                                        >
                                            {{ __('finanzas.planillas.aprobar_accion') }}
                                        </x-atoms.button>
                                    @endif
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$planillas" :aria-label="__('finanzas.planillas.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
