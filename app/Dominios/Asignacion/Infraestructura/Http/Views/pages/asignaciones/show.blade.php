{{--
    Page: asignaciones/show (GET /panel/asignaciones/{solicitud}, panel.asignaciones.show)
    Ficha de la asignación de una solicitud (ADR 0030) — arquetipo Detalle (§6.4
    de docs/diseno/guia_pantalla_panel.md): cabecera con el estado y la acción de
    asignar a mano (con motivo, en un modal), KPIs, los datos del servicio y el
    recorrido de las ofertas, de la primera a la última.

    Datos esperados (ver AsignacionesController::show()): la cáscara de
    CascaraPanel, más $solicitud (DatosSolicitudParaAsignar), $proveedorAsignado
    (?DatosProveedor), $rondas, $ofertasVivas (int), $lineaDeTiempo (list<array{
    title, meta, tone}>), $puedeForzar (bool, ya cruzado con el estado) y
    $opcionesProveedor (array<int, string>).

    Gateada por `asignacion.asignacion.ver`, verificado server-side en el
    controlador; asignar a mano exige además `asignacion.asignacion.forzar`.
--}}
@php
    $titulo = __('asignacion.panel.detalle_titulo', ['id' => $solicitud->id]);
    $tonoPorEstado = ['publicada' => 'info', 'en_asignacion' => 'info', 'sin_proveedor' => 'warning', 'asignada' => 'primary-2', 'en_camino' => 'primary-2'];
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
                :subtitle="__('asignacion.panel.detalle_subtitulo', ['zona' => $solicitud->zonaNombre ?? $solicitud->terrenoNombre])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$tonoPorEstado[$solicitud->estado] ?? 'neutral'">{{ __('solicitudes.estado.'.$solicitud->estado) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.asignaciones.index')" :label="__('asignacion.panel.volver')" />
                    <x-atoms.button :href="route('panel.solicitudes.show', $solicitud->id)" variant="info-outline" icon="request_quote">
                        {{ __('asignacion.panel.ver_solicitud') }}
                    </x-atoms.button>
                    @if ($puedeForzar)
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#asignacion-forzar-modal"
                            variant="warning-outline"
                            icon="swap_horiz"
                        >
                            {{ __('asignacion.panel.forzar_accion') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeForzar)
                <form id="asignacion-forzar-form" method="POST" action="{{ route('panel.asignaciones.forzar', $solicitud->id) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="asignacion-forzar-modal"
                    form-id="asignacion-forzar-form"
                    :title="__('asignacion.panel.forzar_titulo', ['id' => $solicitud->id])"
                    :message="__('asignacion.panel.forzar_mensaje')"
                    :confirm-label="__('asignacion.panel.forzar_accion')"
                    tone="warning"
                >
                    <x-atoms.select
                        name="proveedor_id"
                        id="asignacion-forzar-proveedor"
                        form="asignacion-forzar-form"
                        :label="__('asignacion.panel.forzar_proveedor')"
                        :options="$opcionesProveedor"
                        :value="old('proveedor_id')"
                        :placeholder="__('asignacion.panel.forzar_proveedor_placeholder')"
                        required
                    />
                    <x-atoms.textarea
                        name="motivo"
                        id="asignacion-forzar-motivo"
                        form="asignacion-forzar-form"
                        :label="__('asignacion.panel.motivo_etiqueta')"
                        :help="__('asignacion.panel.motivo_ayuda')"
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

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('asignacion.panel.kpi_ofertas_vivas')"
                    icon="mark_email_unread"
                    :value="(string) $ofertasVivas"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('asignacion.panel.kpi_rondas')"
                    icon="alt_route"
                    :value="(string) $rondas->count()"
                    :foot="__('asignacion.panel.kpi_rondas_pie', ['vacias' => $rondas->where('proveedores_ofrecidos', 0)->count()])"
                    state="distintivo-1"
                />
                <x-molecules.stat-card
                    :label="__('asignacion.panel.kpi_hectareas')"
                    icon="landscape"
                    :value="number_format((float) $solicitud->hectareas, 2, ',', '.')"
                    value-suffix="ha"
                    :foot="$solicitud->urgente ? __('asignacion.panel.urgente') : null"
                    state="success"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('asignacion.panel.seccion_servicio')" :count="__('asignacion.panel.campos_contador', ['cantidad' => 4])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('asignacion.panel.campo_aplicacion') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('solicitudes.tipo_aplicacion.'.$solicitud->tipoAplicacion) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('asignacion.panel.campo_ventana') }}</p>
                        <p class="ag-detalle__campo-valor">{{ \Illuminate\Support\Carbon::parse($solicitud->ventanaDesde)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($solicitud->ventanaHasta)->format('d/m/Y') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('asignacion.panel.campo_monto') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $solicitud->montoEstimado !== null ? number_format((float) $solicitud->montoEstimado, 2, ',', '.') : '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('asignacion.panel.campo_proveedor') }}</p>
                        <p class="ag-detalle__campo-valor">
                            @if ($proveedorAsignado !== null)
                                <a class="ag-dash__link" href="{{ route('panel.proveedores.show', $proveedorAsignado->id) }}">{{ $proveedorAsignado->razonSocial }}</a>
                            @else
                                {{ __('asignacion.panel.sin_proveedor') }}
                            @endif
                        </p>
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.form-section accent="distintivo-2" :title="__('asignacion.panel.ofertas_titulo')" :count="(string) count($lineaDeTiempo)">
                        <div class="ag-form-section__field--full">
                            @if (count($lineaDeTiempo) === 0)
                                <p class="ag-detalle__campo-valor">{{ __('asignacion.panel.ofertas_vacio') }}</p>
                            @else
                                <x-molecules.timeline :items="$lineaDeTiempo" />
                            @endif
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
