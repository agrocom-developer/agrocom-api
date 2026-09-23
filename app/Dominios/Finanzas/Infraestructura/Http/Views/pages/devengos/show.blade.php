{{--
    Page: devengos/show (GET /panel/devengos/{persona}, panel.devengos.show)
    "Como piloto o auxiliar, quiero ver mis devengos por período" (HU-28,
    tarea 40); homogeneizada al arquetipo Detalle en la tarea 126 (§6.4 de
    docs/diseno/guia_pantalla_panel.md). Única ficha SIN objeto padre: el
    objeto es la persona autenticada — el `abort_unless`/`abort_if` de
    `DevengosController::show()` que ata `{persona}` al usuario logueado NO
    se tocó (invariante de exposición entre usuarios, CLAUDE.md §Testing).

    Sin más acción que "Volver" (nada que editar/cambiar de estado — el
    `boton-volver` cae al tablero, único destino con sentido cuando la
    pantalla no cuelga de ningún listado) y sin "Actividad" en el aside (no
    hay más eventos que reconstruir que los propios renglones, ya listados
    en la tabla) — mismo criterio que `personal::pages.personas.desempeno`,
    la otra ficha sin timeline.

    Zona de dinero (CLAUDE.md): ni una suma nueva acá. `$total` ya lo suma
    `ListarDevengosPersona` con `Brick\Math\BigDecimal`; el KPI de cantidad es
    un `count()` de la colección ya cargada, no un monto.

    Datos esperados (ver DevengosController::show()): la cáscara de
    CascaraPanel, más:
    - $personaId (int): la persona autenticada.
    - $nombrePersona (string).
    - $devengos (Collection<DevengoPersonal>): del período filtrado, fecha
      ascendente.
    - $total (string decimal): suma exacta del período.
    - $periodoFiltro (string, formato YYYY-MM): período efectivamente
      aplicado.
    - $vinculos (list): aside "Relacionado" — anticipos de esta persona, si
      hay permiso.

    Gateada por `finanzas.devengo.ver`. Sin `@puede` acá: no hay acciones que
    ocultar, la pantalla entera ya está detrás del `abort_unless` del
    controlador.

    Estilos en resources/css/pages/detalle.css (arquetipo Detalle) — cero
    color hardcodeado (CLAUDE.md invariante 11). `devengos.css` quedó sin
    reglas propias: lo resuelve el catálogo.
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
@php
    $periodoEsMesActual = $periodoFiltro === now()->format('Y-m');
@endphp
<x-templates.panel-shell :title="__('finanzas.devengos.detalle_titulo', ['persona' => $nombrePersona])" :tema="$tema">
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
        :vista-actual="__('finanzas.devengos.titulo')"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="__('finanzas.devengos.detalle_titulo', ['persona' => $nombrePersona])"
                :subtitle="__('finanzas.devengos.detalle_subtitulo', ['periodo' => $periodoFiltro])"
            >
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.dashboard')" :label="__('finanzas.devengos.volver')" />
                </x-slot:actions>
            </x-organisms.page-header>

            <div class="ag-table-toolbar">
                <x-organisms.filter-panel
                    :action="route('panel.devengos.show', $personaId)"
                    :active-count="$periodoEsMesActual ? 0 : 1"
                    :trigger-label="__('finanzas.devengos.filtro_periodo')"
                >
                    <x-atoms.input
                        type="month"
                        name="periodo"
                        id="filtro-periodo"
                        :label="__('finanzas.devengos.filtro_periodo')"
                        :value="$periodoFiltro"
                    />
                </x-organisms.filter-panel>
            </div>

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('finanzas.devengos.total')"
                    icon="payments"
                    :value="FormatoMonto::decimal($total)"
                    :value-suffix="__('finanzas.anticipos.unidad_moneda')"
                    :state="$total !== '0.00' ? 'info' : null"
                />
                <x-molecules.stat-card
                    :label="__('finanzas.devengos.kpi_cantidad')"
                    icon="fact_check"
                    :value="$devengos->count()"
                />
                <x-molecules.stat-card
                    :label="__('finanzas.devengos.filtro_periodo')"
                    icon="calendar_month"
                    :value="$periodoFiltro"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('finanzas.devengos.titulo')" :count="(string) $devengos->count()">
                    <div class="ag-form-section__field--full">
                        @if ($devengos->isEmpty())
                            <x-molecules.empty-state
                                icon="request_quote"
                                :title="__('finanzas.devengos.vacio_titulo')"
                                :detail="__('finanzas.devengos.vacio')"
                            />
                        @else
                            <x-molecules.index-table columns="7rem 8rem 8rem 7rem 1fr 7rem">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('finanzas.devengos.col_fecha') }}</span>
                                    <span role="columnheader">{{ __('finanzas.devengos.col_trabajo') }}</span>
                                    <span role="columnheader">{{ __('finanzas.devengos.col_modalidad') }}</span>
                                    <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.devengos.col_hectareas') }}</span>
                                    <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.devengos.col_monto') }}</span>
                                    <span role="columnheader">{{ __('finanzas.devengos.col_absorbido') }}</span>
                                </x-slot:head>

                                @foreach ($devengos as $devengo)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell" class="ag-index-table__mono">{{ $devengo->fecha->format('d/m/Y') }}</span>
                                        <span role="cell" class="ag-index-table__mono">
                                            {{ $devengo->trabajo_id !== null ? __('finanzas.devengos.trabajo_id', ['id' => $devengo->trabajo_id]) : __('finanzas.devengos.sin_trabajo') }}
                                        </span>
                                        <span role="cell">{{ $devengo->modalidad->etiqueta() }}</span>
                                        <span role="cell" class="ag-index-table__cifra">{{ FormatoMonto::decimal($devengo->hectareas) }}</span>
                                        <span role="cell" class="ag-index-table__cifra">
                                            {{ $devengo->estaAbsorbido() ? '—' : __('finanzas.devengos.monto_valor', ['monto' => FormatoMonto::decimal($devengo->monto)]) }}
                                        </span>
                                        <span role="cell">
                                            @if ($devengo->estaAbsorbido())
                                                <x-atoms.badge variant="neutral">{{ __('finanzas.devengos.absorbido') }}</x-atoms.badge>
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>

                @if (count($vinculos))
                    <x-slot:aside>
                        <x-molecules.form-section accent="alert" :title="__('finanzas.devengos.seccion_vinculos')">
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
                    </x-slot:aside>
                @endif
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
