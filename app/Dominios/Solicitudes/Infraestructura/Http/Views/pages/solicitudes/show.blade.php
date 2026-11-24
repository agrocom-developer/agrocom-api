{{--
    Page: solicitudes/show (GET /panel/solicitudes/{solicitud}, panel.solicitudes.show)
    Ficha de una solicitud de servicio — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y la acción de
    cancelar (con motivo, en un modal), KPIs, los datos de lo pedido y del
    terreno, y el historial inmutable de cada cambio de estado.

    Datos esperados (ver SolicitudesController::show()): la cáscara de
    CascaraPanel, más $solicitud (con `terreno`, `cliente` e `historial`),
    $puedeCancelar (bool, ya cruzado con que el estado admita cancelación),
    $puedeIntervenir (bool: permiso de intervenir y solicitud abierta a
    ofertas), $lineaDeTiempo (list<array{title, meta, tone}>), $contexto
    (?DatosContextoSolicitud) y $negocia (forma de
    LecturaNegociacionParaPanel::deLaSolicitud()): negociaciones con sus
    rondas, chat, avance, fotos y última ubicación del piloto.

    Gateada por `solicitudes.solicitud.ver`, verificado server-side en el
    controlador; cancelar exige además `solicitudes.solicitud.cancelar` y
    cerrar la negociación de un proveedor, `solicitudes.solicitud.intervenir`.
--}}
@php
    $titulo = __('solicitudes.panel.detalle_titulo', ['id' => $solicitud->id]);
    $fecha = fn (?\DateTimeInterface $momento): string => $momento === null
        ? '—'
        : \Illuminate\Support\Carbon::instance($momento)->timezone($zonaHoraria ?: config('app.timezone'))->format('d/m/Y H:i');
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
                :subtitle="__('solicitudes.panel.detalle_subtitulo', ['cliente' => $solicitud->cliente->razon_social, 'terreno' => $solicitud->terreno->nombre])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$solicitud->estado->tono()">{{ __('solicitudes.estado.'.$solicitud->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.solicitudes.index')" :label="__('solicitudes.panel.volver')" />
                    @if ($puedeCancelar)
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#solicitud-cancelar-modal"
                            variant="danger-outline"
                            icon="cancel"
                        >
                            {{ __('solicitudes.panel.cancelar_accion') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeCancelar)
                <form id="solicitud-cancelar-form" method="POST" action="{{ route('panel.solicitudes.cancelar', $solicitud) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="solicitud-cancelar-modal"
                    form-id="solicitud-cancelar-form"
                    :title="__('solicitudes.panel.cancelar_titulo', ['id' => $solicitud->id])"
                    :message="__('solicitudes.panel.cancelar_mensaje')"
                    :confirm-label="__('solicitudes.panel.cancelar_accion')"
                    tone="danger"
                >
                    <x-atoms.textarea
                        name="motivo"
                        id="solicitud-cancelar-motivo"
                        form="solicitud-cancelar-form"
                        :label="__('solicitudes.panel.motivo_etiqueta')"
                        :help="__('solicitudes.panel.motivo_ayuda')"
                        :rows="3"
                        required
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

            @if ($solicitud->motivo_cancelacion)
                <x-molecules.alert-strip variant="danger" icon="info">
                    {{ __('solicitudes.panel.motivo_cancelacion', ['quien' => mb_strtolower(__('solicitudes.origen.'.$solicitud->cancelada_por->value)), 'motivo' => $solicitud->motivo_cancelacion]) }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('solicitudes.panel.kpi_hectareas')"
                    icon="landscape"
                    :value="number_format((float) $solicitud->hectareas_solicitadas, 2, ',', '.')"
                    value-suffix="ha"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('solicitudes.panel.kpi_monto')"
                    icon="payments"
                    :value="$solicitud->monto_estimado !== null ? number_format((float) $solicitud->monto_estimado, 2, ',', '.') : '—'"
                    :foot="$solicitud->precio_ha_propuesto !== null ? __('solicitudes.panel.kpi_tarifa_pie', ['tarifa' => number_format((float) $solicitud->precio_ha_propuesto, 2, ',', '.')]) : __('solicitudes.panel.kpi_sin_publicar')"
                    state="success"
                />
                <x-molecules.stat-card
                    :label="__('solicitudes.panel.kpi_ventana')"
                    icon="event"
                    :value="$solicitud->ventana_desde->format('d/m').' – '.$solicitud->ventana_hasta->format('d/m')"
                    :foot="$solicitud->urgente ? __('solicitudes.panel.urgente') : null"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('solicitudes.panel.seccion_servicio')" :count="__('solicitudes.panel.campos_contador', ['cantidad' => 7])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_aplicacion') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('solicitudes.tipo_aplicacion.'.$solicitud->tipo_aplicacion->value) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_cultivo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $solicitud->cultivo ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_producto') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $solicitud->producto_indicado }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_aporta_producto') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('solicitudes.aporta_producto.'.$solicitud->aporta_producto->value) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_cliente') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $solicitud->cliente->razon_social }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_proveedor') }}</p>
                        <p class="ag-detalle__campo-valor">
                            @if ($solicitud->proveedor_id !== null)
                                <a class="ag-dash__link" href="{{ route('panel.proveedores.show', $solicitud->proveedor_id) }}">#{{ $solicitud->proveedor_id }}</a>
                            @else
                                {{ __('solicitudes.panel.sin_proveedor') }}
                            @endif
                        </p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_observaciones') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $solicitud->observaciones ?? '—' }}</p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('solicitudes.panel.seccion_terreno')" :count="__('solicitudes.panel.campos_contador', ['cantidad' => 4])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_terreno') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $solicitud->terreno->nombre }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_hectareas_terreno') }}</p>
                        <p class="ag-detalle__campo-valor">{{ number_format((float) $solicitud->terreno->hectareas, 2, ',', '.') }} ha</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_acceso') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $solicitud->terreno->referencias_acceso ?? '—' }}@if ($solicitud->terreno->acceso_dificil) · {{ __('solicitudes.panel.acceso_dificil') }}@endif</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.panel.campo_colindantes') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $solicitud->terreno->colindantes_sensibles ?? '—' }}</p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="success" :title="__('solicitudes.panel.seccion_negociacion')" :count="(string) count($negocia['negociaciones'])">
                    <div class="ag-form-section__field--full">
                        @include('solicitudes::pages.solicitudes._negociacion', ['negociaciones' => $negocia['negociaciones']])
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="primary-2" :title="__('solicitudes.panel.seccion_seguimiento')" :count="(string) (count($negocia['avances']) + count($negocia['fotos']))">
                    <div class="ag-form-section__field--full">
                        @include('solicitudes::pages.solicitudes._seguimiento', ['avances' => $negocia['avances'], 'fotos' => $negocia['fotos'], 'ubicacion' => $negocia['ubicacion']])
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('solicitudes.panel.seccion_chat')" :count="(string) count($negocia['mensajes'])">
                    <div class="ag-form-section__field--full">
                        @include('solicitudes::pages.solicitudes._chat', ['mensajes' => $negocia['mensajes']])
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.form-section accent="distintivo-2" :title="__('solicitudes.panel.historial_titulo')" :count="(string) count($lineaDeTiempo)">
                        <div class="ag-form-section__field--full">
                            <x-molecules.timeline :items="$lineaDeTiempo" />
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
