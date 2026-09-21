{{--
    Page: ordenes-trabajo/show (GET /panel/trabajos/{ordenTrabajo}, panel.trabajos.show)
    Detalle maestro-detalle de una Orden de Trabajo (reforma 18/9/2026): cabecera
    con KPIs + secciones de parámetros + tabla de trabajos. Arquetipo Detalle,
    §6.4 de docs/diseno/guia_pantalla_panel.md.

    Datos esperados (ver OrdenesTrabajoController::show()): la cáscara de
    CascaraPanel, más:
    - $ordenTrabajo (OrdenTrabajo, con `orden` y `trabajos` cargadas).
    - $hectareasTotales (string): suma de hectáreas de los trabajos.
    - $cantidadEquipos (int): cantidad de equipos distintos.
    - $etiquetasEquipo / $etiquetasLote (array<int, string>): etiquetas por id.
    - $puedeEditarTrabajo / $puedeEliminarTrabajo (bool).

    Gateada por `operaciones.trabajo.ver`. "Ver detalle completo" del trabajo
    va siempre; "Editar"/"Eliminar" solo si hay permisos Y el trabajo no está
    `validado`. Estilos en resources/css/pages/ordenes-trabajo.css — cero
    color hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $variantePorEstadoTablero = [
        'abierto' => 'neutral',
        'cerrado' => 'info',
        'validado' => 'success',
    ];
@endphp
<x-templates.panel-shell :title="__('operaciones.ordenes_trabajo.detalle_titulo', ['id' => $ordenTrabajo->id])" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes_trabajo.titulo')"
    >
        <div class="ag-ordenes-trabajo-detalle">
            <x-organisms.page-header
                :title="__('operaciones.ordenes_trabajo.detalle_titulo', ['id' => $ordenTrabajo->id])"
            >
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.trabajos.index')" :label="__('operaciones.ordenes_trabajo.volver')" />
                </x-slot:actions>
            </x-organisms.page-header>

            <div class="ag-ordenes-trabajo-detalle__kpi">
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes_trabajo.kpi_hectareas')"
                    :value="number_format((float) $hectareasTotales, 2, ',', '.')"
                    unit="ha"
                    icon="landscape"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes_trabajo.kpi_equipos')"
                    :value="(string) $cantidadEquipos"
                    icon="groups"
                />
            </div>

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.seccion_condiciones')"
                :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => 8])"
            >
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(max(14rem, calc(50% - var(--ag-space-4) / 2)), 1fr)); gap: var(--ag-space-4)">
                    @php
                        $campos = [
                            'humedad_min_pct' => __('operaciones.asignacion_equipos.campo_humedad_min_pct'),
                            'humedad_max_pct' => __('operaciones.asignacion_equipos.campo_humedad_max_pct'),
                            'viento_max_kmh' => __('operaciones.asignacion_equipos.campo_viento_max_kmh'),
                            'temperatura_max_c' => __('operaciones.asignacion_equipos.campo_temperatura_max_c'),
                            'velocidad_max_kmh' => __('operaciones.asignacion_equipos.campo_velocidad_max_kmh'),
                            'altura_vuelo_m' => __('operaciones.asignacion_equipos.campo_altura_vuelo_m'),
                            'velocidad_vuelo_kmh' => __('operaciones.asignacion_equipos.campo_velocidad_vuelo_kmh'),
                            'ancho_pasada_m' => __('operaciones.asignacion_equipos.campo_ancho_pasada_m'),
                        ];
                    @endphp

                    @foreach ($campos as $campo => $label)
                        <div>
                            <p style="margin: 0 0 0.25rem 0; font-size: var(--ag-font-size-sm); color: var(--ag-color-text-muted); font-weight: 500">
                                {{ $label }}
                            </p>
                            <p style="margin: 0; font-family: var(--ag-font-family-mono); font-size: var(--ag-font-size-base)">
                                {{ $ordenTrabajo->{$campo} !== null ? $ordenTrabajo->{$campo} : __('operaciones.ordenes_trabajo.sin_dato') }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-molecules.form-section>

            @php
                // Calda de la tanda: qué lleva, Ph y caudal — solo los datos que
                // efectivamente se cargaron (una tanda de sólido no trae Ph ni litros; una de
                // líquido no trae kilos).
                $llevaCalda = collect($ordenTrabajo->calda_productos ?? [])
                    ->map(fn (string $producto): string => __('operaciones.ordenes_trabajo.calda_productos.'.$producto))
                    ->implode(', ');
                $datosCalda = array_filter([
                    __('operaciones.ordenes_trabajo.detalle_calda_productos') => $llevaCalda !== '' ? $llevaCalda : null,
                    __('operaciones.asignacion_equipos.campo_ph_agua') => $ordenTrabajo->ph_agua,
                    __('operaciones.asignacion_equipos.campo_ph_calda') => $ordenTrabajo->ph_calda,
                    __('operaciones.ordenes_trabajo.campo_litros_ha') => $ordenTrabajo->litros_ha,
                    __('operaciones.ordenes_trabajo.campo_kilos_ha') => $ordenTrabajo->kilos_ha,
                ], fn ($valor): bool => $valor !== null);
            @endphp
            @if ($datosCalda !== [])
                <x-molecules.form-section
                    :title="__('operaciones.ordenes_trabajo.seccion_ph_calda')"
                    :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => count($datosCalda)])"
                >
                    @foreach ($datosCalda as $etiqueta => $valor)
                        <div>
                            <p class="ag-ordenes-trabajo-detalle__dato-etiqueta">{{ $etiqueta }}</p>
                            <p class="ag-ordenes-trabajo-detalle__dato-valor">{{ $valor }}</p>
                        </div>
                    @endforeach
                </x-molecules.form-section>
            @endif

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.seccion_trabajos')"
                :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => $ordenTrabajo->trabajos->count()])"
            >
                @if ($ordenTrabajo->trabajos->isEmpty())
                    <x-molecules.alert-strip variant="info" icon="info">
                        {{ __('operaciones.ordenes_trabajo.trabajos_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.index-table columns="0.8fr 1fr 1.2fr 1fr 1.2fr 1.2fr 1fr var(--ag-row-actions-width)">
                        <x-slot:head>
                            <span role="columnheader">{{ __('operaciones.trabajos.col_trabajo') }}</span>
                            <span role="columnheader">{{ __('operaciones.asignacion_equipos.campo_equipo') }}</span>
                            <span role="columnheader">{{ __('operaciones.asignacion_equipos.campo_lote') }}</span>
                            <span role="columnheader">{{ __('operaciones.trabajos.col_hectareas') }}</span>
                            <span role="columnheader">{{ __('operaciones.asignacion_equipos.campo_turno') }}</span>
                            <span role="columnheader">{{ __('operaciones.trabajos.col_estado') }}</span>
                            <span role="columnheader">{{ __('ui.tabla.col_acciones') }}</span>
                        </x-slot:head>

                        @foreach ($ordenTrabajo->trabajos as $trabajo)
                            @php
                                $estadoTablero = $trabajo->estadoTablero();
                                $turnoLabel = $trabajo->turno ? __('operaciones.asignacion_equipos.turno_' . $trabajo->turno->value) : '—';
                                $turnoHora = $trabajo->turno_hora_inicio && $trabajo->turno_hora_fin
                                    ? "{$trabajo->turno_hora_inicio} - {$trabajo->turno_hora_fin}"
                                    : '—';
                            @endphp
                            <div class="ag-index-table__row" role="row">
                                <span role="cell">#{{ $trabajo->id }}</span>
                                <span role="cell">
                                    {{ $etiquetasEquipo[$trabajo->equipo_trabajo_id] ?? ($trabajo->equipo_trabajo_id ? "#{$trabajo->equipo_trabajo_id}" : __('operaciones.trabajos.campo_equipo_sin_asignar')) }}
                                </span>
                                <span role="cell">{{ $etiquetasLote[$trabajo->lote_id] ?? "#{$trabajo->lote_id}" }}</span>
                                <span role="cell" class="ag-ordenes-trabajo-detalle__mono">
                                    {{ number_format((float) $trabajo->hectareas_declaradas, 2, ',', '.') }} ha
                                </span>
                                <span role="cell">
                                    <small style="font-family: var(--ag-font-family-mono); color: var(--ag-color-text-muted)">
                                        {{ $turnoLabel }}<br>{{ $turnoHora }}
                                    </small>
                                </span>
                                <span role="cell">
                                    <x-atoms.badge :variant="$variantePorEstadoTablero[$estadoTablero->value] ?? 'neutral'">
                                        {{ __('operaciones.trabajos.estado.' . $estadoTablero->value) }}
                                    </x-atoms.badge>
                                </span>
                                <span role="cell" class="ag-index-table__acciones">
                                    <x-organisms.row-actions>
                                        <x-atoms.button
                                            :href="route('panel.trabajos.detalle', $trabajo)"
                                            variant="text"
                                            size="sm"
                                            icon="visibility"
                                        >
                                            {{ __('operaciones.trabajos.ver_detalle') }}
                                        </x-atoms.button>

                                        @if ($puedeEditarTrabajo && $estadoTablero->value !== 'validado')
                                            <x-atoms.button
                                                :href="route('panel.trabajos.detalle-editar', $trabajo)"
                                                variant="text"
                                                size="sm"
                                                icon="edit"
                                            >
                                                {{ __('operaciones.trabajos.editar') }}
                                            </x-atoms.button>
                                        @endif

                                        @if ($puedeEliminarTrabajo && $estadoTablero->value !== 'validado')
                                            <form
                                                id="trabajo-eliminar-{{ $trabajo->id }}"
                                                method="POST"
                                                action="{{ route('panel.trabajos.detalle-eliminar', $trabajo) }}"
                                                style="display: none"
                                            >
                                                @csrf
                                                @method('DELETE')
                                            </form>

                                            <x-molecules.confirm-modal
                                                :id="'trabajo-eliminar-modal-' . $trabajo->id"
                                                :form-id="'trabajo-eliminar-' . $trabajo->id"
                                                :title="__('operaciones.trabajos.editar_titulo', ['id' => $trabajo->id])"
                                                :message="__('operaciones.trabajos.confirmar_baja')"
                                                :confirm-label="__('operaciones.trabajos.eliminar_accion')"
                                                tone="danger"
                                            />

                                            <x-atoms.button
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#trabajo-eliminar-modal-{{ $trabajo->id }}"
                                                variant="text"
                                                size="sm"
                                                icon="delete"
                                            >
                                                {{ __('operaciones.trabajos.eliminar_accion') }}
                                            </x-atoms.button>
                                        @endif
                                    </x-organisms.row-actions>
                                </span>
                            </div>
                        @endforeach
                    </x-molecules.index-table>
                @endif
            </x-molecules.form-section>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
