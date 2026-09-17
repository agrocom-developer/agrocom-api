{{--
    Page: planes/index (GET /panel/planes-mantenimiento, panel.planes-mantenimiento.index)
    Listado de planes de mantenimiento preventivo (HU-38, tarea 54):
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera
    → tabla → paginación. Sin filtros (a diferencia de baterias/vehiculos):
    la lista de planes suele ser corta, un filtro no aporta todavía.

    Datos esperados (ver PlanesMantenimientoController::index()): la cáscara
    de CascaraPanel, más:
    - $planes (LengthAwarePaginator<PlanMantenimiento>): modelo ascendente,
      luego umbral ascendente. Cada fila ya trae `alerta` (bool) calculada
      por ListarPlanesMantenimiento — la vista no evalúa ningún umbral ni
      consulta horas de vuelo.

    Gateada por `mantenimiento.plan.ver`, verificado server-side en el
    controlador. Los botones "Nuevo plan"/"Editar"/"Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    PlanesMantenimientoController).

    Estilos en resources/css/pages/planes.css — cero color hardcodeado
    (CLAUDE.md invariante 11). El badge de alerta usa "warning" (ámbar),
    mismo criterio que la columna de alerta de baterias/index.blade.php.
--}}
<x-templates.panel-shell :title="__('mantenimiento.planes.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.planes.titulo')"
    >
        <div class="ag-planes">
            <x-organisms.page-header
                :title="__('mantenimiento.planes.titulo')"
                :subtitle="__('mantenimiento.planes.subtitulo')"
            >
                @puede('mantenimiento.plan.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.planes-mantenimiento.create')" variant="primary" icon="add">
                            {{ __('mantenimiento.planes.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-planes__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($planes->isEmpty())
                <x-molecules.empty-state
                    icon="checklist"
                    :title="__('mantenimiento.planes.vacio_titulo')"
                    :detail="__('mantenimiento.planes.vacio_detalle')"
                />
            @else
                <div class="ag-planes__tabla" role="table">
                    <div class="ag-planes__head" role="row">
                        <span role="columnheader">{{ __('mantenimiento.planes.col_modelo') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.planes.col_tarea') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.planes.col_horas_umbral') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.planes.col_alerta') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($planes as $plan)
                        <div class="ag-planes__fila" role="row">
                            <span role="cell" class="ag-planes__modelo">{{ $plan->modelo }}</span>
                            <span role="cell">{{ $plan->tarea }}</span>
                            <span role="cell">{{ $plan->horas_umbral }}</span>
                            <span role="cell">
                                @if ($plan->alerta)
                                    <x-atoms.badge variant="warning" icon="warning" title="{{ __('mantenimiento.planes.alerta_titulo') }}">
                                        {{ __('mantenimiento.planes.alerta_activa') }}
                                    </x-atoms.badge>
                                @else
                                    {{ __('mantenimiento.planes.sin_alerta') }}
                                @endif
                            </span>

                            <span role="cell" class="ag-planes__acciones">
                                @puede('mantenimiento.plan.editar')
                                    <x-atoms.button :href="route('panel.planes-mantenimiento.edit', $plan)" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('mantenimiento.planes.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('mantenimiento.plan.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.planes-mantenimiento.destroy', $plan) }}"
                                        onsubmit="return confirm('{{ __('mantenimiento.planes.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('mantenimiento.planes.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($planes->hasPages())
                    <nav class="ag-planes__paginacion" aria-label="{{ __('mantenimiento.planes.paginacion_aria') }}">
                        @if (! $planes->onFirstPage())
                            <x-atoms.button :href="$planes->previousPageUrl()" variant="outline" size="sm" icon="chevron_left">
                                {{ __('mantenimiento.planes.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-planes__paginacion-info">
                            {{ __('mantenimiento.planes.paginacion_info', ['actual' => $planes->currentPage(), 'total' => $planes->lastPage()]) }}
                        </span>

                        @if ($planes->hasMorePages())
                            <x-atoms.button :href="$planes->nextPageUrl()" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('mantenimiento.planes.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
