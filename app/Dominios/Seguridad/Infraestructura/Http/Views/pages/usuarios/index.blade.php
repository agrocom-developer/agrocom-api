{{--
    Page: usuarios/index (GET /panel/usuarios, panel.usuarios.index)
    Listado de cuentas del panel, solo personal de Agrocom (HU-45, tarea 39; ADR 0046): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → buscador y
    filtros → tabla → paginación. Mismo molde que clientes/index.blade.php y
    pilotos/index.blade.php, con una columna de roles (chips) y el estado de acceso
    de la cuenta (activo/bloqueado).

    Datos esperados (ver UsuariosController::index()): la cáscara de CascaraPanel, más
    - $usuarios (LengthAwarePaginator<SecUser>): nombre ascendente, solo cuentas de tipo
      `panel`. Las cuentas de app (cliente y proveedor) no se administran desde acá.
    - $rolesPorUsuario (array<int, list<array{id, nombre}>>): roles de cada usuario con su id; cada uno
      enlaza a «Ver rol» (`panel.roles.show`) cuando $puedeVerRoles.
    - $puedeVerRoles (bool): si el actor tiene `seguridad.rol.ver`.
    - $filtros (array{q: string, estado: ?string, rol: ?string}): filtros aplicados.
    - $opcionesRol (array<int, string>): id de rol → nombre legible, para el filtro.
    - $tonoPorEstado (array<string, string>): estado de acceso → tono, el mismo mapa para el
      badge, el botón de la fila y el modal (`UsuariosController::TONO_POR_ESTADO`).
    - $puedeCrear, $puedeEditar, $puedeBloquear, $puedeEliminar (bool): presentación; el
      servidor revalida cada acción en UsuariosController.

    Acciones de fila en el orden del panel: Ver, Editar, Bloquear/Desbloquear (transición
    en gris) y Eliminar al final. Gateada por `seguridad.usuario.ver`.
--}}
<x-templates.panel-shell :title="__('seguridad.usuarios.titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.usuarios.titulo')"
    >
        <div class="ag-proveedores">
            <x-organisms.page-header
                :title="__('seguridad.usuarios.titulo')"
                :subtitle="__('seguridad.usuarios.subtitulo')"
            >
                @if ($puedeCrear)
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.usuarios.create')" variant="primary" icon="add">{{ __('seguridad.usuarios.nueva') }}</x-atoms.button>
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
                $filtrosPanelActivos = collect(['estado', 'rol'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
                $opcionesEstado = collect(array_keys($tonoPorEstado))->mapWithKeys(fn ($estado) => [$estado => __('seguridad.usuarios.estado_'.$estado)])->all();
            @endphp

            @if ($hayFiltrosActivos || $usuarios->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.usuarios.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('seguridad.usuarios.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('seguridad.usuarios.filtro_todos')"
                        />
                        <x-atoms.select
                            name="rol"
                            id="filtro-rol"
                            :label="__('seguridad.usuarios.filtro_rol')"
                            :options="$opcionesRol"
                            :value="$filtros['rol']"
                            :placeholder="__('seguridad.usuarios.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.usuarios.index')"
                        :value="$filtros['q']"
                        :placeholder="__('seguridad.usuarios.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($usuarios->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('seguridad.usuarios.filtro_vacio_titulo')"
                        :detail="__('seguridad.usuarios.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="badge"
                        :title="__('seguridad.usuarios.vacio_titulo')"
                        :detail="__('seguridad.usuarios.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.8fr) minmax(0, 1.8fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_nombre_usuario') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_roles') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($usuarios as $usuario)
                        @php
                            $estadoValor = $usuario->state ? 'activo' : 'bloqueado';
                            $estadoDestino = $usuario->state ? 'bloqueado' : 'activo';
                            $formIdBloqueo = "usuario-bloqueo-{$usuario->id}";
                            $formIdEliminar = "usuario-eliminar-{$usuario->id}";
                            $modalIdBloqueo = "usuario-bloqueo-modal-{$usuario->id}";
                            $modalIdEliminar = "usuario-eliminar-modal-{$usuario->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($usuarios->currentPage() - 1) * $usuarios->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-proveedores__nombre">{{ $usuario->name }}</span>
                                <span class="ag-proveedores__sub">{{ $usuario->username }}</span>
                            </span>
                            <span role="cell">
                                @forelse (($rolesPorUsuario[$usuario->id] ?? []) as $rolDeLaCuenta)
                                    @if ($puedeVerRoles)
                                        <a href="{{ route('panel.roles.show', $rolDeLaCuenta['id']) }}" class="ag-enlace-insignia" title="{{ __('seguridad.usuarios.ver_rol') }}">
                                            <x-atoms.badge variant="neutral">{{ $rolDeLaCuenta['nombre'] }}</x-atoms.badge>
                                        </a>
                                    @else
                                        <x-atoms.badge variant="neutral">{{ $rolDeLaCuenta['nombre'] }}</x-atoms.badge>
                                    @endif
                                @empty
                                    {{ __('seguridad.usuarios.sin_roles') }}
                                @endforelse
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">
                                    {{ __('seguridad.usuarios.estado_'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Forms y modales FUERA de row-actions a propósito: ese
                                     organism repite su slot dos veces (visible/menú, ver su
                                     docblock), así que un <form> o un modal con id ahí adentro
                                     se duplicaría — y el que cae dentro del menú ⋮ queda oculto
                                     con él y nunca abre. Los disparadores sí van adentro (son
                                     botones sin id propio). Mismo criterio que contratos/index. --}}
                                @if ($puedeBloquear)
                                    <form id="{{ $formIdBloqueo }}" method="POST" action="{{ route('panel.usuarios.bloqueo', $usuario) }}">
                                        @csrf
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdBloqueo"
                                        :form-id="$formIdBloqueo"
                                        :title="__($usuario->state ? 'seguridad.usuarios.confirmar_bloquear_titulo' : 'seguridad.usuarios.confirmar_desbloquear_titulo')"
                                        :message="__($usuario->state ? 'seguridad.usuarios.confirmar_bloquear' : 'seguridad.usuarios.confirmar_desbloquear')"
                                        :confirm-label="__($usuario->state ? 'seguridad.usuarios.bloquear' : 'seguridad.usuarios.desbloquear')"
                                        :tone="$tonoPorEstado[$estadoDestino]"
                                        :modal-icon="$usuario->state ? 'lock' : 'lock_open'"
                                    >
                                        @include('seguridad::pages.usuarios._estado-transicion', ['desde' => $estadoValor, 'hacia' => $estadoDestino])
                                    </x-molecules.confirm-modal>
                                @endif

                                @if ($puedeEliminar)
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.usuarios.destroy', $usuario) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('seguridad.usuarios.confirmar_eliminar_titulo')"
                                        :message="__('seguridad.usuarios.confirmar_baja')"
                                        :confirm-label="__('seguridad.usuarios.eliminar_accion')"
                                    />
                                @endif

                                <x-organisms.row-actions>
                                    <x-slot:ver>
                                        <x-molecules.row-action kind="ver" :href="route('panel.usuarios.show', $usuario)" icon="visibility" :label="__('seguridad.usuarios.ver')" />
                                    </x-slot:ver>

                                    @if ($puedeEditar)
                                        <x-slot:editar>
                                            <x-molecules.row-action kind="editar" :href="route('panel.usuarios.edit', $usuario)" icon="edit" :label="__('seguridad.usuarios.editar')" />
                                        </x-slot:editar>
                                    @endif

                                    @if ($puedeBloquear)
                                        <x-slot:transiciones>
                                            <x-molecules.row-action
                                                kind="transicion"
                                                :tone="$tonoPorEstado[$estadoDestino]"
                                                :modal="$modalIdBloqueo"
                                                :icon="$usuario->state ? 'lock' : 'lock_open'"
                                                :label="__($usuario->state ? 'seguridad.usuarios.bloquear' : 'seguridad.usuarios.desbloquear')"
                                            />
                                        </x-slot:transiciones>
                                    @endif

                                    @if ($puedeEliminar)
                                        <x-slot:eliminar>
                                            <x-molecules.row-action kind="eliminar" :modal="$modalIdEliminar" icon="delete" :label="__('seguridad.usuarios.eliminar_accion')" />
                                        </x-slot:eliminar>
                                    @endif
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$usuarios" :aria-label="__('seguridad.usuarios.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
