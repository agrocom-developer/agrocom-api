{{--
    Page: trabajos/index (GET /panel/trabajos, panel.trabajos.index)
    Pantalla mínima de Operaciones (HU-05, tarea 13): lista de trabajos con
    su estado y sus sesiones — alcanza con que el jefe vea que algo se
    cerró. Sin filtros ni detalle de evidencias (eso es HU-15).

    Datos esperados (ver TrabajosController::index()): la cáscara de
    CascaraPanel, más:
    - $trabajos (Collection<Trabajo>, con `sesiones` precargada): más
      reciente primero.

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
        <h1>{{ __('operaciones.trabajos.titulo') }}</h1>
        <p class="ag-trabajos__intro">{{ __('operaciones.trabajos.subtitulo') }}</p>

        @if ($trabajos->isEmpty())
            <x-molecules.alert-strip variant="info" icon="fact_check" class="ag-trabajos__aviso">
                {{ __('operaciones.trabajos.vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-trabajos__tabla" role="table">
                <div class="ag-trabajos__head" role="row">
                    <span role="columnheader">{{ __('operaciones.trabajos.col_trabajo') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_estado') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_hectareas') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_inicio') }}</span>
                    <span role="columnheader">{{ __('operaciones.trabajos.col_fin') }}</span>
                </div>

                @foreach ($trabajos as $trabajo)
                    <div class="ag-trabajos__fila" role="row">
                        <span role="cell" class="ag-trabajos__trabajo">#{{ $trabajo->id }}</span>

                        <span role="cell">
                            <x-atoms.badge :variant="$trabajo->estado->value === 'cerrado' ? 'success' : 'warning'">
                                {{ __("operaciones.trabajos.estado.{$trabajo->estado->value}") }}
                            </x-atoms.badge>
                        </span>

                        <span role="cell">{{ $trabajo->hectareas_declaradas }}</span>
                        <span role="cell">{{ $trabajo->inicio->format('d/m/Y H:i') }}</span>
                        <span role="cell">{{ $trabajo->fin?->format('d/m/Y H:i') ?? __('operaciones.trabajos.sin_fin') }}</span>

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
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
