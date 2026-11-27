{{--
    Page: disputas/show (GET /panel/disputas/{disputa}, panel.disputas.show)
    Ficha de una disputa (ADR 0033 y 0039) — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y las acciones de
    soporte (tomar, resolver con resultado, reembolso y motivo), KPIs, los datos del
    reclamo y, al lado, la conversación y las resoluciones. Los reembolsos se
    liquidan entre las partes fuera de la aplicación (ADR 0034).

    Datos esperados (ver DisputasController::show()): la cáscara de CascaraPanel,
    más $disputa (Disputa), $servicio (?DatosServicio), $proveedor (?DatosProveedor),
    $conversacion y $resoluciones (list<array{title, meta, tone}>), $respondeAntesDe
    (string), $puedeTomar, $puedeMensajear y $puedeResolver (bool) y $resultados
    (list<ResultadoDisputa>).

    Gateada por `disputas.disputa.ver`, verificado server-side en el controlador;
    tomar y escribir exigen `disputas.disputa.atender` y resolver, `.resolver`.
--}}
@php
    $titulo = __('disputas.panel.detalle_titulo', ['id' => $disputa->id]);
    $opcionesResultado = collect($resultados)->mapWithKeys(fn ($opcion) => [$opcion->value => __('disputas.resultado.'.$opcion->value)])->all();
