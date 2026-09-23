{{--
    Page: ordenes-trabajo/show (GET /panel/trabajos/{ordenTrabajo}, panel.trabajos.show)
    Ficha de una Orden de Trabajo. Replica la distribución de la ficha de la
    orden de aplicación (`ordenes/show.blade.php`, pedido del dueño, 21/9/2026;
    arquetipo Detalle, §6.4 de docs/diseno/guia_pantalla_panel.md) y reusa sus
    clases `ag-detalle*` (resources/css/pages/ordenes.css): KPI bajo la
    cabecera; columna principal con lo que se lee de corrido; aside pegajoso con
    avance, relacionado y actividad; y debajo, a todo el ancho, los trabajos.

    La columna principal se lee en el mismo orden que el alta, y dice lo mismo
    que ella: primero las INDICACIONES de la aplicación —calda, límites
    climáticos y parámetros de vuelo—, que se cargaron una sola vez y valen para
    todos los trabajos; después los TRABAJOS, agrupados por equipo: cada lote
    que le toca a un equipo es un trabajo con su propio estado, que es donde el
    equipo registra vuelos, equipamiento, hectáreas aplicadas y fotos.

    Datos esperados (ver OrdenesTrabajoController::show()): la cáscara de
    CascaraPanel, más:
    - $ordenTrabajo (OrdenTrabajo), $orden (?OrdenAplicacion, con
      `categoriaInsumo`), $clienteLabel (?string).
    - $hectareasTotales / $hectareasTerminadas (string, ya formateadas),
      $porcentajeTerminado (int), $cantidadTrabajos / $cantidadTerminados (int).
    - $equipos (list<array{etiqueta, hectareas, trabajos}>): los trabajos por equipo.
    - $etiquetasLote (array<int, string>), $vinculos (list), $actividad (list).
    - $puedeEditarTrabajo / $puedeEliminarTrabajo (bool).
    - $puedeEditarOrdenTrabajo (bool, tarea 127): «Editar» de la CABECERA —
      permiso `operaciones.trabajo.editar` más `PoliticaEdicionOrdenTrabajo::admiteEdicion()`
      (no todos sus trabajos validados). Va a `panel.trabajos.edit`.

    Gateada por `operaciones.trabajo.ver`. "Ver" del trabajo va siempre;
    "Editar"/"Eliminar" solo si hay permisos Y el trabajo no está `validado`
    (CLAUDE.md invariante 2). Los forms y modales de baja van FUERA de
    `row-actions`: ese organism repite su slot y duplicaría los ids.
--}}
@php
    $variantePorEstadoTablero = [
        'abierto' => 'neutral',
        'cerrado' => 'info',
        'validado' => 'success',
    ];

    $tipoInsumo = $orden?->categoriaInsumo?->tipo_insumo?->value;
    $valor = fn ($dato): string => $dato !== null && $dato !== '' ? (string) $dato : __('operaciones.ordenes_trabajo.sin_dato');

    // Calda: qué lleva, y el Ph y el caudal que correspondan al tipo de insumo.
    $llevaCalda = collect($ordenTrabajo->calda_productos ?? [])
        ->map(fn (string $producto): string => __('operaciones.ordenes_trabajo.calda_productos.'.$producto))
        ->implode(', ');
    $datosCalda = array_filter([
        __('operaciones.asignacion_equipos.campo_ph_agua') => $ordenTrabajo->ph_agua,
        __('operaciones.asignacion_equipos.campo_ph_calda') => $ordenTrabajo->ph_calda,
        __('operaciones.ordenes_trabajo.campo_litros_ha') => $ordenTrabajo->litros_ha,
        __('operaciones.ordenes_trabajo.campo_kilos_ha') => $ordenTrabajo->kilos_ha,
    ], fn ($dato): bool => $dato !== null);

    $datosClima = [
        __('operaciones.asignacion_equipos.campo_humedad_min_pct') => $ordenTrabajo->humedad_min_pct,
        __('operaciones.asignacion_equipos.campo_humedad_max_pct') => $ordenTrabajo->humedad_max_pct,
        __('operaciones.asignacion_equipos.campo_viento_max_kmh') => $ordenTrabajo->viento_max_kmh,
        __('operaciones.asignacion_equipos.campo_temperatura_max_c') => $ordenTrabajo->temperatura_max_c,
    ];
    $datosVuelo = [
        __('operaciones.asignacion_equipos.campo_altura_vuelo_m') => $ordenTrabajo->altura_vuelo_m,
        __('operaciones.asignacion_equipos.campo_velocidad_vuelo_kmh') => $ordenTrabajo->velocidad_vuelo_kmh,
        __('operaciones.asignacion_equipos.campo_ancho_pasada_m') => $ordenTrabajo->ancho_pasada_m,
    ];

    $todosTerminados = $cantidadTrabajos > 0 && $cantidadTerminados === $cantidadTrabajos;
    $trabajosConBaja = [];
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
        :vista-actual="__('operaciones.ordenes_trabajo.detalle_titulo', ['id' => $ordenTrabajo->id])"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="__('operaciones.ordenes_trabajo.detalle_titulo', ['id' => $ordenTrabajo->id])"
                :subtitle="__('operaciones.ordenes_trabajo.detalle_subtitulo', ['cliente' => $clienteLabel ?? '—', 'fecha' => $ordenTrabajo->created_at?->format('d/m/Y') ?? '—'])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$todosTerminados ? 'success' : 'neutral'">
                        {{ $todosTerminados ? __('operaciones.ordenes_trabajo.badge_terminada') : __('operaciones.ordenes_trabajo.badge_en_curso') }}
                    </x-atoms.badge>
                </x-slot:chip>

                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.trabajos.index')" :label="__('operaciones.ordenes_trabajo.volver')" />

                    @if ($puedeEditarOrdenTrabajo)
                        <x-atoms.button :href="route('panel.trabajos.edit', $ordenTrabajo)" variant="warning-outline" icon="edit">
                            {{ __('operaciones.ordenes_trabajo.editar') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

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
                    :label="__('operaciones.ordenes_trabajo.kpi_hectareas')"
                    icon="landscape"
                    :value="$hectareasTotales"
                    value-suffix="ha"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes_trabajo.kpi_equipos')"
                    icon="groups"
                    :value="count($equipos)"
                    state="distintivo-1"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes_trabajo.kpi_trabajos')"
                    icon="work_history"
                    :value="$cantidadTrabajos"
                    :foot="__('operaciones.ordenes_trabajo.kpi_trabajos_pie', ['terminados' => $cantidadTerminados, 'total' => $cantidadTrabajos])"
                    :foot-tone="$todosTerminados ? 'success' : 'muted'"
                    :state="$todosTerminados ? 'success' : null"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes_trabajo.kpi_aplicacion')"
                    icon="repeat"
                    :value="$ordenTrabajo->nro_aplicacion"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('operaciones.ordenes_trabajo.seccion_orden')">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.ordenes_trabajo.campo_orden') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('operaciones.ordenes_trabajo.detalle_orden', ['id' => $ordenTrabajo->orden_id, 'aplicacion' => $ordenTrabajo->nro_aplicacion]) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.ordenes_trabajo.campo_cliente') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $valor($clienteLabel) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.ordenes.campo_tipo_insumo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $tipoInsumo !== null ? __('operaciones.tipo_insumo.'.$tipoInsumo) : '—' }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.ordenes.campo_categoria_insumo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $valor($orden?->categoriaInsumo?->nombre) }}</p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="success" :title="__('operaciones.asignacion_equipos.seccion_calda')" :count="__('operaciones.ordenes_trabajo.indicacion_contador')">
                    <div class="ag-form-section__field--full ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.ordenes_trabajo.detalle_calda_productos') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $valor($llevaCalda) }}</p>
                    </div>
                    @foreach ($datosCalda as $etiqueta => $dato)
                        <div class="ag-detalle__campo">
                            <p class="ag-detalle__campo-label">{{ $etiqueta }}</p>
                            <p class="ag-detalle__campo-valor ag-ordenes__mono">{{ $dato }}</p>
                        </div>
                    @endforeach
                </x-molecules.form-section>

                <x-molecules.form-section accent="warning" :title="__('operaciones.ordenes_trabajo.seccion_clima')" :count="__('operaciones.ordenes_trabajo.indicacion_contador')">
                    @foreach ($datosClima as $etiqueta => $dato)
                        <div class="ag-detalle__campo">
                            <p class="ag-detalle__campo-label">{{ $etiqueta }}</p>
                            <p class="ag-detalle__campo-valor ag-ordenes__mono">{{ $valor($dato) }}</p>
                        </div>
                    @endforeach
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-1" :title="__('operaciones.ordenes_trabajo.seccion_vuelo')" :count="__('operaciones.ordenes_trabajo.indicacion_contador')">
                    @foreach ($datosVuelo as $etiqueta => $dato)
                        <div class="ag-detalle__campo">
                            <p class="ag-detalle__campo-label">{{ $etiqueta }}</p>
                            <p class="ag-detalle__campo-valor ag-ordenes__mono">{{ $valor($dato) }}</p>
                        </div>
                    @endforeach
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.progress-meter
                        :title="__('operaciones.ordenes_trabajo.avance_titulo')"
                        :percent="$porcentajeTerminado"
                        :summary-label="__('operaciones.ordenes_trabajo.avance_resumen', ['terminadas' => $hectareasTerminadas, 'total' => $hectareasTotales])"
                    />

                    <x-molecules.form-section accent="alert" :title="__('operaciones.ordenes.seccion_vinculos')">
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

                    <x-molecules.form-section accent="distintivo-2" :title="__('operaciones.ordenes.seccion_actividad')">
                        <div class="ag-form-section__field--full">
                            <x-molecules.timeline :items="$actividad" />
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>

            {{-- Los trabajos van a TODO el ancho, debajo del cuerpo de dos columnas: son
                 el contenido central de esta ficha y su tabla (seis columnas, con
                 acciones de fila) no entra en la columna principal junto al aside. --}}
            <x-molecules.form-section
                accent="info"
                :title="__('operaciones.ordenes_trabajo.seccion_trabajos')"
                :count="trans_choice('operaciones.ordenes_trabajo.trabajos_contador', $cantidadTrabajos, ['cantidad' => $cantidadTrabajos, 'hectareas' => $hectareasTotales])"
            >
                <p class="ag-form-section__field--full ag-ordenes-trabajo-form__ayuda">
                    {{ __('operaciones.ordenes_trabajo.seccion_trabajos_ayuda') }}
                </p>

                @if ($equipos === [])
                    <div class="ag-form-section__field--full">
                        <x-molecules.empty-state
                            icon="work_history"
                            :title="__('operaciones.ordenes_trabajo.trabajos_vacio')"
                            :detail="__('operaciones.ordenes_trabajo.trabajos_vacio_detalle')"
                        />
                    </div>
                @endif

                @foreach ($equipos as $equipo)
                    <div class="ag-form-section__field--full ag-ordenes-trabajo-detalle__equipo">
                        <div class="ag-ordenes-trabajo-detalle__equipo-cabecera">
                            <p class="ag-ordenes-trabajo-detalle__equipo-nombre">
                                <x-atoms.icon name="groups" size="sm" />
                                {{ $equipo['etiqueta'] }}
                            </p>
                            <p class="ag-ordenes-trabajo-detalle__equipo-resumen">
                                {{ trans_choice('operaciones.ordenes_trabajo.equipo_resumen', $equipo['trabajos']->count(), ['cantidad' => $equipo['trabajos']->count(), 'hectareas' => $equipo['hectareas']]) }}
                            </p>
                        </div>

                        {{-- Resumen de pago del equipo (ADR 0023) --}}
                        @if (array_key_exists('pago', $equipo))
                            <div class="ag-ordenes-trabajo-detalle__pago">
                                @if ($equipo['pago'] !== null)
                                    <p class="ag-ordenes-trabajo-detalle__pago-texto">
                                        {{ __('operaciones.ordenes_trabajo.pago_resumen', [
                                            'modalidad' => $equipo['pago']['modalidad'] ?? '—',
                                            'piloto' => number_format($equipo['pago']['monto_piloto'] ?? 0, 2, ',', '.'),
                                            'auxiliar' => number_format($equipo['pago']['monto_auxiliar'] ?? 0, 2, ',', '.'),
                                        ]) }}
                                    </p>
                                    @if ($equipo['pago']['negociado'] ?? false)
                                        <x-atoms.badge variant="success" tone="success">
                                            {{ __('operaciones.ordenes_trabajo.pago_negociado') }}
                                        </x-atoms.badge>
                                    @endif
                                    @if ($equipo['pago']['motivo'] ?? null)
                                        <p class="ag-ordenes-trabajo-detalle__pago-motivo">
                                            {{ __('operaciones.ordenes_trabajo.pago_negociado_motivo', ['motivo' => $equipo['pago']['motivo']]) }}
                                        </p>
                                    @endif
                                @else
                                    <p class="ag-ordenes-trabajo-detalle__pago-texto">
                                        {{ __('operaciones.ordenes_trabajo.pago_sin_condicion') }}
                                    </p>
                                @endif
                            </div>
                        @endif

                        <x-molecules.index-table columns="6rem minmax(0, 2fr) 7rem minmax(0, 1.2fr) 7rem var(--ag-row-actions-width)">
                            <x-slot:head>
                                <span role="columnheader">{{ __('operaciones.ordenes_trabajo.col_nro_trabajo') }}</span>
                                <span role="columnheader">{{ __('operaciones.asignacion_equipos.campo_lote') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes_trabajo.col_hectareas') }}</span>
                                <span role="columnheader">{{ __('operaciones.asignacion_equipos.campo_turno') }}</span>
                                <span role="columnheader">{{ __('operaciones.trabajos.col_estado') }}</span>
                                <span role="columnheader">{{ __('ui.tabla.col_acciones') }}</span>
                            </x-slot:head>

                            @foreach ($equipo['trabajos'] as $trabajo)
                                @php
                                    $estadoTablero = $trabajo->estadoTablero()->value;
                                    $editable = $estadoTablero !== 'validado';
                                    $turnoTexto = $trabajo->turno ? __('operaciones.asignacion_equipos.turno_'.$trabajo->turno->value) : '—';
                                    $horasTexto = $trabajo->turno_hora_inicio && $trabajo->turno_hora_fin
                                        ? $trabajo->turno_hora_inicio->format('H:i').' – '.$trabajo->turno_hora_fin->format('H:i')
                                        : null;
                                    if ($puedeEliminarTrabajo && $editable) {
                                        $trabajosConBaja[] = $trabajo;
                                    }
                                @endphp
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell" class="ag-ordenes__mono">#{{ $trabajo->id }}</span>
                                    <span role="cell">{{ $etiquetasLote[$trabajo->lote_id] ?? "#{$trabajo->lote_id}" }}</span>
                                    <span role="cell" class="ag-ordenes__mono">{{ number_format((float) $trabajo->hectareas_declaradas, 2, ',', '.') }}</span>
                                    <span role="cell" class="ag-ordenes__renglones">
                                        <span>{{ $turnoTexto }}</span>
                                        @if ($horasTexto)
                                            <span class="ag-ordenes-trabajo-detalle__turno">{{ $horasTexto }}</span>
                                        @endif
                                    </span>
                                    <span role="cell">
                                        <x-atoms.badge :variant="$variantePorEstadoTablero[$estadoTablero] ?? 'neutral'">
                                            {{ __('operaciones.trabajos.estado.'.$estadoTablero) }}
                                        </x-atoms.badge>
                                    </span>
                                    <span role="cell" class="ag-index-table__acciones">
                                        <x-organisms.row-actions>
                                            <x-atoms.button :href="route('panel.trabajos.detalle', $trabajo)" variant="info-outline" size="sm" icon="visibility">
                                                {{ __('operaciones.ordenes.ver_accion') }}
                                            </x-atoms.button>

                                            @if ($puedeEditarTrabajo && $editable)
                                                <x-atoms.button :href="route('panel.trabajos.detalle-editar', $trabajo)" variant="warning-outline" size="sm" icon="edit">
                                                    {{ __('operaciones.trabajos.editar') }}
                                                </x-atoms.button>
                                            @endif

                                            @if ($puedeEliminarTrabajo && $editable)
                                                <x-atoms.button
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    :data-bs-target="'#trabajo-eliminar-modal-'.$trabajo->id"
                                                    variant="danger-outline"
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
                    </div>
                @endforeach
            </x-molecules.form-section>

            {{-- Forms + modales de baja, fuera de `row-actions` (ver el docblock). --}}
            @foreach ($trabajosConBaja as $trabajo)
                <form id="trabajo-eliminar-{{ $trabajo->id }}" method="POST" action="{{ route('panel.trabajos.detalle-eliminar', $trabajo) }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>

                <x-molecules.confirm-modal
                    :id="'trabajo-eliminar-modal-'.$trabajo->id"
                    :form-id="'trabajo-eliminar-'.$trabajo->id"
                    :title="__('operaciones.ordenes_trabajo.baja_trabajo_titulo', ['id' => $trabajo->id])"
                    :message="__('operaciones.trabajos.confirmar_baja')"
                    :confirm-label="__('operaciones.trabajos.eliminar_accion')"
                    tone="danger"
                />
            @endforeach
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
