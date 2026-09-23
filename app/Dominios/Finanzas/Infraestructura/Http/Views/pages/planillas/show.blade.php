{{--
    Page: planillas/show (GET /panel/planillas/{planilla}, panel.planillas.show)
    Detalle de una planilla del período (HU-30, tarea 44); homogeneizada al
    arquetipo Detalle en la tarea 126 (§6.4 de
    docs/diseno/guia_pantalla_panel.md, mismo molde que
    `operaciones::pages.trabajos.show`). Aprobar es la única transición de la
    máquina (`TransicionesPlanilla`), en la cabecera con `confirm-modal` — la
    confirmación nativa del navegador desaparece.

    Zona de dinero (CLAUDE.md): ni una suma nueva acá. `devengado`/
    `anticipos`/`neto` son el snapshot que ya persistió `GenerarPlanilla`;
    `devengosIncluidos` es un `count()` de filas, no un monto.

    Datos esperados (ver PlanillasController::show()): la cáscara de
    CascaraPanel, más:
    - $planilla (Planilla), $detalles (Collection<PlanillaDetalle>): persona
      ascendente.
    - $etiquetasPersona (array<int, string>).
    - $generadaPorNombre / $aprobadaPorNombre (?string): usuarios de
      `sec_user`, no personas.
    - $devengosIncluidos (int): cuántos `fin_devengos_personal` pagables caen
      en el mismo período.
    - $vinculos (list), $actividad (list).
    - $tonoPorEstado (array<string, string>).
    - $puedeAprobar (bool): gatea el botón "Aprobar" (además de que la
      planilla esté en borrador — las dos condiciones, no una sola).

    Gateada por `finanzas.planilla.ver`; el botón de aprobar se oculta con
    `@if` (presentación, no autorización — el servidor revalida
    `finanzas.planilla.aprobar` en `PlanillasController::aprobar()`).

    Estilos en resources/css/pages/planilla.css y
    resources/css/pages/detalle.css (arquetipo Detalle) — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
@php
    $estadoValor = $planilla->estado->value;
    $puedeAprobarEsta = $puedeAprobar && $estadoValor === 'borrador';
@endphp
<x-templates.panel-shell :title="__('finanzas.planillas.detalle_titulo', ['periodo' => $planilla->periodo])" :tema="$tema">
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
        :vista-actual="__('finanzas.planillas.detalle_titulo', ['periodo' => $planilla->periodo])"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="__('finanzas.planillas.detalle_titulo', ['periodo' => $planilla->periodo])"
                :subtitle="trans_choice('finanzas.planillas.detalle_subtitulo', $detalles->count(), ['cantidad' => $detalles->count()])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">
                        {{ __('finanzas.planillas.estado.'.$estadoValor) }}
                    </x-atoms.badge>
                </x-slot:chip>

                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.planillas.index')" :label="__('finanzas.planillas.volver')" />

                    @if ($puedeAprobarEsta)
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#planilla-aprobar-modal"
                            :variant="$tonoPorEstado['aprobada'].'-outline'"
                            icon="check_circle"
                        >
                            {{ __('finanzas.planillas.aprobar_accion') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeAprobarEsta)
                <form id="planilla-aprobar" method="POST" action="{{ route('panel.planillas.aprobar', $planilla) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="planilla-aprobar-modal"
                    form-id="planilla-aprobar"
                    :title="__('finanzas.planillas.confirmar_aprobar_titulo')"
                    :message="__('finanzas.planillas.confirmar_aprobar')"
                    :confirm-label="__('finanzas.planillas.aprobar_accion')"
                    :tone="$tonoPorEstado['aprobada']"
                >
                    @include('finanzas::pages.planillas._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'aprobada', 'tonoPorEstado' => $tonoPorEstado])
                </x-molecules.confirm-modal>
            @endif

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first() }}
                </x-molecules.alert-strip>
            @endif

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('finanzas.planillas.kpi_total')"
                    icon="payments"
                    :value="FormatoMonto::decimal($planilla->total)"
                    :value-suffix="__('finanzas.planillas.unidad_moneda')"
                    :state="$planilla->total !== '0.00' ? 'info' : null"
                />
                <x-molecules.stat-card
                    :label="__('finanzas.planillas.kpi_personas')"
                    icon="groups"
                    :value="$detalles->count()"
                />
                <x-molecules.stat-card
                    :label="__('finanzas.planillas.kpi_devengos_incluidos')"
                    icon="fact_check"
                    :value="$devengosIncluidos"
                />
                <x-molecules.stat-card
                    :label="__('finanzas.planillas.kpi_periodo')"
                    icon="calendar_month"
                    :value="$planilla->periodo"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('finanzas.planillas.seccion_renglones')" :count="trans_choice('finanzas.planillas.campos_contador', $detalles->count(), ['cantidad' => $detalles->count()])">
                    <div class="ag-form-section__field--full">
                        @if ($detalles->isEmpty())
                            <x-molecules.empty-state
                                icon="event_note"
                                :title="__('finanzas.planillas.detalle_vacio_titulo')"
                                :detail="__('finanzas.planillas.detalle_vacio')"
                            />
                        @else
                            <x-molecules.index-table columns="1.6fr 1fr 1fr 1fr 1fr">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('finanzas.planillas.col_persona') }}</span>
                                    <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.planillas.col_devengado') }}</span>
                                    <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.planillas.col_anticipos') }}</span>
                                    <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.planillas.col_neto') }}</span>
                                    <span role="columnheader">{{ __('finanzas.planillas.col_recibo') }}</span>
                                </x-slot:head>

                                @foreach ($detalles as $detalle)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $etiquetasPersona[$detalle->persona_id] ?? "#{$detalle->persona_id}" }}</span>
                                        <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.planillas.monto_valor', ['monto' => FormatoMonto::decimal($detalle->devengado)]) }}</span>
                                        <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.planillas.monto_valor', ['monto' => FormatoMonto::decimal($detalle->anticipos)]) }}</span>
                                        <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.planillas.monto_valor', ['monto' => FormatoMonto::decimal($detalle->neto)]) }}</span>
                                        <span role="cell">
                                            @if ($detalle->pdf_path !== null)
                                                <x-atoms.button :href="route('panel.planillas.recibo', [$planilla, $detalle])" variant="info-outline" size="sm" icon="picture_as_pdf">
                                                    {{ __('finanzas.planillas.ver_recibo') }}
                                                </x-atoms.button>
                                            @else
                                                <span class="ag-detalle__campo-label">{{ __('finanzas.planillas.recibo_pendiente') }}</span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.summary-card
                        :title="__('finanzas.planillas.aside_datos_titulo')"
                        :items="[
                            ['label' => __('finanzas.planillas.aside_periodo'), 'value' => $planilla->periodo, 'mono' => true],
                            ['label' => __('finanzas.planillas.aside_generada_por'), 'value' => $generadaPorNombre ?? '—'],
                            ['label' => __('finanzas.planillas.aside_aprobacion'), 'value' => $planilla->aprobada_en !== null
                                ? __('finanzas.planillas.detalle_aprobada_por', ['usuario' => $aprobadaPorNombre ?? '—', 'fecha' => $planilla->aprobada_en->format('d/m/Y H:i')])
                                : __('finanzas.planillas.sin_aprobacion')],
                        ]"
                    />

                    @if (count($vinculos))
                        <x-molecules.form-section accent="alert" :title="__('finanzas.planillas.seccion_vinculos')">
                            <div class="ag-form-section__field--full ag-detalle__vinculos">
                                @foreach ($vinculos as $vinculo)
                                    <x-molecules.link-row
                                        :href="$vinculo['href']"
                                        :icon="$vinculo['icon']"
                                        :title="$vinculo['title']"
                                        :meta="$vinculo['meta']"
                                        :tone="$vinculo['tone']"
                                    />
                                @endforeach
                            </div>
                        </x-molecules.form-section>
                    @endif

                    <x-molecules.form-section accent="distintivo-2" :title="__('finanzas.planillas.seccion_actividad')">
                        <div class="ag-form-section__field--full">
                            <x-molecules.timeline :items="$actividad" />
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
