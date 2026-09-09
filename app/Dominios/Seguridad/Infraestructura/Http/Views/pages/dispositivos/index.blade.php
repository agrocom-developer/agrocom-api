{{--
    Page: dispositivos/index (GET /panel/dispositivos, panel.dispositivos.index)
    Pantalla de revocación de sesiones de la app de campo (HU-03, CA
    "revocable desde el panel"): quién tiene sesión abierta, desde qué
    equipo, con qué rol, y el botón para dejarlo afuera.

    Datos esperados (ver DispositivosController::index()): la cáscara de
    CascaraPanel (menu/roles/…/tema/campaniaActiva/periodo/version), más:
    - $dispositivos (Collection<SecTokenDispositivo>): sesiones vivas.

    El botón de revocar se gatea con la directiva `@puede` del módulo, que
    evalúa contra el ROL ACTIVO (nunca la unión de roles — CLAUDE.md
    invariante 10). Ocultarlo es presentación: el DELETE igual se rechaza
    server-side en DispositivosController::destroy().

    Estilos en resources/css/pages/dispositivos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('seguridad.dispositivos.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
    >
        <x-organisms.page-header
            :title="__('seguridad.dispositivos.titulo')"
            :subtitle="__('seguridad.dispositivos.subtitulo')"
        />

        @if (session('estado'))
            <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-dispositivos__aviso">
                {{ session('estado') }}
            </x-molecules.alert-strip>
        @endif

        @if ($dispositivos->isEmpty())
            <x-molecules.alert-strip variant="info" icon="smartphone" class="ag-dispositivos__aviso">
                {{ __('seguridad.dispositivos.vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-dispositivos__tabla" role="table">
                <div class="ag-dispositivos__head" role="row">
                    <span role="columnheader">{{ __('seguridad.dispositivos.col_usuario') }}</span>
                    <span role="columnheader">{{ __('seguridad.dispositivos.col_dispositivo') }}</span>
                    <span role="columnheader">{{ __('seguridad.dispositivos.col_rol') }}</span>
                    <span role="columnheader">{{ __('seguridad.dispositivos.col_ultimo_uso') }}</span>
                    {{-- Columna de acción sin label visible: el botón de cada
                         fila ya lleva su propio nombre accesible. --}}
                    <span role="columnheader" aria-hidden="true"></span>
                </div>

                @foreach ($dispositivos as $dispositivo)
                    <div class="ag-dispositivos__fila" role="row">
                        <span role="cell">{{ $dispositivo->tokenable?->name ?? __('seguridad.dispositivos.usuario_desconocido') }}</span>

                        <span role="cell">
                            <span class="ag-dispositivos__equipo">
                                {{ $dispositivo->nombre_dispositivo ?? __('seguridad.dispositivos.equipo_sin_nombre') }}
                            </span>
                            <span class="ag-dispositivos__uuid">{{ $dispositivo->uuid_dispositivo }}</span>
                        </span>

                        <span role="cell">
                            <x-atoms.badge variant="neutral">{{ $dispositivo->rol?->name }}</x-atoms.badge>
                        </span>

                        <span role="cell">
                            {{ $dispositivo->last_used_at?->diffForHumans() ?? __('seguridad.dispositivos.sin_uso') }}
                        </span>

                        <span role="cell" class="ag-dispositivos__accion">
                            @puede('seguridad.dispositivo.revocar')
                                <form method="POST" action="{{ route('panel.dispositivos.revocar', $dispositivo) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-atoms.button
                                        type="submit"
                                        variant="danger-outline"
                                        size="sm"
                                        icon="link_off"
                                    >{{ __('seguridad.dispositivos.revocar') }}</x-atoms.button>
                                </form>
                            @endpuede
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
