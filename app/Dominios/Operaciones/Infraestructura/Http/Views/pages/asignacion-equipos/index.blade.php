{{--
    Page: asignacion-equipos/index (GET /panel/asignacion-equipos, panel.asignacion-equipos.index)
    Listado de órdenes VIGENTES con su resumen de reparto (HU-70, tarea 85):
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera →
    tabla, sin filtros (universo acotado: solo hay una orden vigente por lote
    a la vez, invariante de `ope_ordenes_aplicacion_lote_vigente_unico`).

    Datos esperados (ver AsignacionEquiposController::index()): la cáscara de
    CascaraPanel, más:
    - $ordenes (Collection<int, OrdenAplicacion>): vigentes, emisión
      descendente.
    - $resumenes (Collection<int, array{hectareas_lote: string, asignadas: string, restantes: string}>):
      clave `orden->id`, ya resuelto por el controlador (invariante 6 de
      CLAUDE.md: strings decimales, nunca float).
    - $etiquetasContrato / $etiquetasLote (array<int, string>): mismo criterio
      que ordenes/index.blade.php — la vista nunca consulta Comercial (ADR
      0003 regla 3).
    - $loteIdsPorOrden (array<int, list<int>>): lotes de CADA orden (HU-92,
      tarea 107 — antes un único `lote_id` por orden), clave `orden->id`.

    Gateada por `operaciones.orden.asignar_equipos` (ficha propia, ver
    docblock de `AsignacionEquiposController` — no depende de
    `operaciones.orden.ver`). Estilos en
    resources/css/pages/asignacion-equipos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.asignacion_equipos.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.asignacion_equipos.titulo')"
    >
        <div class="ag-asignacion-equipos">
            <x-organisms.page-header
                :title="__('operaciones.asignacion_equipos.titulo')"
                :subtitle="__('operaciones.asignacion_equipos.subtitulo')"
            />

            @if ($ordenes->isEmpty())
                <x-molecules.alert-strip variant="info" icon="groups" class="ag-asignacion-equipos__aviso">
                    {{ __('operaciones.asignacion_equipos.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-asignacion-equipos__tabla" role="table">
                    <div class="ag-asignacion-equipos__head" role="row">
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_orden') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_contrato') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_lote') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_hectareas_lote') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_asignadas') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_restantes') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($ordenes as $orden)
                        @php
                            $resumen = $resumenes[$orden->id];
                            $lotesTexto = collect($loteIdsPorOrden[$orden->id] ?? [])
                                ->map(fn ($loteId) => $etiquetasLote[$loteId] ?? "#{$loteId}")
                                ->implode(', ');
                        @endphp
                        <div class="ag-asignacion-equipos__fila" role="row">
                            <span role="cell" class="ag-asignacion-equipos__mono">#{{ $orden->nro_aplicacion }}</span>
                            <span role="cell">{{ $etiquetasContrato[$orden->contrato_id] ?? "#{$orden->contrato_id}" }}</span>
                            <span role="cell">{{ $lotesTexto }}</span>
                            <span role="cell" class="ag-asignacion-equipos__mono">{{ number_format((float) $resumen['hectareas_lote'], 2, ',', '.') }}</span>
                            <span role="cell" class="ag-asignacion-equipos__mono">{{ number_format((float) $resumen['asignadas'], 2, ',', '.') }}</span>
                            <span role="cell" class="ag-asignacion-equipos__mono">{{ number_format((float) $resumen['restantes'], 2, ',', '.') }}</span>
                            <span role="cell" class="ag-asignacion-equipos__acciones">
                                <x-atoms.button href="{{ route('panel.asignacion-equipos.show', $orden) }}" variant="outline" size="sm" icon="groups">
                                    {{ __('operaciones.asignacion_equipos.asignar_accion') }}
                                </x-atoms.button>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
