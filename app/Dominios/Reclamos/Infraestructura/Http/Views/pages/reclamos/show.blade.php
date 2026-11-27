{{--
    Page: reclamos/show (GET /panel/reclamos/{reclamo}, panel.reclamos.show)
    Ficha de un reclamo (ADR 0033 y 0039) — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y las acciones de
    soporte (tomar, resolver con resultado, reembolso y motivo), KPIs, los datos del
    reclamo y, al lado, la conversación y las resoluciones. Los reembolsos se
    liquidan entre las partes fuera de la aplicación (ADR 0034).

    Datos esperados (ver ReclamosController::show()): la cáscara de CascaraPanel,
    más $reclamo (Reclamo), $servicio (?DatosServicio), $proveedor (?DatosProveedor),
    $conversacion y $resoluciones (list<array{title, meta, tone}>), $respondeAntesDe
    (string), $puedeTomar, $puedeMensajear y $puedeResolver (bool) y $resultados
    (list<ResultadoReclamo>).

    Gateada por `reclamos.reclamo.ver`, verificado server-side en el controlador;
    tomar y escribir exigen `reclamos.reclamo.atender` y resolver, `.resolver`.
--}}
@php
    $titulo = __('reclamos.panel.detalle_titulo', ['id' => $reclamo->id]);
    $opcionesResultado = collect($resultados)->mapWithKeys(fn ($opcion) => [$opcion->value => __('reclamos.resultado.'.$opcion->value)])->all();
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
                :subtitle="__('reclamos.panel.detalle_subtitulo', ['cliente' => $servicio->clienteNombre ?? '—', 'solicitud' => $reclamo->solicitud_id])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$reclamo->estado->tono()">{{ __('reclamos.estado.'.$reclamo->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.reclamos.index')" :label="__('reclamos.panel.volver')" />
                    <x-atoms.button :href="route('panel.solicitudes.show', $reclamo->solicitud_id)" variant="info-outline" icon="request_quote">
                        {{ __('reclamos.panel.ver_solicitud') }}
                    </x-atoms.button>
                    @if ($puedeTomar)
                        <form method="POST" action="{{ route('panel.reclamos.tomar', $reclamo->id) }}">
                            @csrf
                            <x-atoms.button type="submit" variant="info-outline" icon="assignment_ind">
                                {{ __('reclamos.panel.tomar') }}
                            </x-atoms.button>
                        </form>
                    @endif
                    @if ($puedeResolver)
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#reclamo-resolver-modal"
                            variant="success-outline"
                            icon="gavel"
                        >
                            {{ __('reclamos.panel.resolver') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeResolver)
                <form id="reclamo-resolver-form" method="POST" action="{{ route('panel.reclamos.resolver', $reclamo->id) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="reclamo-resolver-modal"
                    form-id="reclamo-resolver-form"
                    :title="__('reclamos.panel.resolver_titulo', ['id' => $reclamo->id])"
                    :message="__('reclamos.panel.resolver_mensaje')"
                    :confirm-label="__('reclamos.panel.resolver')"
                    tone="success"
                >
                    <x-atoms.select
                        name="resultado"
                        id="reclamo-resultado"
                        form="reclamo-resolver-form"
                        :label="__('reclamos.panel.resultado_etiqueta')"
                        :options="$opcionesResultado"
                        :value="old('resultado')"
                        :placeholder="__('reclamos.panel.resultado_placeholder')"
                        required
                    />
                    <x-atoms.input
                        name="monto_reembolso"
                        id="reclamo-reembolso"
                        form="reclamo-resolver-form"
                        :label="__('reclamos.panel.reembolso_etiqueta')"
                        :help="__('reclamos.panel.reembolso_ayuda', ['monto' => number_format((float) $reclamo->monto_en_reclamo, 2, ',', '.')])"
                        :value="old('monto_reembolso', '0')"
                        inputmode="decimal"
                    />
                    <x-atoms.textarea
                        name="motivo"
                        id="reclamo-motivo"
                        form="reclamo-resolver-form"
                        :label="__('reclamos.panel.motivo_etiqueta')"
                        :help="__('reclamos.panel.motivo_ayuda')"
                        :rows="3"
                        required
                    />
                    <x-atoms.checkbox
                        name="derivada_a_seguro"
                        id="reclamo-seguro"
                        form="reclamo-resolver-form"
                        :label="__('reclamos.panel.seguro_etiqueta')"
                    />
                    <x-atoms.checkbox
                        name="mala_fe_cliente"
                        id="reclamo-mala-fe"
                        form="reclamo-resolver-form"
                        :label="__('reclamos.panel.mala_fe_etiqueta')"
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
                    :label="__('reclamos.panel.kpi_monto')"
                    icon="payments"
                    :value="number_format((float) $reclamo->monto_en_reclamo, 2, ',', '.')"
                    :foot="__('reclamos.panel.kpi_monto_pie')"
                    state="warning"
                />
                <x-molecules.stat-card
                    :label="__('reclamos.panel.kpi_ronda')"
                    icon="sync_alt"
                    :value="(string) $reclamo->ronda"
                    :foot="$reclamo->ronda === 2 ? __('reclamos.panel.apelada') : __('reclamos.panel.kpi_ronda_pie')"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('reclamos.panel.kpi_responde')"
                    icon="schedule"
                    :value="$respondeAntesDe"
                    :foot="__('reclamos.panel.kpi_responde_pie')"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('reclamos.panel.seccion_reclamo')" :count="__('reclamos.panel.campos_contador', ['cantidad' => 5])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('reclamos.panel.campo_motivo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('reclamos.motivo.'.$reclamo->motivo->value) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('reclamos.panel.campo_abierta_por') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('reclamos.panel.abierta_por_'.$reclamo->abierta_por_rol->value) }} · {{ $reclamo->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('reclamos.panel.campo_cliente') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $servicio->clienteNombre ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('reclamos.panel.campo_proveedor') }}</p>
                        <p class="ag-detalle__campo-valor">
                            @if ($proveedor !== null)
                                <a class="ag-dash__link" href="{{ route('panel.proveedores.show', $proveedor->id) }}">{{ $proveedor->razonSocial }}</a>
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div class="ag-detalle__campo ag-form-section__field--full">
                        <p class="ag-detalle__campo-label">{{ __('reclamos.panel.campo_detalle') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $reclamo->detalle }}</p>
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.form-section accent="distintivo-2" :title="__('reclamos.panel.conversacion_titulo')" :count="(string) count($conversacion)">
                        <div class="ag-form-section__field--full">
                            @if (count($conversacion) === 0)
                                <p class="ag-detalle__campo-valor">{{ __('reclamos.panel.conversacion_vacia') }}</p>
                            @else
                                <x-molecules.timeline :items="$conversacion" />
                            @endif
                        </div>
                        @if ($puedeMensajear)
                            <form id="reclamo-mensaje-form" method="POST" action="{{ route('panel.reclamos.mensaje', $reclamo->id) }}" class="ag-form-section__field--full">
                                @csrf
                                <x-atoms.textarea
                                    name="texto"
                                    id="reclamo-mensaje-texto"
                                    :label="__('reclamos.panel.mensaje_etiqueta')"
                                    :rows="3"
                                    required
                                />
                                <x-atoms.checkbox
                                    name="pide_informacion"
                                    id="reclamo-pide-informacion"
                                    :label="__('reclamos.panel.pide_informacion')"
                                />
                                <x-atoms.button type="submit" variant="primary" icon="send">
                                    {{ __('reclamos.panel.enviar') }}
                                </x-atoms.button>
                            </form>
                        @endif
                    </x-molecules.form-section>

                    <x-molecules.form-section accent="success" :title="__('reclamos.panel.resoluciones_titulo')" :count="(string) count($resoluciones)">
                        <div class="ag-form-section__field--full">
                            @if (count($resoluciones) === 0)
                                <p class="ag-detalle__campo-valor">{{ __('reclamos.panel.resoluciones_vacio') }}</p>
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
