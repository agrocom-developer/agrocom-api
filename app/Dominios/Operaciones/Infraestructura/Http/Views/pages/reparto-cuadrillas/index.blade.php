{{--
    Page: reparto-cuadrillas/index (GET /panel/reparto-cuadrillas, panel.reparto-cuadrillas.index)
    Listado de órdenes VIGENTES con su resumen de reparto (HU-70, tarea 85;
    homogeneizado en la tarea 114): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla.

    Sin acción de alta en la cabecera a propósito: acá no nace ninguna orden,
    solo se reparte una que ya existe. La única acción de fila es entrar a su
    ficha de reparto, que es «Ver» (`info-outline`, plan §3.1).

    Datos esperados (ver RepartoCuadrillasController::index()): la cáscara de
    CascaraPanel, más:
    - $ordenes (Collection<int, OrdenAplicacion>): vigentes, emisión
      descendente, ya filtradas.
    - $resumenes (Collection<int, array{hectareas_lote: string, asignadas: string, restantes: string}>):
      clave `orden->id`, ya resuelto por el controlador (invariante 6 de
      CLAUDE.md: strings decimales, nunca float).
    - $etiquetasContrato / $etiquetasLote (array<int, string>): mismo criterio
      que ordenes/index.blade.php — la vista nunca consulta Comercial (ADR
      0003 regla 3).
    - $loteIdsPorOrden (array<int, list<int>>): lotes de CADA orden (HU-92,
      tarea 107 — antes un único `lote_id` por orden), clave `orden->id`.
    - $opcionesContrato (array<int, string>): opciones del filtro — solo los
      contratos que hoy tienen alguna orden vigente.
    - $filtros (array{contrato_id: ?int}): lo aplicado, para dejar el panel de
      filtros con la selección hecha.

    Gateada por `operaciones.orden.asignar_equipos` (ficha propia, ver
    docblock de `RepartoCuadrillasController` — no depende de
    `operaciones.orden.ver`). Estilos en
    resources/css/pages/reparto-cuadrillas.css — cero color hardcodeado
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
        <div class="ag-reparto-cuadrillas">
            <x-organisms.page-header
                :title="__('operaciones.asignacion_equipos.titulo')"
                :subtitle="__('operaciones.asignacion_equipos.subtitulo')"
            />

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosActivosCount = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
            @endphp

            @if ($hayFiltrosActivos || $ordenes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.reparto-cuadrillas.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <x-atoms.select
                            name="contrato_id"
                            id="filtro-contrato"
                            :label="__('operaciones.asignacion_equipos.filtro_contrato')"
                            :options="$opcionesContrato"
                            :value="$filtros['contrato_id']"
                            :placeholder="__('operaciones.asignacion_equipos.filtro_todos')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($ordenes->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.asignacion_equipos.filtro_vacio_titulo')"
                        :detail="__('operaciones.asignacion_equipos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="groups"
                        :title="__('operaciones.asignacion_equipos.vacio_titulo')"
                        :detail="__('operaciones.asignacion_equipos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 0.7fr) minmax(0, 1.6fr) minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_orden') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_contrato') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_lote') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_hectareas_lote') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_asignadas') }}</span>
                        <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_restantes') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($ordenes as $orden)
                        @php
                            $resumen = $resumenes[$orden->id];
                            $lotesTexto = collect($loteIdsPorOrden[$orden->id] ?? [])
                                ->map(fn ($loteId) => $etiquetasLote[$loteId] ?? "#{$loteId}")
                                ->implode(', ');
                            $restantes = (float) $resumen['restantes'];
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">{{ $loop->iteration }}</span>
                            <span role="cell" class="ag-reparto-cuadrillas__mono">#{{ $orden->nro_aplicacion }}</span>
                            <span role="cell">{{ $etiquetasContrato[$orden->contrato_id] ?? "#{$orden->contrato_id}" }}</span>
                            <span role="cell">{{ $lotesTexto }}</span>
                            <span role="cell" class="ag-reparto-cuadrillas__mono">{{ number_format((float) $resumen['hectareas_lote'], 2, ',', '.') }}</span>
                            <span role="cell" class="ag-reparto-cuadrillas__mono">{{ number_format((float) $resumen['asignadas'], 2, ',', '.') }}</span>
                            <span role="cell">
                                {{-- Lo que falta repartir es la cifra que decide si entrar a la
                                     ficha: en cero, la orden ya está repartida entera. --}}
                                <x-atoms.badge :variant="$restantes > 0 ? 'warning' : 'success'">
                                    {{ number_format($restantes, 2, ',', '.') }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button
                                        :href="route('panel.reparto-cuadrillas.show', $orden)"
                                        variant="info-outline"
                                        size="sm"
                                        icon="visibility"
                                    >
                                        {{ __('operaciones.asignacion_equipos.ver_accion') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
