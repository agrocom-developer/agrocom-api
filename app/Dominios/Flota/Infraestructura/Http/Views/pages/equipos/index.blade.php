{{--
    Page: equipos/index (GET /panel/equipos-flota, panel.equipos-flota.index)
    Equipos (piloto + dron + auxiliar) de todos los proveedores, solo lectura:
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md. Cada proveedor
    arma sus equipos desde su app; acá el administrador ve quién opera con qué y
    desde cuándo. Un piloto y un dron no pueden estar en dos equipos vigentes a
    la vez.

    Datos esperados (ver EquiposController::index()): la cáscara de CascaraPanel,
    más $equipos (LengthAwarePaginator<Equipo>, con `dron`), $proveedores,
    $pilotos y $auxiliares (array<int, string>: nombres por id), $hoy (string) y
    $filtros (array{vigencia: ?string}).

    Gateada por `flota.equipo.ver`, verificado server-side en el controlador.
    Estilos en resources/css/pages/proveedores.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('flota.equipos.titulo')" :tema="$tema">
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
        :vista-actual="__('flota.equipos.titulo')"
    >
        <div class="ag-proveedores">
            <x-organisms.page-header
                :title="__('flota.equipos.titulo')"
                :subtitle="__('flota.equipos.subtitulo')"
            />

            @php
                $hayFiltrosActivos = $filtros['vigencia'] !== null;
                $opcionesVigencia = ['vigentes' => __('flota.equipos.vigentes'), 'cerrados' => __('flota.equipos.cerrados')];
            @endphp

            @if ($hayFiltrosActivos || $equipos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.equipos-flota.index')"
                        :active-count="$hayFiltrosActivos ? 1 : 0"
                    >
                        <x-atoms.select
                            name="vigencia"
                            id="filtro-vigencia"
                            :label="__('flota.equipos.filtro_vigencia')"
                            :options="$opcionesVigencia"
                            :value="$filtros['vigencia']"
                            :placeholder="__('proveedores.filtro_todos')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($equipos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('flota.equipos.filtro_vacio_titulo')"
                        :detail="__('flota.equipos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="groups"
                        :title="__('flota.equipos.vacio_titulo')"
                        :detail="__('flota.equipos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.2fr) minmax(0, 1.1fr) minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1.1fr) minmax(0, 0.7fr)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('flota.equipos.col_proveedor') }}</span>
                        <span role="columnheader">{{ __('flota.equipos.col_piloto') }}</span>
                        <span role="columnheader">{{ __('flota.equipos.col_dron') }}</span>
                        <span role="columnheader">{{ __('flota.equipos.col_auxiliar') }}</span>
                        <span role="columnheader">{{ __('flota.equipos.col_vigencia') }}</span>
                        <span role="columnheader">{{ __('flota.equipos.col_estado') }}</span>
                    </x-slot:head>

                    @foreach ($equipos as $equipo)
                        @php
                            $vigente = $equipo->vigencia()->estaVigenteEl($hoy);
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($equipos->currentPage() - 1) * $equipos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">{{ $proveedores[$equipo->proveedor_id] ?? "#{$equipo->proveedor_id}" }}</span>
                            <span role="cell">{{ $pilotos[$equipo->piloto_id] ?? "#{$equipo->piloto_id}" }}</span>
                            <span role="cell">
                                {{ $equipo->dron->marca }} {{ $equipo->dron->modelo }}
                                <span class="ag-proveedores__sub">{{ $equipo->dron->numero_serie }}</span>
                            </span>
                            <span role="cell">{{ $equipo->auxiliar_id !== null ? ($auxiliares[$equipo->auxiliar_id] ?? "#{$equipo->auxiliar_id}") : __('flota.equipos.sin_auxiliar') }}</span>
                            <span role="cell">
                                {{ $equipo->vigente_desde->format('d/m/Y') }} → {{ $equipo->vigente_hasta?->format('d/m/Y') ?? __('flota.equipos.sin_fin') }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$vigente ? 'success' : 'neutral'">{{ $vigente ? __('flota.equipos.vigente') : __('flota.equipos.cerrado') }}</x-atoms.badge>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$equipos" :aria-label="__('flota.equipos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
