{{--
    Page: usuarios/index (GET /panel/usuarios, panel.usuarios.index)
    Listado de cuentas internas del panel (HU-45, tarea 39): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera →
    filtros → tabla → paginación. Mismo molde que clientes/index.blade.php,
    con una columna de roles (chips) y persona asociada, y con el estado de
    acceso de la cuenta (activo/bloqueado).

    Datos esperados (ver UsuariosController::index()):
    - $usuarios (LengthAwarePaginator<SecUser>): nombre ascendente, internas
      Y de portal (tarea 65, HU-41: ya no filtra por tipo a secas).
    - $rolesPorUsuario (array<int, list<string>>): nombres legibles de rol
      por id de usuario (vacío para una cuenta de portal: nunca tiene rol).
    - $rolesVerComoPorUsuario (array<int, array<int, string>>): roles vivos de
      cada cuenta interna (`id de usuario => [id de rol => nombre legible]`),
      para el selector de «Ver como». Vacío si el rol activo no tiene
      `seguridad.usuario.ver_como` (tarea 140).
    - $etiquetasPersona (array<int, string>): nombre de persona por
      persona_id.
    - $filtros (array{q: string, tipo: string}): búsqueda y tipo aplicados
      (`tipo` es `''`/`interno`/`cliente`).
    - $tonoPorEstado (array<string, string>): estado de acceso → tono, el mismo
      mapa para el badge, el botón de la fila y el modal
      (`UsuariosController::TONO_POR_ESTADO`).

    El estado (activo/bloqueado) SÍ se muestra, a diferencia de un catálogo con
    `activo` sin editor: bloquear es una acción de esta misma fila, con su
    propio permiso, y sin verlo el botón «Desbloquear» no tendría contexto.

    Gateada por `seguridad.usuario.ver`. Los botones "Nuevo usuario"/
    "Editar"/bloqueo/"Eliminar" se ocultan con `@puede` (presentación, no
    autorización — el servidor revalida en UsuariosController).

    Estilos en resources/css/pages/usuarios.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
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
        <div class="ag-usuarios">
            <x-organisms.page-header
                :title="__('seguridad.usuarios.titulo')"
                :subtitle="__('seguridad.usuarios.subtitulo')"
            >
                @puede('seguridad.usuario.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.usuarios.create')" variant="primary" icon="add">
                            {{ __('seguridad.usuarios.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-usuarios__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-usuarios__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $usuarios->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.usuarios.index')"
                        :active-count="$filtros['tipo'] !== '' ? 1 : 0"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="tipo"
                            id="filtro-tipo"
                            :label="__('seguridad.usuarios.filtro_tipo')"
                            :options="[
                                'interno' => __('seguridad.usuarios.tipo_interno'),
                                'cliente' => __('seguridad.usuarios.tipo_cliente'),
                            ]"
                            :value="$filtros['tipo']"
                            :placeholder="__('seguridad.usuarios.filtro_tipo_todos')"
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
                {{-- Las columnas de un badge (tipo, estado) y la de acciones son de ancho
                     fijo —nunca `auto`: ver el comentario de
                     resources/css/components/row-actions.css—; el usuario va bajo el
                     nombre en vez de en su propia columna, porque con ~1050 px útiles
                     (1440 con el menú lateral) ocho columnas no dejan sitio al texto. --}}
                <x-molecules.index-table
                    class="ag-usuarios__listado"
                    columns="3rem minmax(0, 1.8fr) 7.5rem minmax(0, 1.5fr) minmax(0, 1.2fr) 6rem var(--ag-row-actions-width)"
                >
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_nombre_usuario') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_tipo') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_roles') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_persona') }}</span>
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

                            // «Ver como» (tarea 140): solo cuentas habilitadas y ajenas; una de
                            // portal necesita contrato, una interna al menos un rol vivo.
                            $esCuentaDePortal = $usuario->type->value === 'cliente';
                            $rolesVerComo = $rolesVerComoPorUsuario[$usuario->id] ?? [];
                            $puedeVerComoEsta = $usuario->state
                                && $usuario->id !== auth('interno')->id()
                                && ($esCuentaDePortal ? $usuario->contrato_id !== null : $rolesVerComo !== []);
                            $formIdVerComo = "usuario-ver-como-{$usuario->id}";
                            $modalIdVerComo = "usuario-ver-como-modal-{$usuario->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($usuarios->currentPage() - 1) * $usuarios->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-usuarios__nombre">{{ $usuario->name }}</span>
                                <span class="ag-usuarios__username">{{ $usuario->username }}</span>
                            </span>
                            <span role="cell">
                                <x-atoms.badge variant="neutral">
                                    {{ __($usuario->type->value === 'cliente' ? 'seguridad.usuarios.tipo_cliente' : 'seguridad.usuarios.tipo_interno') }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-usuarios__roles">
                                @forelse (($rolesPorUsuario[$usuario->id] ?? []) as $nombreRol)
                                    <x-atoms.badge variant="neutral">{{ $nombreRol }}</x-atoms.badge>
                                @empty
                                    {{ __('seguridad.usuarios.sin_roles') }}
                                @endforelse
                            </span>
                            <span role="cell">{{ $usuario->persona_id !== null ? ($etiquetasPersona[$usuario->persona_id] ?? __('seguridad.usuarios.sin_persona')) : __('seguridad.usuarios.sin_persona') }}</span>
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
                                @puede('seguridad.usuario.ver_como')
                                    @if ($puedeVerComoEsta)
                                        <form id="{{ $formIdVerComo }}" method="POST" action="{{ route('panel.usuarios.ver-como', $usuario) }}">
                                            @csrf
                                        </form>

                                        <x-molecules.confirm-modal
                                            :id="$modalIdVerComo"
                                            :form-id="$formIdVerComo"
                                            :title="__('seguridad.vista_como.modal_titulo', ['nombre' => $usuario->name])"
                                            :message="__($esCuentaDePortal ? 'seguridad.vista_como.modal_mensaje_portal' : 'seguridad.vista_como.modal_mensaje_interno')"
                                            :confirm-label="__('seguridad.vista_como.modal_confirmar')"
                                            tone="info"
                                            modal-icon="visibility"
                                        >
                                            {{-- Con un solo rol el servidor lo infiere; con varios se elige uno. --}}
                                            @if (count($rolesVerComo) > 1)
                                                <x-atoms.select
                                                    name="rol_id"
                                                    :id="'ver-como-rol-'.$usuario->id"
                                                    :form="$formIdVerComo"
                                                    :label="__('seguridad.vista_como.campo_rol')"
                                                    :placeholder="__('seguridad.vista_como.campo_rol_placeholder')"
                                                    :options="$rolesVerComo"
                                                    required
                                                />
                                            @endif
                                        </x-molecules.confirm-modal>
                                    @endif
                                @endpuede

                                @puede('seguridad.usuario.bloquear')
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
                                @endpuede

                                @puede('seguridad.usuario.eliminar')
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
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('seguridad.usuario.editar')
                                        <x-atoms.button :href="route('panel.usuarios.edit', $usuario)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('seguridad.usuarios.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('seguridad.usuario.bloquear')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdBloqueo"
                                            :variant="$tonoPorEstado[$estadoDestino].'-outline'"
                                            size="sm"
                                            :icon="$usuario->state ? 'lock' : 'lock_open'"
                                        >
                                            {{ __($usuario->state ? 'seguridad.usuarios.bloquear' : 'seguridad.usuarios.desbloquear') }}
                                        </x-atoms.button>
                                    @endpuede

                                    {{-- Tercera a propósito: `row-actions` deja a la vista solo las dos
                                         primeras (Editar y Bloquear) y manda el resto al menú ⋮. «Ver como»
                                         es una herramienta de soporte, no una acción de todos los días. --}}
                                    @puede('seguridad.usuario.ver_como')
                                        @if ($puedeVerComoEsta)
                                            <x-atoms.button
                                                type="button"
                                                data-bs-toggle="modal"
                                                :data-bs-target="'#'.$modalIdVerComo"
                                                variant="info-outline"
                                                size="sm"
                                                icon="visibility"
                                            >
                                                {{ __('seguridad.vista_como.accion') }}
                                            </x-atoms.button>
                                        @endif
                                    @endpuede

                                    @puede('seguridad.usuario.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdEliminar"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('seguridad.usuarios.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
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
