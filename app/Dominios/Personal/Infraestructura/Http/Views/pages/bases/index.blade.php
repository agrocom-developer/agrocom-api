{{--
    Page: bases/index (GET /panel/bases, panel.bases.index)
    Listado de bases operativas (HU-26, tarea 37): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que drones/index.blade.php (tarea 36), sin
    sub-entidad: una base es catálogo simple (nombre, ubicación).

    Datos esperados (ver BasesController::index()): la cáscara de
    CascaraPanel, más:
    - $bases (LengthAwarePaginator<PerBase>): nombre ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `personal.base.ver`, verificado server-side en el
    controlador. Los botones "Nueva base"/"Editar"/"Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    BasesController).

    Estilos en resources/css/pages/bases.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('personal.bases.titulo')" :tema="$tema">
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
        :vista-actual="__('personal.bases.titulo')"
    >
        <div class="ag-bases">
            <x-organisms.page-header
                :title="__('personal.bases.titulo')"
                :subtitle="__('personal.bases.subtitulo')"
            >
                @puede('personal.base.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.bases.create')" variant="primary" icon="add">
                            {{ __('personal.bases.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-bases__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $bases->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.bases.index')"
                        :value="$filtros['q']"
                        :placeholder="__('personal.bases.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($bases->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="home_work" class="ag-bases__aviso">
                        {{ __('personal.bases.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="home_work"
                        :title="__('personal.bases.vacio_titulo')"
                        :detail="__('personal.bases.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-bases__tabla" role="table">
                    <div class="ag-bases__head" role="row">
                        <span role="columnheader" class="ag-bases__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('personal.bases.col_nombre') }}</span>
                        <span role="columnheader">{{ __('personal.bases.col_ubicacion') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($bases as $base)
                        <div class="ag-bases__fila" role="row">
                            <span role="cell" class="ag-bases__indice">
                                {{ ($bases->currentPage() - 1) * $bases->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-bases__nombre">{{ $base->nombre }}</span>
                            <span role="cell">{{ $base->ubicacion ?? __('personal.bases.sin_ubicacion') }}</span>

                            <span role="cell" class="ag-bases__acciones">
                                @puede('personal.base.editar')
                                    <x-atoms.button :href="route('panel.bases.edit', $base)" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('personal.bases.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('personal.base.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.bases.destroy', $base) }}"
                                        onsubmit="return confirm('{{ __('personal.bases.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('personal.bases.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$bases" :aria-label="__('personal.bases.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
