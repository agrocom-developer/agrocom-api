{{--
    Page: clientes/show (GET /panel/clientes/{cliente}, panel.clientes.show)
    Ficha de un cliente — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y la acción de
    aprobar o rechazar (un cliente pendiente) o suspender o reactivar (con motivo,
    en un modal), KPIs, los datos de la cuenta y sus últimas solicitudes.

    Datos esperados (ver ClientesController::show()): la cáscara de CascaraPanel,
    más $cliente, $cuenta (?DatosCuenta), $contactos (Collection<ClienteContacto>), $solicitudes (Collection<SolicitudServicio>),
    $totalSolicitudes, $totalTerrenos, $puedeSuspender, $puedeAprobar y $puedeVerSolicitudes.

    Gateada por `solicitudes.cliente.ver`; suspender y reactivar exigen además
    `solicitudes.cliente.suspender`, y aprobar y rechazar `solicitudes.cliente.aprobar`,
    verificado server-side.
--}}
@php
    $titulo = $cliente->razon_social;
    $estado = $cliente->estado->value;
    $suspendido = $estado === 'suspendido';
    $pendiente = $estado === 'pendiente';
    $aprobado = $estado === 'aprobado';
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
                :subtitle="__('solicitudes.clientes.detalle_subtitulo', ['tipo' => mb_strtolower(__('solicitudes.clientes.tipo.'.$cliente->tipo->value))])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$cliente->estado->tono()">{{ __('solicitudes.clientes.estado.'.$cliente->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.clientes.index')" :label="__('solicitudes.clientes.volver')" />
                    {{-- Aprobación (ADR 0046): un cliente pendiente espera una decisión. --}}
                    @if ($puedeAprobar && $pendiente)
                        <form method="POST" action="{{ route('panel.clientes.aprobar', $cliente) }}">
                            @csrf
                            <x-atoms.button type="submit" variant="primary" icon="check_circle">{{ __('solicitudes.clientes.aprobar') }}</x-atoms.button>
                        </form>
                        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#cliente-rechazar-modal" variant="alert-outline" icon="cancel">
                            {{ __('solicitudes.clientes.rechazar') }}
                        </x-atoms.button>
                    @endif
                    @if ($puedeSuspender)
                        @if ($suspendido)
                            <form method="POST" action="{{ route('panel.clientes.reactivar', $cliente) }}">
                                @csrf
                                <x-atoms.button type="submit" variant="primary" icon="lock_open">{{ __('solicitudes.clientes.reactivar') }}</x-atoms.button>
                            </form>
                        @elseif ($aprobado)
                            <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#cliente-suspender-modal" variant="neutral-outline" icon="block">
                                {{ __('solicitudes.clientes.suspender') }}
                            </x-atoms.button>
                        @endif
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeAprobar && $pendiente)
                <form id="cliente-rechazar-form" method="POST" action="{{ route('panel.clientes.rechazar', $cliente) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="cliente-rechazar-modal"
                    form-id="cliente-rechazar-form"
                    :title="__('solicitudes.clientes.rechazar_titulo', ['cliente' => $cliente->razon_social])"
                    :message="__('solicitudes.clientes.rechazar_mensaje')"
                    :confirm-label="__('solicitudes.clientes.rechazar')"
                    tone="alert"
                >
                    <x-atoms.textarea
                        name="motivo"
                        id="cliente-rechazar-motivo"
                        form="cliente-rechazar-form"
                        :label="__('solicitudes.clientes.motivo_etiqueta_rechazo')"
                        :help="__('solicitudes.clientes.motivo_ayuda')"
                        :rows="3"
                        required
                    />
                </x-molecules.confirm-modal>
            @endif

            @if ($puedeSuspender && $aprobado)
                <form id="cliente-suspender-form" method="POST" action="{{ route('panel.clientes.suspender', $cliente) }}" hidden>
                    @csrf
                </form>

                <x-molecules.confirm-modal
                    id="cliente-suspender-modal"
                    form-id="cliente-suspender-form"
                    :title="__('solicitudes.clientes.suspender_titulo', ['cliente' => $cliente->razon_social])"
                    :message="__('solicitudes.clientes.suspender_mensaje')"
                    :confirm-label="__('solicitudes.clientes.suspender')"
                    tone="neutral"
                >
                    <x-atoms.textarea
                        name="motivo"
                        id="cliente-suspender-motivo"
                        form="cliente-suspender-form"
                        :label="__('solicitudes.clientes.motivo_etiqueta')"
                        :help="__('solicitudes.clientes.motivo_ayuda')"
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

            @if ($suspendido && $cliente->motivo_estado)
                <x-molecules.alert-strip variant="danger" icon="info">
                    {{ __('solicitudes.clientes.motivo_suspension', ['motivo' => $cliente->motivo_estado]) }}
                </x-molecules.alert-strip>
            @elseif ($estado === 'rechazado' && $cliente->motivo_estado)
                <x-molecules.alert-strip variant="danger" icon="info">
                    {{ __('solicitudes.clientes.motivo_rechazo', ['motivo' => $cliente->motivo_estado]) }}
                </x-molecules.alert-strip>
            @endif

            @if ($cliente->publicacion_limitada_en)
                <x-molecules.alert-strip variant="warning" icon="person_alert">
                    {{ __('solicitudes.clientes.publicacion_limitada', ['fecha' => $cliente->publicacion_limitada_en->format('d/m/Y')]) }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('solicitudes.clientes.kpi_calificacion')"
                    icon="star"
                    :value="$cliente->calificaciones_conteo > 0 ? (string) $cliente->calificacion_promedio : '—'"
                    :foot="trans_choice('solicitudes.clientes.kpi_calificaciones_pie', $cliente->calificaciones_conteo, ['cantidad' => $cliente->calificaciones_conteo])"
                    state="warning"
                />
                <x-molecules.stat-card :label="__('solicitudes.clientes.kpi_solicitudes')" icon="request_quote" :value="(string) $totalSolicitudes" state="info" />
                <x-molecules.stat-card :label="__('solicitudes.clientes.kpi_terrenos')" icon="landscape" :value="(string) $totalTerrenos" state="distintivo-1" />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('solicitudes.clientes.seccion_datos')" :count="__('solicitudes.panel.campos_contador', ['cantidad' => 5])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_razon_social') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cliente->razon_social }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_documento') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cliente->documento_fiscal ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_telefono') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cliente->telefono ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_cuenta') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cuenta->nombre ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_alta') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cliente->created_at->format('d/m/Y') }}</p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-1" :title="__('solicitudes.clientes.seccion_contactos')" :count="(string) $contactos->count()">
                    <div class="ag-form-section__field--full">
                        @if ($contactos->isEmpty())
                            <x-molecules.empty-state icon="contacts" :title="__('solicitudes.clientes.contactos_vacio_titulo')" :detail="__('solicitudes.clientes.contactos_vacio_detalle')" />
                        @else
                            <div class="ag-tablero__scroll">
                                <table class="ag-tablero__tabla">
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('solicitudes.clientes.campo_contacto_nombre') }}</th>
                                            <th scope="col">{{ __('solicitudes.clientes.campo_contacto_cargo') }}</th>
                                            <th scope="col">{{ __('solicitudes.clientes.campo_contacto_telefono') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($contactos as $contacto)
                                            <tr>
                                                <th scope="row">
                                                    {{ $contacto->nombre }}
                                                    @if ($contacto->es_principal)
                                                        <x-atoms.badge variant="info">{{ __('solicitudes.clientes.contacto_principal') }}</x-atoms.badge>
                                                    @endif
                                                </th>
                                                <td>{{ $contacto->cargo ?? '—' }}</td>
                                                <td>{{ $contacto->telefono }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('solicitudes.clientes.seccion_solicitudes')" :count="(string) $totalSolicitudes">
                    <div class="ag-form-section__field--full">
                        @if ($solicitudes->isEmpty())
                            <x-molecules.empty-state icon="request_quote" :title="__('solicitudes.clientes.solicitudes_vacio_titulo')" :detail="__('solicitudes.clientes.solicitudes_vacio_detalle')" />
                        @else
                            <div class="ag-tablero__scroll">
                                <table class="ag-tablero__tabla">
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('solicitudes.panel.col_solicitud') }}</th>
                                            <th scope="col">{{ __('solicitudes.panel.col_estado') }}</th>
                                            <th scope="col" class="ag-tablero__num">{{ __('solicitudes.panel.col_hectareas') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($solicitudes as $solicitud)
                                            <tr>
                                                <th scope="row">
                                                    @if ($puedeVerSolicitudes)
                                                        <a class="ag-dash__link" href="{{ route('panel.solicitudes.show', $solicitud) }}">#{{ $solicitud->id }}</a>
                                                    @else
                                                        #{{ $solicitud->id }}
                                                    @endif
                                                    <span class="ag-solicitudes__sub">{{ $solicitud->terreno->nombre ?? '' }}</span>
                                                </th>
                                                <td><x-atoms.badge :variant="$solicitud->estado->tono()">{{ __('solicitudes.estado.'.$solicitud->estado->value) }}</x-atoms.badge></td>
                                                <td class="ag-tablero__num">{{ number_format((float) $solicitud->hectareas_solicitadas, 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </x-molecules.form-section>
                <x-slot:aside>
                    <x-molecules.form-section accent="info" :title="__('solicitudes.clientes.seccion_relacionado')">
                        <div class="ag-form-section__field--full">
                            @if ($puedeVerSolicitudes)
                                <x-molecules.link-row
                                    :href="route('panel.solicitudes.index')"
                                    icon="request_quote"
                                    :title="__('solicitudes.clientes.seccion_solicitudes')"
                                    :meta="(string) $totalSolicitudes"
                                    tone="info"
                                />
                            @endif
                        </div>
                    </x-molecules.form-section>

                    <x-molecules.form-section accent="distintivo-2" :title="__('solicitudes.clientes.historial_titulo')" :count="(string) count($lineaDeTiempo)">
                        <div class="ag-form-section__field--full">
                            @if ($lineaDeTiempo === [])
                                <p class="ag-detalle__campo-valor">{{ __('solicitudes.clientes.historial_vacio') }}</p>
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
