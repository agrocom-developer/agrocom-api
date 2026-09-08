{{--
    Page: roles/index (GET /panel/roles, panel.roles.index)
    Listado del catálogo de roles: arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → tabla. Sin filtros: son
    cinco filas y crecen de a una por año, un buscador sería mobiliario.

    Cada fila cuenta PANTALLAS y ACCIONES por separado, no "34 permisos": la
    partición es la que hace legible el modelo (ver CatalogoDePermisos) y es
    la misma que estructura la pantalla de la matriz.

    Datos esperados (ver RolesController::index()):
    - $filas: list<array{rol, nombre, pantallas, acciones, usuarios, esRolActivo}>.
    - $totales: array{pantallas: int, acciones: int} — denominadores.

    Gateada por `seguridad.rol.ver`. Los botones se ocultan con `@puede`
    (presentación, no autorización — el servidor revalida en el controlador).

    Estilos en resources/css/pages/roles.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('seguridad.roles.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('seguridad.roles.titulo')"
    >
        <div class="ag-roles">
            <x-organisms.page-header
                :title="__('seguridad.roles.titulo')"
                :subtitle="__('seguridad.roles.subtitulo')"
            >
                @puede('seguridad.rol.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.roles.create') }}" variant="primary" icon="add">
                            {{ __('seguridad.roles.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-roles__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-roles__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($filas->isEmpty())
                <x-molecules.alert-strip variant="info" icon="shield_person" class="ag-roles__aviso">
                    {{ __('seguridad.roles.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-roles__tabla" role="table">
                    <div class="ag-roles__head" role="row">
                        <span role="columnheader">{{ __('seguridad.roles.col_rol') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_pantallas') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_acciones') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_usuarios') }}</span>
                        <span role="columnheader">{{ __('seguridad.roles.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($filas as $fila)
                        <div class="ag-roles__fila" role="row">
                            <span role="cell" class="ag-roles__identidad">
                                <span class="ag-roles__nombre">
                                    {{ $fila['nombre'] }}
                                    @if ($fila['esRolActivo'])
                                        <x-atoms.badge variant="success">{{ __('seguridad.roles.badge_rol_activo') }}</x-atoms.badge>
                                    @endif
                                </span>
                                <span class="ag-roles__slug">{{ $fila['rol']->name }}</span>
                                <span class="ag-roles__descripcion">{{ $fila['rol']->description }}</span>
                            </span>

                            <span role="cell" class="ag-roles__metrica">
                                <span class="ag-roles__cifra">{{ $fila['pantallas'] }}</span>
                                <span class="ag-roles__denominador">{{ __('seguridad.roles.de_total', ['total' => $totales['pantallas']]) }}</span>
                            </span>

                            <span role="cell" class="ag-roles__metrica">
                                <span class="ag-roles__cifra">{{ $fila['acciones'] }}</span>
                                <span class="ag-roles__denominador">{{ __('seguridad.roles.de_total', ['total' => $totales['acciones']]) }}</span>
                            </span>

                            <span role="cell" class="ag-roles__metrica">
                                @if ($fila['usuarios'] === 0)
                                    <span class="ag-roles__denominador">{{ __('seguridad.roles.sin_usuarios') }}</span>
                                @else
                                    <span class="ag-roles__cifra">{{ $fila['usuarios'] }}</span>
                                @endif
                            </span>

                            <span role="cell">
                                <x-atoms.badge :variant="$fila['rol']->state ? 'success' : 'neutral'">
                                    {{ __($fila['rol']->state ? 'seguridad.roles.estado_activo' : 'seguridad.roles.estado_inactivo') }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-roles__acciones">
                                @puede('seguridad.rol.editar')
                                    <x-atoms.button href="{{ route('panel.roles.edit', $fila['rol']) }}" variant="outline" size="sm" icon="edit">
                                        {{ __('seguridad.roles.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('seguridad.rol.asignar_permiso')
                                    <x-atoms.button href="{{ route('panel.roles.permisos.edit', $fila['rol']) }}" variant="primary" size="sm" icon="tune">
                                        {{ __('seguridad.roles.permisos') }}
                                    </x-atoms.button>
                                @endpuede

                                {{-- La baja se ofrece solo cuando puede prosperar: con cuentas
                                     detrás o siendo el rol propio, EliminarRol la rechaza. Mejor
                                     no ofrecerla que ofrecer un botón que siempre falla. --}}
                                @puede('seguridad.rol.eliminar')
                                    @if ($fila['usuarios'] === 0 && ! $fila['esRolActivo'])
                                        <form
                                            method="POST"
                                            action="{{ route('panel.roles.destroy', $fila['rol']) }}"
                                            onsubmit="return confirm('{{ __('seguridad.roles.eliminar_confirmar', ['rol' => $fila['nombre']]) }}')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-atoms.button type="submit" variant="outline" size="sm" icon="delete">
                                                {{ __('seguridad.roles.eliminar') }}
                                            </x-atoms.button>
                                        </form>
                                    @endif
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            <x-molecules.alert-strip variant="info" icon="info" class="ag-roles__aviso">
                {{ __('seguridad.roles.catalogo_fijo', ['total' => $totales['pantallas'] + $totales['acciones']]) }}
            </x-molecules.alert-strip>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
