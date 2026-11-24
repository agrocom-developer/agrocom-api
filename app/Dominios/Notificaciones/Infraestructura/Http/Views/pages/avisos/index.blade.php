{{--
    Page: avisos/index (GET /panel/avisos, panel.avisos.index)
    Los avisos que el sistema envió a clientes, proveedores y administradores:
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md. Solo lectura;
    sirve para comprobar que un evento avisó a quien debía.

    Datos esperados (ver AvisosController::index()): la cáscara de CascaraPanel,
    más $avisos (LengthAwarePaginator<Notificacion>), $filas (list<array{icono,
    titulo, destinatario, leida, creado_en}>), $filtros (array{lectura}) y
    $lecturas (list<string>).

    Gateada por `notificaciones.aviso.ver`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('notificaciones.panel.titulo')" :tema="$tema">
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
        :vista-actual="__('notificaciones.panel.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('notificaciones.panel.titulo')"
                :subtitle="__('notificaciones.panel.subtitulo')"
            />

            @php
                $hayFiltrosActivos = $filtros['lectura'] !== null;
                $opcionesLectura = collect($lecturas)->mapWithKeys(fn ($clave) => [$clave => __('notificaciones.panel.lectura.'.$clave)])->all();
            @endphp

            @if ($hayFiltrosActivos || $avisos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.avisos.index')"
                        :active-count="$hayFiltrosActivos ? 1 : 0"
                    >
                        <x-atoms.select
                            name="lectura"
                            id="filtro-lectura"
                            :label="__('notificaciones.panel.filtro_lectura')"
                            :options="$opcionesLectura"
                            :value="$filtros['lectura']"
                            :placeholder="__('notificaciones.panel.filtro_todos')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($avisos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('notificaciones.panel.filtro_vacio_titulo')"
                        :detail="__('notificaciones.panel.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="notifications_active"
                        :title="__('notificaciones.panel.vacio_titulo')"
                        :detail="__('notificaciones.panel.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 2fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 0.9fr)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('notificaciones.panel.col_aviso') }}</span>
                        <span role="columnheader">{{ __('notificaciones.panel.col_destinatario') }}</span>
                        <span role="columnheader">{{ __('notificaciones.panel.col_lectura') }}</span>
                        <span role="columnheader">{{ __('notificaciones.panel.col_fecha') }}</span>
                    </x-slot:head>

                    @foreach ($filas as $fila)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($avisos->currentPage() - 1) * $avisos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <x-atoms.icon :name="$fila['icono']" size="sm" />
                                {{ $fila['titulo'] }}
                            </span>
                            <span role="cell">{{ $fila['destinatario'] }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$fila['leida'] ? 'neutral' : 'info'">{{ __('notificaciones.panel.lectura.'.($fila['leida'] ? 'leida' : 'sin_leer')) }}</x-atoms.badge>
                            </span>
                            <span role="cell">{{ $fila['creado_en'] }}</span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$avisos" :aria-label="__('notificaciones.panel.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
