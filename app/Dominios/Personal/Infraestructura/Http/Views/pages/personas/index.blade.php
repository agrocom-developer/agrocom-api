{{--
    Page: personas/index (GET /panel/personas, panel.personas.index)
    Listado de personas operativas (HU-26, tarea 37): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla
    → paginación. Mismo molde que bases/index.blade.php (misma tarea), con
    dos columnas más (rol, base) y un estado (activo/inactivo).

    Datos esperados (ver PersonasController::index()): la cáscara de
    CascaraPanel, más:
    - $personas (LengthAwarePaginator<PerPersona>, con `base` precargada):
      nombre ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `personal.persona.ver`, verificado server-side en el
    controlador. Los botones "Nueva persona"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    PersonasController).

    Estilos en resources/css/pages/personas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('personal.personas.titulo')" :tema="$tema">
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
        :vista-actual="__('personal.personas.titulo')"
    >
        <div class="ag-personas">
            <x-organisms.page-header
                :title="__('personal.personas.titulo')"
                :subtitle="__('personal.personas.subtitulo')"
            >
                @puede('personal.persona.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.personas.create')" variant="primary" icon="add">
                            {{ __('personal.personas.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-personas__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $personas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.personas.index')"
                        :value="$filtros['q']"
                        :placeholder="__('personal.personas.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($personas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="badge" class="ag-personas__aviso">
                        {{ __('personal.personas.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="badge"
                        :title="__('personal.personas.vacio_titulo')"
                        :detail="__('personal.personas.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-personas__tabla" role="table">
                    <div class="ag-personas__head" role="row">
                        <span role="columnheader" class="ag-personas__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_nombre') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_rol') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_base') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_tarifa') }}</span>
                        <span role="columnheader">{{ __('personal.personas.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($personas as $persona)
                        <div class="ag-personas__fila" role="row">
                            <span role="cell" class="ag-personas__indice">
                                {{ ($personas->currentPage() - 1) * $personas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-personas__nombre">{{ $persona->nombre }}</span>
                            <span role="cell">{{ __('personal.roles.'.$persona->rol->value) }}</span>
                            <span role="cell">{{ $persona->base?->nombre ?? __('personal.personas.sin_base') }}</span>
                            <span role="cell" class="ag-personas__tarifa">
                                {{ $persona->tarifa_ha !== null ? __('personal.personas.tarifa_valor', ['monto' => $persona->tarifa_ha]) : __('personal.personas.sin_tarifa') }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$persona->activo ? 'success' : 'neutral'">
                                    {{ __($persona->activo ? 'personal.personas.estado_activo' : 'personal.personas.estado_inactivo') }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-personas__acciones">
                                @puede('personal.persona.desempenio')
                                    <x-atoms.button :href="route('panel.personas.desempenio', $persona)" variant="outline" size="sm" icon="insights">
                                        {{ __('personal.personas.desempenio.ver') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('personal.persona.editar')
                                    <x-atoms.button :href="route('panel.personas.edit', $persona)" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('personal.personas.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('personal.persona.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.personas.destroy', $persona) }}"
                                        onsubmit="return confirm('{{ __('personal.personas.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('personal.personas.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$personas" :aria-label="__('personal.personas.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
