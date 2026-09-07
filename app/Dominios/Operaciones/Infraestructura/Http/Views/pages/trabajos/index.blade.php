{{--
    Page: trabajos/index (GET /panel/trabajos, panel.trabajos.index)
    Tablero de Operaciones (HU-05, tarea 13; extendido en HU-15, tarea 15):
    trabajos con filtros por estado de tablero/lote/orden y paginado. El
    detalle de cada trabajo vive en trabajos/show.blade.php
    (panel.trabajos.show) — acá solo el listado y el link.

    Datos esperados (ver TrabajosController::index()): la cáscara de
    CascaraPanel, más:
    - $trabajos (LengthAwarePaginator<Trabajo>, con `sesiones` precargada):
      más reciente primero.
    - $filtros (array{estado: ?string, lote_id: ?int, orden_id: ?int}):
      valores actualmente aplicados, para dejar el formulario con la
      selección hecha tras el submit.
    - $lotesDisponibles (Collection<int>), $ordenesDisponibles
      (Collection<Trabajo> con solo orden_id/nro_aplicacion): opciones de
      los selects — solo las que de verdad aparecen entre los trabajos
      existentes, no el catálogo completo de Comercial (ADR 0003, regla 3:
      sin relación Eloquent cruzada).

    Gateada por el permiso `operaciones.trabajo.ver`, verificado
    server-side en el controlador (no hay acción mutable acá que ocultar
    con @puede).

    Estilos en resources/css/pages/trabajos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.trabajos.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
    >
        <x-organisms.page-header
            :title="__('operaciones.trabajos.titulo')"
            :subtitle="__('operaciones.trabajos.subtitulo')"
        />

        @php $hayFiltrosActivos = $filtros['estado'] !== null || $filtros['lote_id'] !== null || $filtros['orden_id'] !== null; @endphp

        <form method="GET" action="{{ route('panel.trabajos.index') }}" class="ag-filtros ag-trabajos__filtros">
            <div class="ag-input">
                <label for="filtro-estado" class="ag-input__label">{{ __('operaciones.trabajos.filtro_estado') }}</label>
                <div class="ag-input__control">
                    <select name="estado" id="filtro-estado" class="ag-input__field">
                        <option value="">{{ __('operaciones.trabajos.filtro_todos') }}</option>
                        <option value="abierto" @selected($filtros['estado'] === 'abierto')>{{ __('operaciones.trabajos.estado.abierto') }}</option>
                        <option value="cerrado" @selected($filtros['estado'] === 'cerrado')>{{ __('operaciones.trabajos.estado.cerrado') }}</option>
                        <option value="validado" @selected($filtros['estado'] === 'validado')>{{ __('operaciones.trabajos.estado.validado') }}</option>
                    </select>
                </div>
            </div>

            <div class="ag-input">
                <label for="filtro-lote" class="ag-input__label">{{ __('operaciones.trabajos.filtro_lote') }}</label>
                <div class="ag-input__control">
                    <select name="lote_id" id="filtro-lote" class="ag-input__field">
                        <option value="">{{ __('operaciones.trabajos.filtro_todos') }}</option>
                        @foreach ($lotesDisponibles as $loteId)
                            <option value="{{ $loteId }}" @selected($filtros['lote_id'] === $loteId)>
                                {{ __('operaciones.trabajos.filtro_lote_opcion', ['id' => $loteId]) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="ag-input">
                <label for="filtro-orden" class="ag-input__label">{{ __('operaciones.trabajos.filtro_orden') }}</label>
                <div class="ag-input__control">
                    <select name="orden_id" id="filtro-orden" class="ag-input__field">
                        <option value="">{{ __('operaciones.trabajos.filtro_todos') }}</option>
                        @foreach ($ordenesDisponibles as $orden)
                            <option value="{{ $orden->orden_id }}" @selected($filtros['orden_id'] === $orden->orden_id)>
                                {{ __('operaciones.trabajos.filtro_orden_opcion', ['id' => $orden->orden_id, 'aplicacion' => $orden->nro_aplicacion]) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="ag-filtros__acciones ag-trabajos__filtros-acciones">
                <x-atoms.button type="submit" variant="primary" size="md" icon="filter_alt">
                    {{ __('operaciones.trabajos.filtrar') }}
                </x-atoms.button>

                @if ($hayFiltrosActivos)
                    <x-atoms.button href="{{ route('panel.trabajos.index') }}" variant="text" size="md">
                        {{ __('operaciones.trabajos.limpiar_filtros') }}
                    </x-atoms.button>
                @endif
            </div>
        </form>

        @if ($trabajos->isEmpty())
            <x-molecules.alert-strip variant="info" icon="fact_check" class="ag-trabajos__aviso">
                {{ __($hayFiltrosActivos ? 'operaciones.trabajos.filtro_vacio' : 'operaciones.trabajos.vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-trabajos__tabla" role="table">
                <div class="ag-trabajos__head" role="row">
                    <span role="columnheader">{{ __('operaciones.trabajos.col_trabajo') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_estado') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_hectareas') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_inicio') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_fin') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_detalle') }}</span>
                </div>

                @foreach ($trabajos as $trabajo)
                    @php $estadoTablero = $trabajo->estadoTablero(); @endphp
                    <div class="ag-trabajos__fila" role="row">
                        <span role="cell" class="ag-trabajos__trabajo">#{{ $trabajo->id }}</span>

                        <span role="cell">
                            <x-atoms.badge :variant="match ($estadoTablero->value) {
                                'validado' => 'success',
                                'cerrado' => 'info',
                                default => 'warning',
                            }">
                                {{ __("operaciones.trabajos.estado.{$estadoTablero->value}") }}
                            </x-atoms.badge>
                        </span>

                        <span role="cell">{{ $trabajo->hectareas_declaradas }}</span>
                        <span role="cell">{{ $trabajo->inicio->format('d/m/Y H:i') }}</span>
                        <span role="cell">{{ $trabajo->fin?->format('d/m/Y H:i') ?? __('operaciones.trabajos.sin_fin') }}</span>

                        <span role="cell">
                            <x-atoms.button href="{{ route('panel.trabajos.show', $trabajo) }}" variant="outline" size="sm" icon="visibility">
                                {{ __('operaciones.trabajos.ver_detalle') }}
                            </x-atoms.button>
                        </span>

                        <details class="ag-trabajos__sesiones">
                            <summary>{{ __('operaciones.trabajos.sesiones_ver', ['cantidad' => $trabajo->sesiones->count()]) }}</summary>

                            @if ($trabajo->sesiones->isEmpty())
                                <p class="ag-trabajos__sesiones-vacio">{{ __('operaciones.trabajos.sesiones_vacio') }}</p>
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
                                                <x-atoms.badge :variant="$sesion->estado->value === 'cerrado' ? 'success' : 'warning'">
                                                    {{ __("operaciones.trabajos.estado.{$sesion->estado->value}") }}
                                                </x-atoms.badge>

                                                @if ($sesion->motivo_cierre !== null)
                                                    <span class="ag-trabajos__motivo-cierre">
                                                        {{ __("operaciones.trabajos.motivo_cierre.{$sesion->motivo_cierre}") }}
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
                        </details>
                    </div>
                @endforeach
            </div>

            @if ($trabajos->hasPages())
                <nav class="ag-trabajos__paginacion" aria-label="{{ __('operaciones.trabajos.paginacion_aria') }}">
                    @if (! $trabajos->onFirstPage())
                        <x-atoms.button href="{{ $trabajos->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                            {{ __('operaciones.trabajos.paginacion_anterior') }}
                        </x-atoms.button>
                    @endif

                    <span class="ag-trabajos__paginacion-info">
                        {{ __('operaciones.trabajos.paginacion_info', ['actual' => $trabajos->currentPage(), 'total' => $trabajos->lastPage()]) }}
                    </span>

                    @if ($trabajos->hasMorePages())
                        <x-atoms.button href="{{ $trabajos->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                            {{ __('operaciones.trabajos.paginacion_siguiente') }}
                        </x-atoms.button>
                    @endif
                </nav>
            @endif
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
