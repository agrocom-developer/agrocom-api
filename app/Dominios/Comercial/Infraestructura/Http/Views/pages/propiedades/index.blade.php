{{--
    Page: propiedades/index (GET /panel/propiedades, panel.propiedades.index)
    Listado de propiedades: arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — mismo molde que
    `contratos/index.blade.php` (adenda 16/9/2026 a ADR 0018 punto 1: trae
    esta pantalla al patrón vigente, corrigiendo de paso la columna "Campos"
    que había quedado rota desde ADR 0020 — `$propiedad->campos_count` ya no
    existe, el caso de uso devuelve `lotes_count`/`hectareas_totales`).

    Datos esperados (ver PropiedadesController::index()): la cáscara de
    CascaraPanel, más:
    - $propiedades (LengthAwarePaginator<Propiedad>, con `cliente`/
      `departamento`/`municipio` precargadas, `lotes_count` y
      `hectareas_totales` vía withCount/withSum): nombre ascendente.
    - $clientesDisponibles (Collection<int, string>): id => razón social,
      para el filtro por cliente.
    - $departamentosDisponibles (Collection<int, string>): id => nombre,
      para el filtro por zona.
    - $filtros (array{q: string, cliente_id: int|null, departamento_id: int|null}).

    Gateada por `comercial.propiedad.ver`, verificado server-side en el
    controlador. Los botones "Nueva propiedad"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    PropiedadesController).

    Estilos en resources/css/pages/propiedades.css — cero color hardcodeado
    (CLAUDE.md invariante 11), salvo el chip `.ag-propiedades__color` (dato
    de negocio, no token de theming — ver su comentario en ese archivo).
--}}
<x-templates.panel-shell :title="__('comercial.propiedades.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.propiedades.titulo')"
    >
        <div class="ag-propiedades">
            <x-organisms.page-header
                :title="__('comercial.propiedades.titulo')"
                :subtitle="__('comercial.propiedades.subtitulo')"
            >
                @puede('comercial.propiedad.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.propiedades.create')" variant="primary" icon="add">
                            {{ __('comercial.propiedades.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-propiedades__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            {{-- Baja bloqueada por lotes asociados (PropiedadConLotesAsociados):
                 esta alerta faltaba en la versión anterior de esta pantalla —
                 sin ella, el intento de eliminar volvía al índice sin ningún
                 aviso visible. --}}
            @if ($errors->has('propiedad'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-propiedades__aviso">
                    {{ $errors->first('propiedad') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['cliente_id', 'departamento_id'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            @if ($hayFiltrosActivos || $propiedades->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.propiedades.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="cliente_id"
                            id="filtro-cliente"
                            :label="__('comercial.propiedades.filtro_cliente')"
                            :options="$clientesDisponibles"
                            :value="$filtros['cliente_id']"
                            :placeholder="__('comercial.propiedades.filtro_cliente_placeholder')"
                        />

                        <x-atoms.select
                            name="departamento_id"
                            id="filtro-departamento"
                            :label="__('comercial.propiedades.filtro_departamento')"
                            :options="$departamentosDisponibles"
                            :value="$filtros['departamento_id']"
                            :placeholder="__('comercial.propiedades.filtro_departamento_placeholder')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.propiedades.index')"
                        :value="$filtros['q']"
                        :placeholder="__('comercial.propiedades.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($propiedades->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('comercial.propiedades.filtro_vacio_titulo')"
                        :detail="__('comercial.propiedades.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="map"
                        :title="__('comercial.propiedades.vacio_titulo')"
                        :detail="__('comercial.propiedades.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem 2fr 1.3fr 1.3fr 0.8fr 0.8fr var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('comercial.propiedades.col_nombre') }}</span>
                        <span role="columnheader">{{ __('comercial.propiedades.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.propiedades.col_ubicacion') }}</span>
                        <span role="columnheader">{{ __('comercial.propiedades.col_lotes') }}</span>
                        <span role="columnheader">{{ __('comercial.propiedades.col_hectareas') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($propiedades as $propiedad)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($propiedades->currentPage() - 1) * $propiedades->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-propiedades__nombre">
                                @if ($propiedad->color)
                                    <span class="ag-propiedades__color" style="background-color: {{ $propiedad->color }}" aria-hidden="true"></span>
                                @endif
                                {{ $propiedad->nombre }}
                            </span>
                            <span role="cell">{{ $propiedad->cliente->razon_social }}</span>
                            <span role="cell">
                                {{ collect([$propiedad->departamento?->nombre, $propiedad->municipio?->nombre])->filter()->implode(' · ') ?: __('comercial.propiedades.sin_ubicacion') }}
                            </span>
                            <span role="cell" class="ag-propiedades__mono">{{ __('comercial.propiedades.lotes_cantidad', ['cantidad' => $propiedad->lotes_count]) }}</span>
                            <span role="cell" class="ag-propiedades__mono">{{ __('comercial.propiedades.hectareas_valor', ['cantidad' => number_format((float) ($propiedad->hectareas_totales ?? 0), 2, ',', '.')]) }}</span>

                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    @puede('comercial.propiedad.editar')
                                        <x-atoms.button :href="route('panel.propiedades.edit', $propiedad)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('comercial.propiedades.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('comercial.propiedad.eliminar')
                                        <form
                                            method="POST"
                                            action="{{ route('panel.propiedades.destroy', $propiedad) }}"
                                            onsubmit="return confirm('{{ __('comercial.propiedades.confirmar_baja') }}')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                                {{ __('comercial.propiedades.eliminar_accion') }}
                                            </x-atoms.button>
                                        </form>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$propiedades" :aria-label="__('comercial.propiedades.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
