{{--
    Page: usuarios/index (GET /panel/usuarios, panel.usuarios.index)
    Listado de cuentas internas del panel (HU-45, tarea 39): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera →
    filtros → tabla → paginación. Mismo molde que personas/index.blade.php,
    con una columna de roles (chips) y persona asociada en vez de rol/base.

    Datos esperados (ver UsuariosController::index()):
    - $usuarios (LengthAwarePaginator<SecUser>): nombre ascendente, internas
      Y de portal (tarea 65, HU-41: ya no filtra por tipo a secas).
    - $rolesPorUsuario (array<int, list<string>>): nombres legibles de rol
      por id de usuario (vacío para una cuenta de portal: nunca tiene rol).
    - $etiquetasPersona (array<int, string>): nombre de persona por
      persona_id.
    - $filtros (array{q: string, tipo: string}): búsqueda y tipo aplicados
      (`tipo` es `''`/`interno`/`cliente`).

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
                        <x-atoms.button href="{{ route('panel.usuarios.create') }}" variant="primary" icon="add">
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
                        action="{{ route('panel.usuarios.index') }}"
                        :active-count="$filtros['tipo'] !== '' ? 1 : 0"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="tipo"
                            id="filtro-tipo"
                            label="{{ __('seguridad.usuarios.filtro_tipo') }}"
                            :options="[
                                'interno' => __('seguridad.usuarios.tipo_interno'),
                                'cliente' => __('seguridad.usuarios.tipo_cliente'),
                            ]"
                            :value="$filtros['tipo']"
                            placeholder="{{ __('seguridad.usuarios.filtro_tipo_todos') }}"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        action="{{ route('panel.usuarios.index') }}"
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
                <div class="ag-usuarios__tabla" role="table">
                    <div class="ag-usuarios__head" role="row">
                        <span role="columnheader" class="ag-usuarios__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_nombre') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_username') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_tipo') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_roles') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_persona') }}</span>
                        <span role="columnheader">{{ __('seguridad.usuarios.col_estado') }}</span>
                        <span role="columnheader" class="ag-usuarios__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </div>

                    @foreach ($usuarios as $usuario)
                        <div class="ag-usuarios__fila" role="row">
                            <span role="cell" class="ag-usuarios__indice">
                                {{ ($usuarios->currentPage() - 1) * $usuarios->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-usuarios__nombre">{{ $usuario->name }}</span>
                            <span role="cell" class="ag-usuarios__username">{{ $usuario->username }}</span>
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
                                <x-atoms.badge :variant="$usuario->state ? 'success' : 'danger'">
                                    {{ __($usuario->state ? 'seguridad.usuarios.estado_activo' : 'seguridad.usuarios.estado_bloqueado') }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-usuarios__acciones">
                                <x-organisms.row-actions>
                                    @puede('seguridad.usuario.editar')
                                        <x-atoms.button href="{{ route('panel.usuarios.edit', $usuario) }}" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('seguridad.usuarios.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('seguridad.usuario.bloquear')
                                        <form method="POST" action="{{ route('panel.usuarios.bloqueo', $usuario) }}">
                                            @csrf
                                            <x-atoms.button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                :icon="$usuario->state ? 'lock' : 'lock_open'"
                                            >
                                                {{ __($usuario->state ? 'seguridad.usuarios.bloquear' : 'seguridad.usuarios.desbloquear') }}
                                            </x-atoms.button>
                                        </form>
                                    @endpuede

                                    @puede('seguridad.usuario.eliminar')
                                        <form
                                            method="POST"
                                            action="{{ route('panel.usuarios.destroy', $usuario) }}"
                                            onsubmit="return confirm('{{ __('seguridad.usuarios.confirmar_baja') }}')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                                {{ __('seguridad.usuarios.eliminar_accion') }}
                                            </x-atoms.button>
                                        </form>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$usuarios" :aria-label="__('seguridad.usuarios.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
