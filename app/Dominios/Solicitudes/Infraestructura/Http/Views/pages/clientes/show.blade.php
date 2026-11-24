{{--
    Page: clientes/show (GET /panel/clientes/{cliente}, panel.clientes.show)
    Ficha de un cliente — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y la acción de
    suspender o reactivar (con motivo, en un modal), KPIs, sus datos, sus terrenos
    y sus últimas solicitudes.

    Datos esperados (ver ClientesController::show()): la cáscara de CascaraPanel,
    más $cliente, $terrenos, $solicitudes (las últimas 10), $totalSolicitudes,
    $solicitudesEnCurso, $puedeSuspender y $puedeVerSolicitud.

    Gateada por `solicitudes.cliente.ver`; suspender y reactivar exigen
    `solicitudes.cliente.suspender`, verificado server-side.
--}}
@php
    $titulo = $cliente->razon_social;
    $suspendido = $cliente->estado->value === 'suspendido';
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
                    <x-atoms.badge :variant="$suspendido ? 'danger' : 'success'">{{ __('solicitudes.clientes.estado.'.$cliente->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.clientes.index')" :label="__('solicitudes.clientes.volver')" />
                    @if ($puedeSuspender)
                        @if ($suspendido)
                            <form method="POST" action="{{ route('panel.clientes.estado', $cliente) }}">
                                @csrf
                                <input type="hidden" name="accion" value="reactivar">
                                <x-atoms.button type="submit" variant="primary" icon="check_circle">
                                    {{ __('solicitudes.clientes.reactivar') }}
                                </x-atoms.button>
                            </form>
                        @else
                            <x-atoms.button
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#cliente-suspender-modal"
                                variant="danger-outline"
                                icon="block"
                            >
                                {{ __('solicitudes.clientes.suspender') }}
                            </x-atoms.button>
                        @endif
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeSuspender && ! $suspendido)
                <form id="cliente-suspender-form" method="POST" action="{{ route('panel.clientes.estado', $cliente) }}" hidden>
                    @csrf
                    <input type="hidden" name="accion" value="suspender">
                </form>

                <x-molecules.confirm-modal
                    id="cliente-suspender-modal"
                    form-id="cliente-suspender-form"
                    :title="__('solicitudes.clientes.suspender_titulo', ['cliente' => $cliente->razon_social])"
                    :message="__('solicitudes.clientes.suspender_mensaje')"
                    :confirm-label="__('solicitudes.clientes.suspender')"
                    tone="danger"
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

            @if ($suspendido && $cliente->motivo_suspension)
                <x-molecules.alert-strip variant="danger" icon="info">
                    {{ __('solicitudes.clientes.motivo_suspension', ['motivo' => $cliente->motivo_suspension]) }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card :label="__('solicitudes.clientes.kpi_solicitudes')" icon="request_quote" :value="(string) $totalSolicitudes" :foot="__('solicitudes.clientes.kpi_en_curso', ['cantidad' => $solicitudesEnCurso])" state="info" />
                <x-molecules.stat-card :label="__('solicitudes.clientes.kpi_terrenos')" icon="landscape" :value="(string) count($terrenos)" state="success" />
                <x-molecules.stat-card
                    :label="__('solicitudes.clientes.kpi_calificacion')"
                    icon="star"
                    :value="$cliente->calificaciones_conteo > 0 ? number_format((float) $cliente->calificacion_promedio, 2, ',', '.') : '—'"
                    :value-suffix="$cliente->calificaciones_conteo > 0 ? '/ 5' : null"
                    :foot="__('solicitudes.clientes.kpi_calificaciones', ['cantidad' => $cliente->calificaciones_conteo])"
                    state="alert"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('solicitudes.clientes.seccion_datos')" :count="__('solicitudes.panel.campos_contador', ['cantidad' => 4])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_razon_social') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cliente->razon_social }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_tipo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('solicitudes.clientes.tipo.'.$cliente->tipo->value) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_documento') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cliente->documento_fiscal ?? '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('solicitudes.clientes.campo_telefono') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cliente->telefono ?? '—' }}</p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('solicitudes.clientes.seccion_terrenos')" :count="(string) count($terrenos)">
                    <div class="ag-form-section__field--full">
                        @if (count($terrenos) === 0)
                            <p class="ag-solicitudes__sub">{{ __('solicitudes.clientes.terrenos_vacio') }}</p>
                        @else
                            <x-molecules.index-table columns="minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 0.8fr)">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('solicitudes.clientes.col_terreno') }}</span>
                                    <span role="columnheader">{{ __('solicitudes.clientes.col_zona') }}</span>
                                    <span role="columnheader">{{ __('solicitudes.panel.col_hectareas') }}</span>
                                </x-slot:head>
                                @foreach ($terrenos as $terreno)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $terreno->nombre }}</span>
                                        <span role="cell">{{ $terreno->zona->nombre ?? __('solicitudes.panel.sin_zona') }}</span>
                                        <span role="cell" class="ag-solicitudes__cifra">{{ number_format((float) $terreno->hectareas, 2, ',', '.') }} ha</span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="success" :title="__('solicitudes.clientes.seccion_solicitudes')" :count="(string) count($solicitudes)">
                    <div class="ag-form-section__field--full">
                        @if (count($solicitudes) === 0)
                            <p class="ag-solicitudes__sub">{{ __('solicitudes.clientes.solicitudes_vacio') }}</p>
                        @else
                            <x-molecules.index-table columns="4rem minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 0.8fr)">
                                <x-slot:head>
                                    <span role="columnheader">#</span>
                                    <span role="columnheader">{{ __('solicitudes.clientes.col_terreno') }}</span>
                                    <span role="columnheader">{{ __('solicitudes.panel.col_estado') }}</span>
                                    <span role="columnheader">{{ __('solicitudes.panel.col_hectareas') }}</span>
                                </x-slot:head>
                                @foreach ($solicitudes as $solicitud)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">
                                            @if ($puedeVerSolicitud)
                                                <a class="ag-dash__link" href="{{ route('panel.solicitudes.show', $solicitud) }}">#{{ $solicitud->id }}</a>
                                            @else
                                                #{{ $solicitud->id }}
                                            @endif
                                        </span>
                                        <span role="cell">{{ $solicitud->terreno->nombre }}</span>
                                        <span role="cell">
                                            <x-atoms.badge :variant="$solicitud->estado->tono()">{{ __('solicitudes.estado.'.$solicitud->estado->value) }}</x-atoms.badge>
                                        </span>
                                        <span role="cell" class="ag-solicitudes__cifra">{{ number_format((float) $solicitud->hectareas_solicitadas, 2, ',', '.') }} ha</span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        @endif
                    </div>
                </x-molecules.form-section>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
