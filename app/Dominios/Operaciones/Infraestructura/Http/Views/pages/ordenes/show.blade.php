{{--
    Page: ordenes/show (GET /panel/ordenes/{orden}, panel.ordenes.show)
    Detalle de solo lectura de una orden de aplicación (homogeneización
    17/9/2026): primera pantalla del arquetipo Detalle del panel — no existía
    ninguno todavía (§6.4 de docs/diseno/guia_pantalla_panel.md documenta el
    patrón que arma esta vista, para que la próxima pantalla "ver detalle" de
    Operaciones lo copie).

    Por qué hace falta: mientras la orden es `emitida`, `edit()` cumple este
    rol; apenas se activa, el link "Editar" se oculta (`Aplicacion/ActualizarOrden`
    exige `emitida`) y hasta ahora no quedaba ningún lugar del panel para
    volver a ver sus datos completos.

    Datos esperados (ver OrdenesController::show()): la cáscara de
    CascaraPanel, más $orden (con `categoriaInsumo` cargada),
    $contratoLabel, $contactoLabel (nullable), $aplicacionesPrevistas
    (nullable — del contrato), $lotes (list, ver detalleLotesOrden()),
    $hectareasSolicitadas/$hectareasAsignadas (string, ya formateadas),
    $porcentajeAsignado (int), $equiposAsignados (int), $actividad (list,
    ver actividadOrden()), $puedeEditar/$puedeActivar/$puedeEliminar (bool).

    Gateada por `operaciones.orden.ver` — mismo permiso que `index()`/`edit()`,
    sin permiso nuevo (mismo criterio que las 5 pantallas `.show` ya
    homogeneizadas del panel: Trabajos, Devengos, Planillas, Rendiciones,
    EquiposTrabajo).

    Estilos en resources/css/pages/ordenes.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $estadoValor = $orden->estado->value;
    $variantePorEstado = [
        'emitida' => 'neutral',
        'vigente' => 'success',
        'consumida' => 'info',
        'vencida' => 'danger',
    ];
    $tipoInsumo = $orden->categoriaInsumo?->tipo_insumo?->value;
    $dosisTexto = $orden->kilos_por_vuelo !== null
        ? __('operaciones.ordenes.dosis_kilos_por_vuelo', ['cantidad' => number_format((float) $orden->kilos_por_vuelo, 2, ',', '.')])
        : ($orden->litros_ha !== null
            ? __('operaciones.ordenes.dosis_litros_ha', ['cantidad' => number_format((float) $orden->litros_ha, 2, ',', '.')])
            : '—');
    $formIdActivar = "orden-activar-{$orden->id}";
    $formIdEliminar = "orden-eliminar-{$orden->id}";
    $modalIdActivar = "orden-activar-modal-{$orden->id}";
    $modalIdEliminar = "orden-eliminar-modal-{$orden->id}";

    $limitesItems = [
        ['label' => __('operaciones.ordenes.campo_humedad_min_pct'), 'value' => $orden->humedad_min_pct !== null ? "{$orden->humedad_min_pct} %" : __('operaciones.ordenes.limite_sin_definir')],
        ['label' => __('operaciones.ordenes.campo_humedad_max_pct'), 'value' => $orden->humedad_max_pct !== null ? "{$orden->humedad_max_pct} %" : __('operaciones.ordenes.limite_sin_definir')],
        ['label' => __('operaciones.ordenes.campo_viento_max_kmh'), 'value' => $orden->viento_max_kmh !== null ? "{$orden->viento_max_kmh} km/h" : __('operaciones.ordenes.limite_sin_definir')],
        ['label' => __('operaciones.ordenes.campo_temperatura_max_c'), 'value' => $orden->temperatura_max_c !== null ? "{$orden->temperatura_max_c} °C" : __('operaciones.ordenes.limite_sin_definir')],
        ['label' => __('operaciones.ordenes.campo_velocidad_max_kmh'), 'value' => $orden->velocidad_max_kmh !== null ? "{$orden->velocidad_max_kmh} km/h" : __('operaciones.ordenes.limite_sin_definir')],
    ];
@endphp
<x-templates.panel-shell :title="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])"
    >
        <div class="ag-ordenes-detalle">
            <x-molecules.boton-volver :href="route('panel.ordenes.index')" :label="__('operaciones.ordenes.volver')" />

            <x-organisms.page-header
                :title="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])"
                :subtitle="__('operaciones.ordenes.detalle_subtitulo', ['cliente' => $contratoLabel, 'fecha' => $orden->fecha_emision->format('d/m/Y')])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                        {{ __('operaciones.estado.'.$estadoValor) }}
                    </x-atoms.badge>
                </x-slot:chip>

                <x-slot:actions>
                    @if ($puedeEditar && $estadoValor === 'emitida')
                        <x-atoms.button :href="route('panel.ordenes.edit', $orden)" variant="primary" icon="edit">
                            {{ __('operaciones.ordenes.editar') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeActivar && $estadoValor === 'emitida')
                        <form id="{{ $formIdActivar }}" method="POST" action="{{ route('panel.ordenes.activar', $orden) }}">
                            @csrf
                        </form>

                        <x-molecules.confirm-modal
                            :id="$modalIdActivar"
                            :form-id="$formIdActivar"
                            :title="__('operaciones.ordenes.confirmar_activar_titulo')"
                            :message="__('operaciones.ordenes.confirmar_activar')"
                            :confirm-label="__('operaciones.ordenes.activar_accion')"
                            tone="success"
                        />

                        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdActivar }}" variant="outline" icon="check_circle">
                            {{ __('operaciones.ordenes.activar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeEliminar && $estadoValor !== 'vigente')
                        <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.ordenes.destroy', $orden) }}">
                            @csrf
                            @method('DELETE')
                        </form>

                        <x-molecules.confirm-modal
                            :id="$modalIdEliminar"
                            :form-id="$formIdEliminar"
                            :title="__('operaciones.ordenes.confirmar_eliminar_titulo')"
                            :message="__('operaciones.ordenes.confirmar_baja')"
                            :confirm-label="__('operaciones.ordenes.eliminar_accion')"
                            tone="danger"
                        />

                        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdEliminar }}" variant="danger-outline" icon="delete">
                            {{ __('operaciones.ordenes.eliminar_accion') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-ordenes-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.aside_hectareas_solicitadas')"
                    icon="landscape"
                    :value="$hectareasSolicitadas"
                    value-suffix="ha"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.campo_categoria_insumo')"
                    icon="science"
                    :value="$dosisTexto"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.kpi_aplicaciones')"
                    icon="repeat"
                    :value="$orden->nro_aplicacion"
                    :value-suffix="$aplicacionesPrevistas !== null ? __('operaciones.ordenes.kpi_aplicaciones_sufijo', ['total' => $aplicacionesPrevistas]) : null"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.kpi_equipos_necesarios')"
                    icon="groups"
                    :value="$orden->cantidad_equipos_necesarios"
                    :foot="__('operaciones.ordenes.kpi_equipos_asignados_pie', ['asignados' => $equiposAsignados, 'necesarios' => $orden->cantidad_equipos_necesarios])"
                    :foot-tone="$equiposAsignados >= $orden->cantidad_equipos_necesarios ? 'success' : 'muted'"
                />
            </div>

            @php
                $datosOrden = [
                    ['label' => __('operaciones.ordenes.campo_contrato'), 'value' => $contratoLabel],
                    ['label' => __('operaciones.ordenes.campo_tipo_insumo'), 'value' => $tipoInsumo !== null ? __('operaciones.tipo_insumo.'.$tipoInsumo) : '—'],
                    ['label' => __('operaciones.ordenes.campo_categoria_insumo'), 'value' => $orden->categoriaInsumo?->nombre ?? '—'],
                    ['label' => __('operaciones.ordenes.campo_tipo_aplicacion'), 'value' => __('operaciones.tipo_aplicacion.'.$orden->tipo_aplicacion->value)],
                    ['label' => __('operaciones.ordenes.campo_fecha_emision'), 'value' => $orden->fecha_emision->format('d/m/Y')],
                    ['label' => __('operaciones.ordenes.campo_contacto'), 'value' => $contactoLabel ?? __('operaciones.ordenes.campo_contacto_placeholder')],
                ];
            @endphp

            <x-molecules.form-layout>
                <x-molecules.form-section :title="__('operaciones.ordenes.seccion_datos')" :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 6])">
                    @foreach ($datosOrden as $campo)
                        <div class="ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ $campo['label'] }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $campo['value'] }}</p>
                        </div>
                    @endforeach

                    @if ($orden->observaciones)
                        <div class="ag-form-section__field--full ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_observaciones') }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $orden->observaciones }}</p>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-molecules.form-section :title="__('operaciones.ordenes.seccion_lotes')" :count="__('operaciones.ordenes.lotes_contador', ['cantidad' => count($lotes), 'hectareas' => $hectareasSolicitadas])">
                    <div class="ag-form-section__field--full">
                        <x-molecules.index-table columns="1fr 8rem 9rem">
                            <x-slot:head>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_lote') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_hectareas_lote') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_estado') }}</span>
                            </x-slot:head>

                            @foreach ($lotes as $lote)
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell">{{ $lote['label'] }}</span>
                                    <span role="cell" class="ag-ordenes__mono">{{ number_format((float) $lote['hectareas_solicitadas'], 2, ',', '.') }}</span>
                                    <span role="cell">
                                        <x-atoms.badge :variant="$lote['estado'] === 'asignado' ? 'success' : 'neutral'">
                                            {{ __('operaciones.ordenes.lote_estado_'.$lote['estado']) }}
                                        </x-atoms.badge>
                                    </span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section :title="__('operaciones.ordenes.seccion_actividad')">
                    <div class="ag-form-section__field--full">
                        <x-molecules.timeline :items="$actividad" />
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.form-section :title="__('operaciones.ordenes.seccion_limites')" :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 5])">
                        @foreach ($limitesItems as $limite)
                            <div class="ag-ordenes-detalle__campo">
                                <p class="ag-ordenes-detalle__campo-label">{{ $limite['label'] }}</p>
                                <p class="ag-ordenes-detalle__campo-valor">{{ $limite['value'] }}</p>
                            </div>
                        @endforeach

                        <div class="ag-form-section__field--full ag-ordenes-form__ayuda">
                            {{ __('operaciones.ordenes.seccion_limites_ayuda') }}
                        </div>
                    </x-molecules.form-section>

                    <x-molecules.form-section :title="__('operaciones.ordenes.seccion_vuelo')" :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 3])">
                        <div class="ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_altura_vuelo_m') }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $orden->altura_vuelo_m !== null ? "{$orden->altura_vuelo_m} m" : __('operaciones.ordenes.limite_sin_definir') }}</p>
                        </div>
                        <div class="ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_velocidad_vuelo_kmh') }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $orden->velocidad_vuelo_kmh !== null ? "{$orden->velocidad_vuelo_kmh} km/h" : __('operaciones.ordenes.limite_sin_definir') }}</p>
                        </div>
                        <div class="ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_ancho_pasada_m') }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $orden->ancho_pasada_m !== null ? "{$orden->ancho_pasada_m} m" : __('operaciones.ordenes.limite_sin_definir') }}</p>
                        </div>
                    </x-molecules.form-section>

                    <x-molecules.progress-meter
                        :title="__('operaciones.ordenes.avance_titulo')"
                        :percent="$porcentajeAsignado"
                        :summary-label="__('operaciones.ordenes.avance_resumen', ['asignadas' => $hectareasAsignadas, 'solicitadas' => $hectareasSolicitadas])"
                    />
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