@endphp
<x-templates.panel-shell :title="$titulo" :tema="$tema">
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
        :vista-actual="$titulo"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="$titulo"
                :subtitle="__('disputas.panel.detalle_subtitulo', ['cliente' => $servicio->clienteNombre ?? '—', 'solicitud' => $disputa->solicitud_id])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$disputa->estado->tono()">{{ __('disputas.estado.'.$disputa->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.disputas.index')" :label="__('disputas.panel.volver')" />
                    <x-atoms.button :href="route('panel.solicitudes.show', $disputa->solicitud_id)" variant="info-outline" icon="request_quote">
                        {{ __('disputas.panel.ver_solicitud') }}
                    </x-atoms.button>
                    @if ($puedeTomar)
                        <form method="POST" action="{{ route('panel.disputas.tomar', $disputa->id) }}">
                            @csrf
                            <x-atoms.button type="submit" variant="info-outline" icon="assignment_ind">
                                {{ __('disputas.panel.tomar') }}
                            </x-atoms.button>
                        </form>
                    @endif
                    @if ($puedeResolver)
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#disputa-resolver-modal"
                            variant="success-outline"
                            icon="gavel"
                        >
                            {{ __('disputas.panel.resolver') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeResolver)
                <form id="disputa-resolver-form" method="POST" action="{{ route('panel.disputas.resolver', $disputa->id) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="disputa-resolver-modal"
                    form-id="disputa-resolver-form"
                    :title="__('disputas.panel.resolver_titulo', ['id' => $disputa->id])"
                    :message="__('disputas.panel.resolver_mensaje')"
                    :confirm-label="__('disputas.panel.resolver')"
                    tone="success"
                >
                    <x-atoms.select
                        name="resultado"
                        id="disputa-resultado"
                        form="disputa-resolver-form"
                        :label="__('disputas.panel.resultado_etiqueta')"
                        :options="$opcionesResultado"
                        :value="old('resultado')"
                        :placeholder="__('disputas.panel.resultado_placeholder')"
                        required
                    />
                    <x-atoms.input
                        name="monto_reembolso"
                        id="disputa-reembolso"
                        form="disputa-resolver-form"
                        :label="__('disputas.panel.reembolso_etiqueta')"
                        :help="__('disputas.panel.reembolso_ayuda', ['monto' => number_format((float) $disputa->monto_en_disputa, 2, ',', '.')])"
                        :value="old('monto_reembolso', '0')"
                        inputmode="decimal"
                    />
                    <x-atoms.textarea
                        name="motivo"
                        id="disputa-motivo"
                        form="disputa-resolver-form"
                        :label="__('disputas.panel.motivo_etiqueta')"
                        :help="__('disputas.panel.motivo_ayuda')"
                        :rows="3"
                        required
                    />
                    <x-atoms.checkbox
                        name="derivada_a_seguro"
                        id="disputa-seguro"
                        form="disputa-resolver-form"
                        :label="__('disputas.panel.seguro_etiqueta')"
                    />
                    <x-atoms.checkbox
                        name="mala_fe_cliente"
                        id="disputa-mala-fe"
                        form="disputa-resolver-form"
                        :label="__('disputas.panel.mala_fe_etiqueta')"
                    />
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
                    :label="__('disputas.panel.kpi_monto')"
                    icon="payments"
                    :value="number_format((float) $disputa->monto_en_disputa, 2, ',', '.')"
                    :foot="__('disputas.panel.kpi_monto_pie')"
                    state="warning"
                />
                <x-molecules.stat-card
                    :label="__('disputas.panel.kpi_ronda')"
                    icon="sync_alt"
                    :value="(string) $disputa->ronda"
                    :foot="$disputa->ronda === 2 ? __('disputas.panel.apelada') : __('disputas.panel.kpi_ronda_pie')"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('disputas.panel.kpi_responde')"
                    icon="schedule"
                    :value="$respondeAntesDe"
                    :foot="__('disputas.panel.kpi_responde_pie')"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('disputas.panel.seccion_reclamo')" :count="__('disputas.panel.campos_contador', ['cantidad' => 5])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('disputas.panel.campo_motivo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('disputas.motivo.'.$disputa->motivo->value) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('disputas.panel.campo_abierta_por') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('disputas.panel.abierta_por_'.$disputa->abierta_por_rol->value) }} · {{ $disputa->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('disputas.panel.campo_cliente') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $servicio->clienteNombre ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('disputas.panel.campo_proveedor') }}</p>
                        <p class="ag-detalle__campo-valor">
                            @if ($proveedor !== null)
                                <a class="ag-dash__link" href="{{ route('panel.proveedores.show', $proveedor->id) }}">{{ $proveedor->razonSocial }}</a>
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div class="ag-detalle__campo ag-form-section__field--full">
                        <p class="ag-detalle__campo-label">{{ __('disputas.panel.campo_detalle') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $disputa->detalle }}</p>
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.form-section accent="distintivo-2" :title="__('disputas.panel.conversacion_titulo')" :count="(string) count($conversacion)">
                        <div class="ag-form-section__field--full">
                            @if (count($conversacion) === 0)
                                <p class="ag-detalle__campo-valor">{{ __('disputas.panel.conversacion_vacia') }}</p>
                            @else
                                <x-molecules.timeline :items="$conversacion" />
                            @endif
                        </div>
                        @if ($puedeMensajear)
                            <form id="disputa-mensaje-form" method="POST" action="{{ route('panel.disputas.mensaje', $disputa->id) }}" class="ag-form-section__field--full">
                                @csrf
                                <x-atoms.textarea
                                    name="texto"
                                    id="disputa-mensaje-texto"
                                    :label="__('disputas.panel.mensaje_etiqueta')"
                                    :rows="3"
                                    required
                                />
                                <x-atoms.checkbox
                                    name="pide_informacion"
                                    id="disputa-pide-informacion"
                                    :label="__('disputas.panel.pide_informacion')"
                                />
                                <x-atoms.button type="submit" variant="primary" icon="send">
                                    {{ __('disputas.panel.enviar') }}
                                </x-atoms.button>
                            </form>
                        @endif
                    </x-molecules.form-section>

                    <x-molecules.form-section accent="success" :title="__('disputas.panel.resoluciones_titulo')" :count="(string) count($resoluciones)">
                        <div class="ag-form-section__field--full">
                            @if (count($resoluciones) === 0)
                                <p class="ag-detalle__campo-valor">{{ __('disputas.panel.resoluciones_vacio') }}</p>
                            @else
                                <x-molecules.timeline :items="$resoluciones" />
                            @endif
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
