{{--
    Page: alertas/index (GET /panel/alertas, panel.alertas.index)
    Bandeja de alertas por excepción (HU-19, tarea 26): el encargado de
    operaciones ve solo lo anómalo (batería caliente, dron sospechoso,
    condiciones forzadas, suma excedida) con filtros por estado/tipo y acción
    de atender.

    Datos esperados (ver AlertasController::index()): la cáscara de
    CascaraPanel, más:
    - $alertas (LengthAwarePaginator<Alerta>): más reciente primero.
    - $filtros (array{estado: ?string, tipo: ?string}): valores actualmente
      aplicados, para dejar el formulario con la selección hecha tras el
      submit.
    - $puedeAtender (bool): si el rol activo tiene `operaciones.alerta.atender`
      — sin él, la fila muestra el estado sin el botón (el servidor revalida
      igual en AlertasController::atender()).

    Gateada por el permiso `operaciones.alerta.ver`, verificado server-side
    en el controlador.

    Estilos en resources/css/pages/alertas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.alertas.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
    >
        <x-organisms.page-header
            :title="__('operaciones.alertas.titulo')"
            :subtitle="__('operaciones.alertas.subtitulo')"
        />

        @if (session('estado'))
            <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-alertas__aviso">
                {{ session('estado') }}
            </x-molecules.alert-strip>
        @endif

        @php $hayFiltrosActivos = $filtros['estado'] !== null || $filtros['tipo'] !== null; @endphp

        <form method="GET" action="{{ route('panel.alertas.index') }}" class="ag-filtros ag-alertas__filtros">
            @php
                $opcionesEstado = [
                    'pendiente' => __('operaciones.alertas.estado.pendiente'),
                    'atendida' => __('operaciones.alertas.estado.atendida'),
                ];
            @endphp

            <x-atoms.select
                name="estado"
                id="filtro-estado"
                label="{{ __('operaciones.alertas.filtro_estado') }}"
                :options="$opcionesEstado"
                :value="$filtros['estado']"
                :placeholder="__('operaciones.alertas.filtro_todos')"
            />

            @php
                $opcionesTipo = [
                    'bateria_caliente' => __('operaciones.alertas.tipo.bateria_caliente'),
                    'dron_sospechoso' => __('operaciones.alertas.tipo.dron_sospechoso'),
                    'condiciones_forzadas' => __('operaciones.alertas.tipo.condiciones_forzadas'),
                    'suma_excedida' => __('operaciones.alertas.tipo.suma_excedida'),
                ];
            @endphp

            <x-atoms.select
                name="tipo"
                id="filtro-tipo"
                label="{{ __('operaciones.alertas.filtro_tipo') }}"
                :options="$opcionesTipo"
                :value="$filtros['tipo']"
                :placeholder="__('operaciones.alertas.filtro_todos')"
            />

            <div class="ag-filtros__acciones ag-alertas__filtros-acciones">
                <x-atoms.button type="submit" variant="primary" size="md" icon="filter_alt">
                    {{ __('operaciones.alertas.filtrar') }}
                </x-atoms.button>

                @if ($hayFiltrosActivos)
                    <x-atoms.button href="{{ route('panel.alertas.index') }}" variant="text" size="md">
                        {{ __('operaciones.alertas.limpiar_filtros') }}
                    </x-atoms.button>
                @endif
            </div>
        </form>

        @if ($alertas->isEmpty())
            <x-molecules.alert-strip variant="info" icon="notifications_active" class="ag-alertas__aviso">
                {{ __($hayFiltrosActivos ? 'operaciones.alertas.filtro_vacio' : 'operaciones.alertas.vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-alertas__tabla" role="table">
                <div class="ag-alertas__head" role="row">
                    <span role="columnheader">{{ __('operaciones.alertas.col_tipo') }}</span>
                    <span role="columnheader">{{ __('operaciones.alertas.col_mensaje') }}</span>
                    <span role="columnheader">{{ __('operaciones.alertas.col_trabajo') }}</span>
                    <span role="columnheader">{{ __('operaciones.alertas.col_estado') }}</span>
                    <span role="columnheader">{{ __('operaciones.alertas.col_fecha') }}</span>
                    <span role="columnheader">{{ __('operaciones.alertas.col_accion') }}</span>
                </div>

                @foreach ($alertas as $alerta)
                    <div class="ag-alertas__fila" role="row">
                        <span role="cell">
                            <x-atoms.badge variant="warning">
                                {{ __("operaciones.alertas.tipo.{$alerta->tipo->value}") }}
                            </x-atoms.badge>
                        </span>

                        <span role="cell" class="ag-alertas__mensaje">{{ $alerta->mensaje }}</span>

                        <span role="cell">
                            {{ $alerta->trabajo_id !== null ? "#{$alerta->trabajo_id}" : __('operaciones.alertas.sin_trabajo') }}
                        </span>

                        <span role="cell">
                            <x-atoms.badge :variant="$alerta->estado->value === 'atendida' ? 'success' : 'warning'">
                                {{ __("operaciones.alertas.estado.{$alerta->estado->value}") }}
                            </x-atoms.badge>

                            @if ($alerta->estado->value === 'atendida')
                                <span class="ag-alertas__atendida-por">
                                    {{ __('operaciones.alertas.atendida_por', ['id' => $alerta->atendida_por, 'fecha' => $alerta->atendida_en?->format('d/m/Y H:i')]) }}
                                </span>
                            @endif
                        </span>

                        <span role="cell">{{ $alerta->created_at?->format('d/m/Y H:i') }}</span>

                        <span role="cell">
                            @if ($puedeAtender && $alerta->estado->value === 'pendiente')
                                <form method="POST" action="{{ route('panel.alertas.atender', $alerta) }}">
                                    @csrf
                                    <x-atoms.button type="submit" variant="outline" size="sm" icon="check_circle">
                                        {{ __('operaciones.alertas.atender') }}
                                    </x-atoms.button>
                                </form>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

            @if ($alertas->hasPages())
                <nav class="ag-alertas__paginacion" aria-label="{{ __('operaciones.alertas.paginacion_aria') }}">
                    @if (! $alertas->onFirstPage())
                        <x-atoms.button href="{{ $alertas->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                            {{ __('operaciones.alertas.paginacion_anterior') }}
                        </x-atoms.button>
                    @endif

                    <span class="ag-alertas__paginacion-info">
                        {{ __('operaciones.alertas.paginacion_info', ['actual' => $alertas->currentPage(), 'total' => $alertas->lastPage()]) }}
                    </span>

                    @if ($alertas->hasMorePages())
                        <x-atoms.button href="{{ $alertas->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                            {{ __('operaciones.alertas.paginacion_siguiente') }}
                        </x-atoms.button>
                    @endif
                </nav>
            @endif
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
