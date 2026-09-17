{{--
    Page: cultivos/index (GET /panel/cultivos, panel.cultivos.index)
    Listado del catálogo de cultivos (HU-48, tarea 71; filtros y columnas
    agronómicas, ampliación 16/9/2026): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar (filter-panel +
    table-search) → tabla → paginación. Mismo molde que
    seguridad::pages.usuarios.index (buscador + filtro + row-actions).

    Datos esperados (ver CultivosController::index()): la cáscara de
    CascaraPanel, más:
    - $cultivos (LengthAwarePaginator<Cultivo>): nombre común ascendente.
    - $filtros (array{q: string, tipo_cultivo: string, ciclo_vida: string,
      activo: string}): valores aplicados, para dejar los campos con su
      valor tras el submit (`activo` es `''`/`1`/`0`).
    - $tiposCultivo (list<TipoCultivo>), $ciclosVida (list<CicloVidaCultivo>):
      opciones del filtro.

    Gateada por `comercial.cultivo.ver`, verificado server-side en el
    controlador. Los botones "Nuevo cultivo"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    CultivosController).

    Estilos en resources/css/pages/cultivos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.cultivos.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.cultivos.titulo')"
    >
        <div class="ag-cultivos">
            <x-organisms.page-header
                :title="__('comercial.cultivos.titulo')"
                :subtitle="__('comercial.cultivos.subtitulo')"
            >
                @puede('comercial.cultivo.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.cultivos.create')" variant="primary" icon="add">
                            {{ __('comercial.cultivos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-cultivos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosActivosCount = collect(['tipo_cultivo', 'ciclo_vida'])
                    ->filter(fn ($clave) => $filtros[$clave] !== '')
                    ->count();
            @endphp

            @if ($hayFiltrosActivos || $cultivos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.cultivos.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">

                        <x-atoms.select
                            name="tipo_cultivo"
                            id="filtro-tipo-cultivo"
                            :label="__('comercial.cultivos.filtro_tipo_cultivo')"
                            :options="collect($tiposCultivo)->mapWithKeys(fn ($tipo) => [$tipo->value => __('comercial.cultivos.tipo_cultivo_opcion.'.$tipo->value)])"
                            :value="$filtros['tipo_cultivo'] !== '' ? $filtros['tipo_cultivo'] : null"
                            :placeholder="__('comercial.cultivos.filtro_todos')"
                        />

                        <x-atoms.select
                            name="ciclo_vida"
                            id="filtro-ciclo-vida"
                            :label="__('comercial.cultivos.filtro_ciclo_vida')"
                            :options="collect($ciclosVida)->mapWithKeys(fn ($ciclo) => [$ciclo->value => __('comercial.cultivos.ciclo_vida_opcion.'.$ciclo->value)])"
                            :value="$filtros['ciclo_vida'] !== '' ? $filtros['ciclo_vida'] : null"
                            :placeholder="__('comercial.cultivos.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.cultivos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('comercial.cultivos.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($cultivos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('comercial.cultivos.filtro_vacio_titulo')"
                        :detail="__('comercial.cultivos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="grass"
                        :title="__('comercial.cultivos.vacio_titulo')"
                        :detail="__('comercial.cultivos.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-cultivos__tabla" role="table">
                    <div class="ag-cultivos__head" role="row">
                        <span role="columnheader" class="ag-cultivos__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('comercial.cultivos.col_nombre') }}</span>
                        <span role="columnheader">{{ __('comercial.cultivos.col_tipo') }}</span>
                        <span role="columnheader">{{ __('comercial.cultivos.col_ciclo_vida') }}</span>
                        <span role="columnheader" class="ag-cultivos__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </div>

                    @foreach ($cultivos as $cultivo)
                        <div class="ag-cultivos__fila" role="row">
                            <span role="cell" class="ag-cultivos__indice">
                                {{ ($cultivos->currentPage() - 1) * $cultivos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-cultivos__nombre-celda">
                                <span class="ag-cultivos__nombre">{{ $cultivo->nombre_comun }}</span>
                                @if ($cultivo->nombre_cientifico)
                                    <span class="ag-cultivos__nombre-cientifico">{{ $cultivo->nombre_cientifico }}</span>
                                @endif
                            </span>
                            <span role="cell">
                                @if ($cultivo->tipo_cultivo)
                                    <x-atoms.badge variant="neutral">
                                        {{ __('comercial.cultivos.tipo_cultivo_opcion.'.$cultivo->tipo_cultivo->value) }}
                                    </x-atoms.badge>
                                @endif
                            </span>
                            <span role="cell">
                                {{ $cultivo->ciclo_vida ? __('comercial.cultivos.ciclo_vida_opcion.'.$cultivo->ciclo_vida->value) : '—' }}
                            </span>

                            <span role="cell" class="ag-cultivos__acciones">
                                <x-organisms.row-actions>
                                    @puede('comercial.cultivo.editar')
                                        <x-atoms.button :href="route('panel.cultivos.edit', $cultivo)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('comercial.cultivos.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('comercial.cultivo.eliminar')
                                        <form
                                            method="POST"
                                            action="{{ route('panel.cultivos.destroy', $cultivo) }}"
                                            onsubmit="return confirm('{{ __('comercial.cultivos.confirmar_baja') }}')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                                {{ __('comercial.cultivos.eliminar_accion') }}
                                            </x-atoms.button>
                                        </form>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$cultivos" :aria-label="__('comercial.cultivos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
