{{--
    Page: roles/index (GET /panel/roles, panel.roles.index)
    Listado de roles del panel: arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → buscador y filtros → tabla →
    paginación. Mismo molde que usuarios/index.blade.php, con las dos métricas del
    rol (pantallas y acciones que otorga) y cuántas cuentas lo tienen.

    Cada fila cuenta PANTALLAS y ACCIONES por separado, no "34 permisos": la
    partición es la que hace legible el modelo (ver CatalogoDePermisos) y es la
    misma que estructura la pantalla de la matriz.

    Datos esperados (ver RolesController::index()): la cáscara de CascaraPanel, más
    - $listadoRoles (LengthAwarePaginator<array{rol, nombre, pantallas, acciones, usuarios,
      esRolActivo, esDelSistema}>): el administrador (id 1) primero.
    - $totales (array{pantallas: int, acciones: int}): denominadores de las métricas.
    - $filtros (array{q: string, estado: ?string}): filtros aplicados.
    - $puedeCrear, $puedeEditar, $puedeEliminar, $puedeAsignar (bool): presentación; el
      servidor revalida cada acción en RolesController y en los casos de uso.

    Acciones de fila en el orden del panel: Ver, Editar, Permisos y Eliminar al final.
    Eliminar no se ofrece para el rol del sistema (administrador, id 1): la regla la hace
    cumplir EliminarRol, esto solo no ofrece lo que va a fallar. Gateada por `seguridad.rol.ver`.
--}}
<x-templates.panel-shell :title="__('seguridad.roles.titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.roles.titulo')"
    >
        <div class="ag-proveedores">
            <x-organisms.page-header
                :title="__('seguridad.roles.titulo')"
                :subtitle="__('seguridad.roles.subtitulo')"
            >
                @if ($puedeCrear)
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.roles.create')" variant="primary" icon="add">{{ __('seguridad.roles.nuevo') }}</x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first() }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = ($filtros['estado'] ?? null) !== null ? 1 : 0;
                $opcionesEstado = ['activo' => __('seguridad.roles.estado_activo'), 'inactivo' => __('seguridad.roles.estado_inactivo')];
            @endphp

            @if ($hayFiltrosActivos || $listadoRoles->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.roles.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('seguridad.roles.col_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('seguridad.roles.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.roles.index')"
                        :value="$filtros['q']"
                        :placeholder="__('seguridad.roles.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($listadoRoles->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('seguridad.roles.filtro_vacio_titulo')"
                        :detail="__('seguridad.roles.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="shield_person"
                        :title="__('seguridad.roles.vacio_titulo')"
                        :detail="__('seguridad.roles.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 2.2fr) minmax(0, 0.9fr) minmax(0, 0.9fr) minmax(0, 0.8fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_rol') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_pantallas') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_acciones') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_usuarios') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($listadoRoles as $fila)
                        @php
                            $rol = $fila['rol'];
                            $formIdEliminar = "rol-eliminar-{$rol->id}";
                            $modalIdEliminar = "rol-eliminar-modal-{$rol->id}";
                            $puedeEliminarEste = $puedeEliminar && ! $fila['esDelSistema'];
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($listadoRoles->currentPage() - 1) * $listadoRoles->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-proveedores__nombre">
                                    {{ $fila['nombre'] }}
                                    @if ($fila['esDelSistema'])
                                        <x-atoms.badge variant="info" icon="lock">{{ __('seguridad.roles.badge_sistema') }}</x-atoms.badge>
                                    @endif
                                    @if ($fila['esRolActivo'])
                                        <x-atoms.badge variant="success">{{ __('seguridad.roles.badge_rol_activo') }}</x-atoms.badge>
                                    @endif
                                </span>
                                <span class="ag-proveedores__sub">{{ $rol->description }}</span>
                            </span>
                            <span role="cell">
                                <span class="ag-index-table__mono">{{ $fila['pantallas'] }}</span>
                                <span class="ag-proveedores__sub">{{ __('seguridad.roles.de_total', ['total' => $totales['pantallas']]) }}</span>
                            </span>
                            <span role="cell">
                                <span class="ag-index-table__mono">{{ $fila['acciones'] }}</span>
                                <span class="ag-proveedores__sub">{{ __('seguridad.roles.de_total', ['total' => $totales['acciones']]) }}</span>
                            </span>
                            <span role="cell">
                                @if ($fila['usuarios'] === 0)
                                    <span class="ag-proveedores__sub">{{ __('seguridad.roles.sin_usuarios') }}</span>
                                @else
                                    <span class="ag-index-table__mono">{{ $fila['usuarios'] }}</span>
                                @endif
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$rol->state ? 'success' : 'neutral'">
                                    {{ __($rol->state ? 'seguridad.roles.estado_activo' : 'seguridad.roles.estado_inactivo') }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- El form y el modal van FUERA de row-actions: ese organism puede
                                     repetir su slot en el menú ⋮ y un form o un modal con id ahí
                                     adentro se duplicaría. Los disparadores sí van adentro. --}}
                                @if ($puedeEliminarEste)
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.roles.destroy', $rol) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('seguridad.roles.confirmar_eliminar_titulo')"
                                        :message="__('seguridad.roles.confirmar_eliminar', ['rol' => $fila['nombre']])"
                                        :confirm-label="__('seguridad.roles.eliminar')"
                                    />
                                @endif

                                <x-organisms.row-actions>
                                    <x-slot:ver>
                                        <x-molecules.row-action kind="ver" :href="route('panel.roles.show', $rol)" icon="visibility" :label="__('seguridad.roles.ver')" />
                                    </x-slot:ver>

                                    @if ($puedeEditar)
                                        <x-slot:editar>
                                            <x-molecules.row-action kind="editar" :href="route('panel.roles.edit', $rol)" icon="edit" :label="__('seguridad.roles.editar')" />
                                        </x-slot:editar>
                                    @endif

                                    @if ($puedeAsignar)
                                        <x-slot:permisos>
                                            <x-molecules.row-action kind="permisos" :href="route('panel.roles.permisos.edit', $rol)" icon="key" :label="__('seguridad.roles.permisos')" />
                                        </x-slot:permisos>
                                    @endif

                                    @if ($puedeEliminarEste)
                                        <x-slot:eliminar>
                                            <x-molecules.row-action kind="eliminar" :modal="$modalIdEliminar" icon="delete" :label="__('seguridad.roles.eliminar')" />
                                        </x-slot:eliminar>
                                    @endif
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$listadoRoles" :aria-label="__('seguridad.roles.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
