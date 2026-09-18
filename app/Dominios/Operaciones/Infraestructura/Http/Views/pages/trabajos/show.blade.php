{{--
    Page: trabajos/show (GET /panel/trabajos/detalle/{trabajo}, panel.trabajos.detalle)
    Detalle de un trabajo (HU-15, tarea 15; ruta renombrada en la reforma
    18/9/2026 — "Orden de Trabajo", ver docblock de `TrabajosController`):
    sus datos, sus sesiones (piloto, hectáreas, estado, motivo de cierre, y
    el motivo del rechazo cuando una sesión fue anulada por HU-14) y una
    sección de evidencias que hoy está siempre vacía — TE-07/HU-08/HU-09
    (compresión, fotos, captura del RC) son sprint 3 y no están
    implementadas. No se inventan datos ni tablas de evidencias para
    llenarla: la ausencia se muestra con normalidad.

    Datos esperados (ver TrabajosController::show()): la cáscara de
    CascaraPanel, más:
    - $trabajo (Trabajo, con `sesiones.rechazo`, `acta`, `reporteTecnico` y
      `ordenTrabajo` precargadas).

    Sin acciones de validar/rechazar/cerrar (eso es la cola de HU-14,
    pantalla distinta — panel.sesiones.validacion.*). SÍ tiene editar/eliminar
    (HU-93) mientras el trabajo no esté `validado` — mismas guardas que
    `Aplicacion/ActualizarTrabajo`/`EliminarTrabajo`, acá solo ocultas tras
    `@puede` + el estado de tablero (defensa en superficie: la guarda real
    vive en el caso de uso, no acá).

    Gateada por el permiso `operaciones.trabajo.ver`, verificado
    server-side en el controlador.

    Estilos en resources/css/pages/trabajos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php $estadoTablero = $trabajo->estadoTablero(); @endphp
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
    >
        <x-atoms.button :href="route('panel.trabajos.index')" variant="text" size="sm" icon="arrow_back">
            {{ __('operaciones.trabajos.volver') }}
        </x-atoms.button>

        <x-organisms.page-header :title="__('operaciones.trabajos.detalle_titulo', ['id' => $trabajo->id])">
            @if ($estadoTablero->value !== 'validado')
                <x-slot:actions>
                    @puede('operaciones.trabajo.editar')
                        <x-atoms.button :href="route('panel.trabajos.detalle-editar', $trabajo)" variant="warning-outline" size="sm" icon="edit">
                            {{ __('operaciones.trabajos.editar') }}
                        </x-atoms.button>
                    @endpuede

                    @puede('operaciones.trabajo.eliminar')
                        <form
                            method="POST"
                            action="{{ route('panel.trabajos.detalle-eliminar', $trabajo) }}"
                            onsubmit="return confirm('{{ __('operaciones.trabajos.confirmar_baja') }}')"
                        >
                            @csrf
                            @method('DELETE')
                            <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                {{ __('operaciones.trabajos.eliminar_accion') }}
                            </x-atoms.button>
                        </form>
                    @endpuede
                </x-slot:actions>
            @endif
        </x-organisms.page-header>

        <div class="ag-trabajo-detalle__resumen">
            <span class="ag-trabajo-detalle__campo">
                <strong>{{ __('operaciones.trabajos.col_estado') }}</strong>
                <x-atoms.badge :variant="match ($estadoTablero->value) {
                    'validado' => 'success',
                    'cerrado' => 'info',
                    default => 'warning',
                }">
                    {{ __("operaciones.trabajos.estado.{$estadoTablero->value}") }}
                </x-atoms.badge>
            </span>
            <span class="ag-trabajo-detalle__campo">
                <strong>{{ __('operaciones.trabajos.filtro_lote') }}</strong>
                {{ __('operaciones.trabajos.filtro_lote_opcion', ['id' => $trabajo->lote_id]) }}
            </span>
            <span class="ag-trabajo-detalle__campo">
                <strong>{{ __('operaciones.trabajos.filtro_orden') }}</strong>
                {{ __('operaciones.trabajos.filtro_orden_opcion', ['id' => $trabajo->orden_id, 'aplicacion' => $trabajo->nro_aplicacion]) }}
            </span>
            <span class="ag-trabajo-detalle__campo">
                <strong>{{ __('operaciones.trabajos.col_hectareas') }}</strong>
                {{ $trabajo->hectareas_declaradas }}
            </span>
            <span class="ag-trabajo-detalle__campo">
                <strong>{{ __('operaciones.trabajos.col_inicio') }}</strong>
                {{ $trabajo->inicio->format('d/m/Y H:i') }}
            </span>
            <span class="ag-trabajo-detalle__campo">
                <strong>{{ __('operaciones.trabajos.col_fin') }}</strong>
                {{ $trabajo->fin?->format('d/m/Y H:i') ?? __('operaciones.trabajos.sin_fin') }}
            </span>
        </div>

        <x-molecules.section-head :title="__('operaciones.trabajos.detalle_sesiones_titulo')" class="ag-trabajo-detalle__seccion" />

        @if ($trabajo->sesiones->isEmpty())
            <x-molecules.alert-strip variant="info" icon="flight" class="ag-trabajos__aviso">
                {{ __('operaciones.trabajos.sesiones_vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-trabajos__sesiones-tabla" role="table">
                <div class="ag-trabajos__sesiones-head" role="row">
                    <span role="columnheader">{{ __('operaciones.trabajos.col_piloto') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_estado') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_hectareas') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_inicio') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_fin') }}</span>
                </div>

                @foreach ($trabajo->sesiones as $sesion)
                    <div class="ag-trabajos__sesiones-fila" role="row">
                        <span role="cell">{{ __('operaciones.trabajos.sesion_piloto', ['id' => $sesion->piloto_id]) }}</span>

                        <span role="cell">
                            <x-atoms.badge :variant="$sesion->anulada_en !== null ? 'danger' : ($sesion->estado->value === 'validado' ? 'success' : 'warning')">
                                {{ $sesion->anulada_en !== null
                                    ? __('operaciones.trabajos.sesion_rechazada')
                                    : __("operaciones.trabajos.estado.{$sesion->estado->value}") }}
                            </x-atoms.badge>

                            @if ($sesion->motivo_cierre !== null)
                                <span class="ag-trabajos__motivo-cierre">
                                    {{ __("operaciones.trabajos.motivo_cierre.{$sesion->motivo_cierre}") }}
                                </span>
                            @endif

                            @if ($sesion->rechazo !== null)
                                <span class="ag-trabajos__motivo-cierre">
                                    {{ __('operaciones.trabajos.sesion_motivo_rechazo', ['motivo' => $sesion->rechazo->motivo]) }}
                                </span>
                            @endif
                        </span>

                        <span role="cell">{{ $sesion->hectareas_declaradas }}</span>
                        <span role="cell">{{ $sesion->inicio->format('d/m/Y H:i') }}</span>
                        <span role="cell">{{ $sesion->fin?->format('d/m/Y H:i') ?? __('operaciones.trabajos.sin_fin') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <x-molecules.section-head :title="__('operaciones.trabajos.detalle_acta_titulo')" class="ag-trabajo-detalle__seccion" />

        @if ($trabajo->acta === null)
            <x-molecules.alert-strip variant="info" icon="description" class="ag-trabajos__aviso">
                {{ __('operaciones.trabajos.detalle_acta_vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-trabajo-detalle__resumen">
                <span class="ag-trabajo-detalle__campo">
                    <strong>{{ __('operaciones.trabajos.col_estado') }}</strong>
                    <x-atoms.badge :variant="$trabajo->acta->estado->value === 'firmada' ? 'success' : 'warning'">
                        {{ __("operaciones.trabajos.acta_estado.{$trabajo->acta->estado->value}") }}
                    </x-atoms.badge>
                </span>
                <span class="ag-trabajo-detalle__campo">
                    <strong>{{ __('operaciones.trabajos.col_hectareas') }}</strong>
                    {{ $trabajo->acta->hectareas_conformadas }}
                </span>
                @if ($trabajo->acta->estado->value === 'firmada')
                    <span class="ag-trabajo-detalle__campo">
                        <strong>{{ __('operaciones.trabajos.acta_firmante') }}</strong>
                        {{ $trabajo->acta->firmante }}
                    </span>
                @endif
            </div>

            @if ($trabajo->acta->pdf_path !== null)
                <x-atoms.button :href="route('panel.trabajos.acta-pdf', $trabajo)" variant="outline" size="sm" icon="picture_as_pdf">
                    {{ __('operaciones.trabajos.acta_descargar_pdf') }}
                </x-atoms.button>
            @endif
        @endif

        @if ($puedeVerReporte)
            <x-molecules.section-head :title="__('operaciones.trabajos.detalle_reporte_titulo')" class="ag-trabajo-detalle__seccion" />

            @if ($trabajo->reporteTecnico === null || $trabajo->reporteTecnico->pdf_path === null)
                <x-molecules.alert-strip variant="info" icon="summarize" class="ag-trabajos__aviso">
                    {{ __('operaciones.trabajos.detalle_reporte_vacio') }}
                </x-molecules.alert-strip>
            @else
                <x-atoms.button :href="route('panel.trabajos.reporte-pdf', $trabajo)" variant="outline" size="sm" icon="picture_as_pdf">
                    {{ __('operaciones.trabajos.reporte_descargar_pdf') }}
                </x-atoms.button>
            @endif
        @endif

        <x-molecules.section-head :title="__('operaciones.trabajos.detalle_evidencias_titulo')" class="ag-trabajo-detalle__seccion" />

        <x-molecules.alert-strip variant="info" icon="photo_library" class="ag-trabajos__aviso">
            {{ __('operaciones.trabajos.detalle_evidencias_vacio') }}
        </x-molecules.alert-strip>

        <x-atoms.button :href="route('panel.trabajos.evidencias', $trabajo)" variant="outline" size="sm" icon="photo_library">
            {{ __('operaciones.trabajos.evidencias_ver_galeria') }}
        </x-atoms.button>
    </x-templates.panel-layout>
</x-templates.panel-shell>
