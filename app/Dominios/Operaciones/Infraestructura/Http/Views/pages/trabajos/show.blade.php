{{--
    Page: trabajos/show (GET /panel/trabajos/detalle/{trabajo}, panel.trabajos.detalle)
    Detalle de UN trabajo (equipo×lote) — HU-15, tarea 15; homogeneizada a
    arquetipo Detalle en la tarea 124 (§6.4 de docs/diseno/guia_pantalla_panel.md,
    misma anatomía que `ordenes-trabajo/show.blade.php`, con Editar/Eliminar en
    la cabecera como ya tenía esta ficha — HU-93, tarea 108).

    Sus sesiones (piloto, dron, hectáreas, estado, motivo de cierre y el motivo
    del rechazo cuando una sesión fue anulada por HU-14) van como
    `index-table`. Evidencias: TE-07/HU-08/HU-09 (compresión, fotos, captura
    del RC) son sprint 3 y no están implementadas — la sección solo enlaza a
    la galería, sin inventar datos. El acta y el reporte técnico, cuando
    existen, son un vínculo en PDF en el aside (no una sección propia en el
    cuerpo): su estado de firma ya lo cuenta la "Actividad" de abajo.

    Sin acciones de validar/rechazar/cerrar (eso es la cola de HU-14, pantalla
    distinta — panel.sesiones.validacion.*). SÍ tiene editar/eliminar (HU-93)
    mientras el trabajo no esté `validado` — mismas guardas que
    `Aplicacion/ActualizarTrabajo`/`EliminarTrabajo`, acá solo ocultas tras
    `@puede` + el estado de tablero (defensa en superficie: la guarda real
    vive en el caso de uso, no acá). El aviso nativo del navegador
    desaparece: `confirm-modal`, con su `<form>`, fuera del `page-header`.

    Datos esperados (ver TrabajosController::show()): la cáscara de
    CascaraPanel, más:
    - $trabajo (Trabajo, con `sesiones.rechazo`, `sesiones.dron`, `acta`,
      `reporteTecnico` y `ordenTrabajo.equipos` precargadas).
    - $loteLabel (string), $cuadrillaLabel (?string, null si no tiene equipo
      asignado), $pagoEquipo (array{modalidad, monto_piloto, monto_auxiliar,
      negociado, motivo}|null — misma forma que arma la ficha de la OT).
    - $hectareasAplicadas (string, ya formateada), $porcentajeCobertura (int,
      0-100), $sesionesValidadas / $sesionesTotal (int).
    - $puedeEditar / $puedeEliminar (bool, ya cruzados con "no validado"),
      $puedeVerReporte (bool).
    - $vinculos (list), $actividad (list).
    - $etiquetasPiloto (array<int, string>): nombre real por `piloto_id`
      (tarea 131), resuelto en lote vía `LecturaPanelPersonal`. Sin entrada
      para el id (persona borrada o inexistente): fallback a `#id`, mismo
      criterio que `$etiquetasBase`/`$etiquetasContrato` en otras pantallas.

    Gateada por `operaciones.trabajo.ver`, verificado server-side en el
    controlador.

    Estilos en resources/css/pages/trabajos.css y
    resources/css/pages/detalle.css (arquetipo Detalle) — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $variantePorEstadoTablero = [
        'abierto' => 'neutral',
        'cerrado' => 'info',
        'validado' => 'success',
    ];
    $estadoTablero = $trabajo->estadoTablero();
    $coberturaCompleta = $porcentajeCobertura >= 100;
    $sesionesCompletas = $sesionesTotal > 0 && $sesionesValidadas === $sesionesTotal;
    $turnoTexto = $trabajo->turno ? __('operaciones.asignacion_equipos.turno_'.$trabajo->turno->value) : __('operaciones.trabajos.turno_sin_definir');
    $horarioTexto = $trabajo->turno_hora_inicio && $trabajo->turno_hora_fin
        ? $trabajo->turno_hora_inicio->format('H:i').' – '.$trabajo->turno_hora_fin->format('H:i')
        : null;
@endphp
<x-templates.panel-shell :title="__('operaciones.trabajos.detalle_titulo', ['id' => $trabajo->id])" :tema="$tema">
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
        :vista-actual="__('operaciones.trabajos.detalle_titulo', ['id' => $trabajo->id])"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="__('operaciones.trabajos.detalle_titulo', ['id' => $trabajo->id])"
                :subtitle="__('operaciones.trabajos.detalle_subtitulo', ['lote' => $loteLabel, 'cuadrilla' => $cuadrillaLabel ?? __('operaciones.trabajos.campo_equipo_sin_asignar')])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$variantePorEstadoTablero[$estadoTablero->value] ?? 'neutral'">
                        {{ __('operaciones.trabajos.estado.'.$estadoTablero->value) }}
                    </x-atoms.badge>
                </x-slot:chip>

                <x-slot:actions>
                    @if ($trabajo->ordenTrabajo !== null)
                        <x-molecules.boton-volver :href="route('panel.trabajos.show', $trabajo->ordenTrabajo)" :label="__('operaciones.trabajos.volver_a_ot', ['id' => $trabajo->orden_trabajo_id])" />
                    @else
                        <x-molecules.boton-volver :href="route('panel.trabajos.index')" :label="__('operaciones.trabajos.volver_al_listado')" />
                    @endif

                    @if ($puedeEditar)
                        <x-atoms.button :href="route('panel.trabajos.detalle-editar', $trabajo)" variant="warning-outline" icon="edit">
                            {{ __('operaciones.trabajos.editar') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeEliminar)
                        <x-atoms.button
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#trabajo-eliminar-modal"
                            variant="danger-outline"
                            icon="delete"
                        >
                            {{ __('operaciones.trabajos.eliminar_accion') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeEliminar)
                <form id="trabajo-eliminar" method="POST" action="{{ route('panel.trabajos.detalle-eliminar', $trabajo) }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>

                <x-molecules.confirm-modal
                    id="trabajo-eliminar-modal"
                    form-id="trabajo-eliminar"
                    :title="__('operaciones.ordenes_trabajo.baja_trabajo_titulo', ['id' => $trabajo->id])"
                    :message="__('operaciones.trabajos.confirmar_baja')"
                    :confirm-label="__('operaciones.trabajos.eliminar_accion')"
                    tone="danger"
                />
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
                    :label="__('operaciones.trabajos.col_hectareas')"
                    icon="landscape"
                    :value="number_format((float) $trabajo->hectareas_declaradas, 2, ',', '.')"
                    value-suffix="ha"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.trabajos.kpi_hectareas_aplicadas')"
                    icon="flight"
                    :value="number_format((float) $hectareasAplicadas, 2, ',', '.')"
                    value-suffix="ha"
                    :state="$coberturaCompleta ? 'success' : null"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.trabajos.kpi_sesiones')"
                    icon="fact_check"
                    :value="$sesionesTotal"
                    :foot="__('operaciones.trabajos.kpi_sesiones_pie', ['validadas' => $sesionesValidadas, 'total' => $sesionesTotal])"
                    :foot-tone="$sesionesCompletas ? 'success' : 'muted'"
                    :state="$sesionesCompletas ? 'success' : null"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.trabajos.kpi_turno')"
                    icon="schedule"
                    :value="$turnoTexto"
                    :foot="$horarioTexto"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('operaciones.trabajos.seccion_datos')" :count="__('operaciones.trabajos.campos_contador', ['cantidad' => 6])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.trabajos.campo_orden_aplicacion') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('operaciones.trabajos.filtro_orden_opcion', ['id' => $trabajo->orden_id, 'aplicacion' => $trabajo->nro_aplicacion]) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.trabajos.campo_orden_trabajo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $trabajo->orden_trabajo_id !== null ? "#{$trabajo->orden_trabajo_id}" : __('operaciones.ordenes_trabajo.sin_dato') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.trabajos.campo_lote') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $loteLabel }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.trabajos.campo_equipo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $cuadrillaLabel ?? __('operaciones.trabajos.campo_equipo_sin_asignar') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.trabajos.campo_turno') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $turnoTexto }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('operaciones.trabajos.campo_horario') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $horarioTexto ?? __('operaciones.ordenes_trabajo.sin_dato') }}</p>
                    </div>

                    @if ($pagoEquipo !== null)
                        <div class="ag-form-section__field--full ag-detalle__campo">
                            <p class="ag-detalle__campo-label">{{ __('operaciones.trabajos.campo_pago') }}</p>
                            <p class="ag-detalle__campo-valor">
                                {{ __('operaciones.ordenes_trabajo.pago_resumen', [
                                    'modalidad' => $pagoEquipo['modalidad'],
                                    'piloto' => number_format((float) $pagoEquipo['monto_piloto'], 2, ',', '.'),
                                    'auxiliar' => number_format((float) $pagoEquipo['monto_auxiliar'], 2, ',', '.'),
                                ]) }}
                                @if ($pagoEquipo['negociado'])
                                    · {{ __('operaciones.ordenes_trabajo.pago_negociado') }}
                                @endif
                            </p>
                        </div>
                    @elseif ($trabajo->ordenTrabajo !== null)
                        <div class="ag-form-section__field--full ag-detalle__campo">
                            <p class="ag-detalle__campo-label">{{ __('operaciones.trabajos.campo_pago') }}</p>
                            <p class="ag-detalle__campo-valor">{{ __('operaciones.ordenes_trabajo.pago_sin_condicion') }}</p>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('operaciones.trabajos.detalle_sesiones_titulo')" :count="(string) $trabajo->sesiones->count()">
                    @if ($trabajo->sesiones->isEmpty())
                        <div class="ag-form-section__field--full">
                            <x-molecules.empty-state
                                icon="flight"
                                :title="__('operaciones.trabajos.sesiones_vacio')"
                            />
                        </div>
                    @else
                        <div class="ag-form-section__field--full">
                            <x-molecules.index-table columns="1fr 6rem 6rem 8rem 1fr 1fr">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('operaciones.trabajos.col_piloto') }}</span>
                                    <span role="columnheader">{{ __('operaciones.trabajos.col_dron') }}</span>
                                    <span role="columnheader">{{ __('operaciones.trabajos.col_hectareas') }}</span>
                                    <span role="columnheader">{{ __('operaciones.trabajos.col_estado') }}</span>
                                    <span role="columnheader">{{ __('operaciones.trabajos.col_motivo_cierre') }}</span>
                                    <span role="columnheader">{{ __('operaciones.trabajos.col_motivo_rechazo') }}</span>
                                </x-slot:head>

                                @foreach ($trabajo->sesiones as $sesion)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $etiquetasPiloto[$sesion->piloto_id] ?? "#{$sesion->piloto_id}" }}</span>
                                        <span role="cell">{{ $sesion->dron?->identificador ?? '—' }}</span>
                                        <span role="cell" class="ag-ordenes__mono">{{ number_format((float) $sesion->hectareas_declaradas, 2, ',', '.') }}</span>
                                        <span role="cell">
                                            <x-atoms.badge :variant="$sesion->anulada_en !== null ? 'danger' : ($sesion->estado->value === 'validado' ? 'success' : 'warning')">
                                                {{ $sesion->anulada_en !== null
                                                    ? __('operaciones.trabajos.sesion_rechazada')
                                                    : __('operaciones.trabajos.estado.'.$sesion->estado->value) }}
                                            </x-atoms.badge>
                                        </span>
                                        <span role="cell">{{ $sesion->motivo_cierre !== null ? __('operaciones.trabajos.motivo_cierre.'.$sesion->motivo_cierre) : '—' }}</span>
                                        <span role="cell">{{ $sesion->rechazo?->motivo ?? '—' }}</span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-2" :title="__('operaciones.trabajos.detalle_evidencias_titulo')">
                    <div class="ag-form-section__field--full">
                        <p class="ag-detalle__campo-valor">{{ __('operaciones.trabajos.detalle_evidencias_vacio') }}</p>

                        <x-atoms.button :href="route('panel.trabajos.evidencias', $trabajo)" variant="outline" icon="photo_library">
                            {{ __('operaciones.trabajos.evidencias_ver_galeria') }}
                        </x-atoms.button>
                    </div>
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.progress-meter
                        :title="__('operaciones.trabajos.avance_titulo')"
                        :percent="$porcentajeCobertura"
                        :summary-label="__('operaciones.trabajos.avance_resumen', ['aplicadas' => number_format((float) $hectareasAplicadas, 2, ',', '.'), 'declaradas' => number_format((float) $trabajo->hectareas_declaradas, 2, ',', '.')])"
                    />

                    @if (count($vinculos))
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
                    @endif

                    <x-molecules.form-section accent="distintivo-2" :title="__('operaciones.ordenes.seccion_actividad')">
                        <div class="ag-form-section__field--full">
                            <x-molecules.timeline :items="$actividad" />
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
