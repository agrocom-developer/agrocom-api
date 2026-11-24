{{--
    Page: zonas/index (GET /panel/zonas, panel.zonas.index)
    Zonas de Santa Cruz agrupadas por macrorregión: arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md. Cada zona muestra cuántos terrenos tiene y
    cuánta demanda abierta (solicitudes abiertas o con ofertas); las zonas
    alimentan los filtros del mapa de los pilotos y los avisos de nuevas
    solicitudes. No se crean ni se borran desde el panel.

    Datos esperados (ver ZonasController::index()): la cáscara de CascaraPanel,
    más $zonas (Collection<Zona>), $terrenos y $abiertas (array<int, int>) y
    $puedeEditar (bool).

    Gateada por `solicitudes.zona.ver`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('solicitudes.zonas.titulo')" :tema="$tema">
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
        :vista-actual="__('solicitudes.zonas.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('solicitudes.zonas.titulo')"
                :subtitle="__('solicitudes.zonas.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($zonas->isEmpty())
                <x-molecules.empty-state
                    icon="map"
                    :title="__('solicitudes.zonas.vacio_titulo')"
                    :detail="__('solicitudes.zonas.vacio_detalle')"
                />
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_zona') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_region') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_terrenos') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_abiertas') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($zonas as $zona)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">{{ $loop->iteration }}</span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">{{ $zona->nombre }}</span>
                                <span class="ag-solicitudes__sub">{{ $zona->centroide_lat }}, {{ $zona->centroide_lng }}</span>
                            </span>
                            <span role="cell">{{ $zona->macrorregion ? __('solicitudes.macrorregion.'.$zona->macrorregion) : __('solicitudes.panel.sin_zona') }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $terrenos[$zona->id] ?? 0 }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $abiertas[$zona->id] ?? 0 }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                @if ($puedeEditar)
                                    <x-organisms.row-actions>
                                        <x-atoms.button :href="route('panel.zonas.edit', $zona)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('solicitudes.zonas.editar') }}
                                        </x-atoms.button>
                                    </x-organisms.row-actions>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
