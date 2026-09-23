{{--
    Page: rendiciones/show (GET /panel/rendiciones/{rendicion}, panel.rendiciones.show)
    Detalle de una rendición (HU-34, tarea 48); homogeneizada al arquetipo
    Detalle en la tarea 126 (§6.4 de docs/diseno/guia_pantalla_panel.md,
    mismo molde que `finanzas::pages.planillas.show`). Presentar y Aprobar son
    las dos transiciones de `TransicionesRendicion`, cada una en la cabecera
    con `confirm-modal` — la confirmación nativa del navegador desaparece.

    "Gastos disponibles para asociar" se dibuja SIEMPRE (guía §6.3.5: una
    sección no se esconde por el estado): si la rendición no está `abierta`,
    la sección se ve igual pero con un `empty-state` que explica por qué, en
    vez de la tabla.

    Datos esperados (ver RendicionesController::show()): la cáscara de
    CascaraPanel, más:
    - $rendicion (Rendicion).
    - $gastosAsociados (Collection<Gasto>): ordenados desc por fecha.
    - $gastosDisponibles (Collection<Gasto>): vacía si rendición no está
      Abierta; 100 primeros de la misma base, sin rendicion_id.
    - $etiquetasBase / $etiquetasJefeCampo (array<int, string>).
    - $creadaPorNombre (?string): usuario de `sec_user`.
    - $personaId (int|null): persona del usuario autenticado — para decidir
      si mostrar el aviso "quien rindió no aprueba".
    - $vinculos (list), $actividad (list).
    - $tonoPorEstado (array<string, string>).
    - $puedeCrear / $puedePresentar / $puedeAprobar (bool): permisos.
    - $puedeEditarEsta (bool, tarea 134): permiso `finanzas.rendicion.presentar`
      (reusado) Y la rendición sigue `Abierta` (`Dominio/PoliticaEdicionRendicion`).

    Gateada por `finanzas.rendicion.ver`. Los botones "Editar", "Presentar" y
    "Aprobar" se ocultan según permiso y estado; el backend revalida con
    `abort(403)` (o redirige, en el caso de "Editar") si se fuerza.

    Estilos en resources/css/pages/rendiciones.css y
    resources/css/pages/detalle.css (arquetipo Detalle) — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
@php
    $estadoValor = $rendicion->estado->value;
    $esJefeCampo = $personaId === (int) $rendicion->jefe_campo_id;
    $puedePresentarEsta = $puedePresentar && $estadoValor === 'abierta' && $gastosAsociados->isNotEmpty();
    $puedeAprobarEsta = $puedeAprobar && $estadoValor === 'presentada' && ! $esJefeCampo;
    $jefeCampoLabel = $etiquetasJefeCampo[$rendicion->jefe_campo_id] ?? '#'.$rendicion->jefe_campo_id;
    $baseLabel = $etiquetasBase[$rendicion->base_id] ?? '#'.$rendicion->base_id;
@endphp
<x-templates.panel-shell :title="__('finanzas.rendiciones.detalle_titulo', ['id' => $rendicion->id])" :tema="$tema">
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
        :vista-actual="__('finanzas.rendiciones.detalle_titulo', ['id' => $rendicion->id])"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="__('finanzas.rendiciones.detalle_titulo', ['id' => $rendicion->id])"
                :subtitle="__('finanzas.rendiciones.detalle_subtitulo', ['jefe' => $jefeCampoLabel, 'base' => $baseLabel])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">
                        {{ __('finanzas.rendiciones.estado.'.$estadoValor) }}
                    </x-atoms.badge>
                </x-slot:chip>

                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.rendiciones.index')" :label="__('finanzas.rendiciones.volver')" />

                    @if ($puedeEditarEsta)
                        <x-atoms.button :href="route('panel.rendiciones.edit', $rendicion)" variant="warning-outline" icon="edit">
                            {{ __('finanzas.rendiciones.editar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedePresentarEsta)
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#rendicion-presentar-modal"
                            :variant="$tonoPorEstado['presentada'].'-outline'"
                            icon="send"
                        >
                            {{ __('finanzas.rendiciones.presentar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeAprobarEsta)
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#rendicion-aprobar-modal"
                            :variant="$tonoPorEstado['aprobada'].'-outline'"
                            icon="check_circle"
                        >
                            {{ __('finanzas.rendiciones.aprobar_accion') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedePresentarEsta)
                <form id="rendicion-presentar" method="POST" action="{{ route('panel.rendiciones.presentar', $rendicion) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="rendicion-presentar-modal"
                    form-id="rendicion-presentar"
                    :title="__('finanzas.rendiciones.confirmar_presentar_titulo')"
                    :message="__('finanzas.rendiciones.confirmar_presentar')"
                    :confirm-label="__('finanzas.rendiciones.presentar_accion')"
                    :tone="$tonoPorEstado['presentada']"
                    modal-icon="send"
                >
                    @include('finanzas::pages.rendiciones._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'presentada', 'tonoPorEstado' => $tonoPorEstado])
                </x-molecules.confirm-modal>
            @endif

            @if ($puedeAprobarEsta)
                <form id="rendicion-aprobar" method="POST" action="{{ route('panel.rendiciones.aprobar', $rendicion) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="rendicion-aprobar-modal"
                    form-id="rendicion-aprobar"
                    :title="__('finanzas.rendiciones.confirmar_aprobar_titulo')"
                    :message="__('finanzas.rendiciones.confirmar_aprobar')"
                    :confirm-label="__('finanzas.rendiciones.aprobar_accion')"
                    :tone="$tonoPorEstado['aprobada']"
                >
                    @include('finanzas::pages.rendiciones._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'aprobada', 'tonoPorEstado' => $tonoPorEstado])
                </x-molecules.confirm-modal>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('gasto'))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('gasto') }}
                </x-molecules.alert-strip>
            @endif

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($puedePresentar && $estadoValor === 'abierta' && $gastosAsociados->isEmpty())
                <x-molecules.alert-strip variant="info" icon="info">
                    {{ __('finanzas.rendiciones.no_puede_presentar_sin_gastos') }}
                </x-molecules.alert-strip>
            @endif

            @if ($esJefeCampo && $puedeAprobar)
                <x-molecules.alert-strip variant="warning" icon="warning">
                    {{ __('finanzas.rendiciones.no_puede_aprobar_propia') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('finanzas.rendiciones.kpi_total_rendido')"
                    icon="payments"
                    :value="FormatoMonto::decimal($rendicion->monto)"
                    :value-suffix="__('finanzas.rendiciones.unidad_moneda')"
                    :state="$rendicion->monto !== '0.00' ? 'info' : null"
                />
                <x-molecules.stat-card
                    :label="__('finanzas.rendiciones.kpi_gastos')"
                    icon="receipt_long"
                    :value="$gastosAsociados->count()"
                />
                <x-molecules.stat-card
                    :label="__('finanzas.rendiciones.col_base')"
                    icon="holiday_village"
                    :value="$baseLabel"
                />
                <x-molecules.stat-card
                    :label="__('finanzas.rendiciones.col_jefe_campo')"
                    icon="person"
                    :value="$jefeCampoLabel"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('finanzas.rendiciones.gastos_asociados_titulo')" :count="(string) $gastosAsociados->count()">
                    <div class="ag-form-section__field--full">
                        @if ($gastosAsociados->isEmpty())
                            <x-molecules.empty-state
                                icon="receipt_long"
                                :title="__('finanzas.rendiciones.gastos_asociados_vacio_titulo')"
                                :detail="__('finanzas.rendiciones.gastos_asociados_vacio')"
                            />
                        @else
                            <x-molecules.index-table columns="1fr 1.4fr 1fr">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('finanzas.gastos.col_fecha') }}</span>
                                    <span role="columnheader">{{ __('finanzas.gastos.col_rubro') }}</span>
                                    <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.rendiciones.col_monto') }}</span>
                                </x-slot:head>

                                @foreach ($gastosAsociados as $gasto)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell" class="ag-index-table__mono">{{ $gasto->fecha->format('d/m/Y') }}</span>
                                        <span role="cell">{{ optional($gasto->rubro)->nombre ?? "#{$gasto->rubro_id}" }}</span>
                                        <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.rendiciones.monto_valor', ['monto' => FormatoMonto::decimal($gasto->monto)]) }}</span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('finanzas.rendiciones.gastos_disponibles_titulo')" :count="(string) $gastosDisponibles->count()">
                    <div class="ag-form-section__field--full">
                        @if ($estadoValor !== 'abierta')
                            <x-molecules.empty-state
                                icon="lock_clock"
                                :title="__('finanzas.rendiciones.gastos_disponibles_no_abierta_titulo')"
                                :detail="__('finanzas.rendiciones.gastos_disponibles_no_abierta_detalle', ['estado' => __('finanzas.rendiciones.estado.'.$estadoValor)])"
                            />
                        @elseif ($gastosDisponibles->isEmpty())
                            <x-molecules.empty-state
                                icon="receipt_long"
                                :title="__('finanzas.rendiciones.gastos_disponibles_vacio_titulo')"
                                :detail="__('finanzas.rendiciones.gastos_disponibles_vacio')"
                            />
                        @else
                            <x-molecules.index-table columns="1fr 1.4fr 1fr 8rem">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('finanzas.gastos.col_fecha') }}</span>
                                    <span role="columnheader">{{ __('finanzas.gastos.col_rubro') }}</span>
                                    <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.rendiciones.col_monto') }}</span>
                                    <span role="columnheader" aria-hidden="true"></span>
                                </x-slot:head>

                                @foreach ($gastosDisponibles as $gasto)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell" class="ag-index-table__mono">{{ $gasto->fecha->format('d/m/Y') }}</span>
                                        <span role="cell">{{ optional($gasto->rubro)->nombre ?? "#{$gasto->rubro_id}" }}</span>
                                        <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.rendiciones.monto_valor', ['monto' => FormatoMonto::decimal($gasto->monto)]) }}</span>

                                        <span role="cell">
                                            @if ($puedeCrear)
                                                <form id="rendicion-asociar-{{ $gasto->id }}" method="POST" action="{{ route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto]) }}" hidden>
                                                    @csrf
                                                </form>

                                                <x-molecules.confirm-modal
                                                    id="rendicion-asociar-modal-{{ $gasto->id }}"
                                                    form-id="rendicion-asociar-{{ $gasto->id }}"
                                                    :title="__('finanzas.rendiciones.confirmar_asociar_titulo')"
                                                    :message="__('finanzas.rendiciones.confirmar_asociar', ['monto' => FormatoMonto::decimal($gasto->monto)])"
                                                    :confirm-label="__('finanzas.rendiciones.asociar_accion')"
                                                    tone="info"
                                                    modal-icon="add_circle"
                                                />

                                                <x-atoms.button
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rendicion-asociar-modal-{{ $gasto->id }}"
                                                    variant="outline"
                                                    size="sm"
                                                    icon="add_circle"
                                                >
                                                    {{ __('finanzas.rendiciones.asociar_accion') }}
                                                </x-atoms.button>
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
                        :title="__('finanzas.rendiciones.aside_datos_titulo')"
                        :items="[
                            ['label' => __('finanzas.rendiciones.aside_fecha'), 'value' => $rendicion->fecha->format('d/m/Y'), 'mono' => true],
                            ...($rendicion->descripcion !== null ? [['label' => __('finanzas.rendiciones.aside_descripcion'), 'value' => $rendicion->descripcion]] : []),
                            ['label' => __('finanzas.rendiciones.aside_creada_por'), 'value' => $creadaPorNombre ?? '—'],
                        ]"
                    />

                    @if (count($vinculos))
                        <x-molecules.form-section accent="alert" :title="__('finanzas.rendiciones.seccion_vinculos')">
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

                    <x-molecules.form-section accent="distintivo-2" :title="__('finanzas.rendiciones.seccion_actividad')">
                        <div class="ag-form-section__field--full">
                            <x-molecules.timeline :items="$actividad" />
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
