{{--
    Page: roles/permisos (GET/PUT /panel/roles/{rol}/permisos, panel.roles.permisos.*)
    La matriz rol↔permiso. Dos columnas: a la izquierda una vista previa del
    sidebar tal como lo vería alguien operando con este rol; a la derecha el
    árbol del menú, con un switch por PANTALLA y sus ACCIONES anidadas como
    chips.

    Por qué así (canvas de diseño aprobado, 7/9/2026): los 91 permisos no son
    91 cosas equivalentes. 31 encienden un ítem del sidebar y 58 son acciones
    dentro de esas pantallas — ver CatalogoDePermisos. Presentados como una
    lista plana son ilegibles; partidos en pantallas y acciones, y agrupados
    por el módulo del MENÚ, la pantalla dice lo que hace: "este rol ve esto".

    La vista previa no es decoración: es la única forma de contestar "¿qué le
    cambio a esta persona?" sin iniciar sesión con su rol. Se recalcula en el
    navegador (resources/js/pages/roles-permisos.js) a medida que se togglea,
    sin ida y vuelta al servidor.

    Datos esperados (ver RolesController::editarPermisos()):
    - $rol (SecRole), $nombreRol (string), $esRolActivo (bool).
    - $modulos: árbol de módulos de menú → pantallas → acciones.
    - $sueltos: acciones que no cuelgan de ninguna pantalla del panel.
    - $otorgados (list<int>): lo que el rol tiene hoy.
    - $bloqueados (list<int>): permisos que SOLO este rol sostiene.
    - $concedibles (list<int>): los que el actor tiene en su rol activo.

    Gateada por `seguridad.rol.asignar_permiso`, verificado server-side. Las
    cuatro guardas de negocio viven en AsignarPermisosRol: lo de acá es no
    ofrecer lo que el servidor va a rechazar.

    Estilos en resources/css/pages/roles.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $tiene = fn (int $id): bool => in_array($id, $otorgados, true);
    $bloqueado = fn (int $id): bool => in_array($id, $bloqueados, true);
    $concedible = fn (int $id): bool => in_array($id, $concedibles, true);
@endphp

<x-templates.panel-shell :title="__('seguridad.roles.permisos_titulo', ['rol' => $nombreRol])" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('seguridad.roles.titulo')"
    >
        <form
            method="POST"
            action="{{ route('panel.roles.permisos.update', $rol) }}"
            class="ag-permisos"
            data-ag-permisos
        >
            @csrf
            @method('PUT')

            <x-organisms.page-header
                :title="__('seguridad.roles.permisos_titulo', ['rol' => $nombreRol])"
                :subtitle="__('seguridad.roles.permisos_subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button href="{{ route('panel.roles.index') }}" variant="outline" icon="arrow_back">
                        {{ __('seguridad.roles.permisos_volver') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-permisos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-permisos__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($esRolActivo)
                <x-molecules.alert-strip variant="warning" icon="warning" class="ag-permisos__aviso">
                    {{ __('seguridad.roles.permisos_aviso_rol_propio') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-permisos__layout">

                {{-- Vista previa del sidebar del rol --}}
                <aside class="ag-permisos__preview" aria-live="polite">
                    <div class="ag-permisos__preview-head">
                        <x-atoms.icon name="visibility" size="sm" />
                        <span>{{ __('seguridad.roles.permisos_preview_titulo') }}</span>
                    </div>

                    <div class="ag-permisos__preview-cuerpo">
                        @foreach ($modulos as $modulo)
                            @php $visibles = collect($modulo['pantallas'])->filter(fn ($p) => $tiene($p['id']))->count(); @endphp
                            <div
                                class="ag-permisos__preview-modulo"
                                data-ag-preview-modulo
                                @class(['is-oculto' => $visibles === 0])
                            >
                                <div class="ag-permisos__preview-modulo-head">
                                    <x-atoms.icon :name="$modulo['icono']" size="sm" />
                                    <span class="ag-permisos__preview-modulo-nombre">{{ __($modulo['clave']) }}</span>
                                    <span
                                        class="ag-permisos__preview-modulo-conteo"
                                        data-ag-preview-conteo
                                        data-ag-texto-oculto="{{ __('seguridad.roles.permisos_preview_oculto') }}"
                                    >
                                        {{ $visibles === 0 ? __('seguridad.roles.permisos_preview_oculto') : $visibles.'/'.count($modulo['pantallas']) }}
                                    </span>
                                </div>

                                @foreach ($modulo['pantallas'] as $pantalla)
                                    <div
                                        class="ag-permisos__preview-item"
                                        data-ag-preview-item="{{ $pantalla['id'] }}"
                                        @class(['is-visible' => $tiene($pantalla['id'])])
                                    >
                                        <span class="ag-permisos__preview-punto" aria-hidden="true"></span>
                                        <span>{{ __($pantalla['clave']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <p class="ag-permisos__preview-pie">{{ __('seguridad.roles.permisos_preview_pie') }}</p>
                </aside>

                {{-- Editor --}}
                <div class="ag-permisos__editor">
                    @foreach ($modulos as $modulo)
                        @php $encendidas = collect($modulo['pantallas'])->filter(fn ($p) => $tiene($p['id']))->count(); @endphp
                        <section class="ag-permisos__modulo" data-ag-modulo>
                            <header class="ag-permisos__modulo-head">
                                <x-atoms.icon :name="$modulo['icono']" size="sm" />
                                <h2 class="ag-permisos__modulo-nombre">{{ __($modulo['clave']) }}</h2>
                                <span
                                    class="ag-permisos__modulo-resumen"
                                    data-ag-modulo-resumen
                                    data-ag-plantilla="{{ __('seguridad.roles.permisos_modulo_resumen') }}"
                                >
                                    {{ __('seguridad.roles.permisos_modulo_resumen', ['encendidas' => $encendidas, 'total' => count($modulo['pantallas'])]) }}
                                </span>
                            </header>

                            @foreach ($modulo['pantallas'] as $pantalla)
                                @php
                                    $activa = $tiene($pantalla['id']);
                                    $trabada = $bloqueado($pantalla['id']) || ! $concedible($pantalla['id']);
                                    $activas = collect($pantalla['acciones'])->filter(fn ($a) => $tiene($a['id']))->count();
                                @endphp
                                <div
                                    class="ag-permisos__pantalla"
                                    data-ag-pantalla="{{ $pantalla['id'] }}"
                                    @class(['is-activa' => $activa])
                                >
                                    <div class="ag-permisos__pantalla-fila">
                                        <label class="ag-permisos__switch">
                                            <input
                                                type="checkbox"
                                                name="permisos[]"
                                                value="{{ $pantalla['id'] }}"
                                                class="ag-permisos__switch-input"
                                                data-ag-switch-pantalla
                                                @checked($activa)
                                                @disabled($trabada)
                                            >
                                            <span class="ag-permisos__switch-pista" aria-hidden="true">
                                                <span class="ag-permisos__switch-perilla"></span>
                                            </span>
                                            <span class="ag-permisos__pantalla-texto">
                                                <span class="ag-permisos__pantalla-nombre">
                                                    {{ __($pantalla['clave']) }}
                                                    @if ($bloqueado($pantalla['id']))
                                                        <x-atoms.icon name="lock" size="sm" :label="__('seguridad.roles.permisos_bloqueado')" />
                                                    @endif
                                                </span>
                                                <span class="ag-permisos__codigo">{{ $pantalla['codigo'] }}</span>
                                            </span>
                                        </label>

                                        <span
                                            class="ag-permisos__pantalla-conteo"
                                            data-ag-pantalla-conteo
                                            data-ag-plantilla="{{ __('seguridad.roles.permisos_conteo_acciones') }}"
                                        >
                                            {{ $pantalla['acciones'] === []
                                                ? __('seguridad.roles.permisos_sin_acciones')
                                                : __('seguridad.roles.permisos_conteo_acciones', ['activas' => $activas, 'total' => count($pantalla['acciones'])]) }}
                                        </span>
                                    </div>

                                    {{-- Un checkbox deshabilitado no se envía, y la ausencia ES la
                                         revocación: sin este hidden, "bloqueado" llegaría al servidor
                                         como "quitame este permiso" y el guardado entero se rechazaría. --}}
                                    @if ($trabada && $activa)
                                        <input type="hidden" name="permisos[]" value="{{ $pantalla['id'] }}">
                                    @endif

                                    @if ($pantalla['acciones'] !== [])
                                        <div class="ag-permisos__acciones">
                                            @foreach ($pantalla['acciones'] as $accion)
                                                @php
                                                    $accionActiva = $tiene($accion['id']);
                                                    $accionTrabada = $bloqueado($accion['id']) || ! $concedible($accion['id']);
                                                @endphp
                                                {{-- Un chip trabado se ve gris y nada más: el `title` es lo único
                                                     que puede decir por qué, así que ahí va el motivo y no la
                                                     descripción del permiso (que ya se lee en el código). --}}
                                                <label
                                                    class="ag-permisos__chip"
                                                    title="{{ $accionTrabada
                                                        ? __($bloqueado($accion['id']) ? 'seguridad.roles.permisos_bloqueado' : 'seguridad.roles.permisos_no_concedible')
                                                        : $accion['descripcion'] }}"
                                                    @class(['is-trabada' => $accionTrabada])
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="permisos[]"
                                                        value="{{ $accion['id'] }}"
                                                        class="ag-permisos__chip-input"
                                                        data-ag-chip-accion
                                                        @checked($accionActiva)
                                                        @disabled($accionTrabada)
                                                    >
                                                    <span class="ag-permisos__chip-punto" aria-hidden="true"></span>
                                                    <span class="ag-permisos__chip-texto">{{ $accion['accion'] }}</span>
                                                </label>

                                                @if ($accionTrabada && $accionActiva)
                                                    <input type="hidden" name="permisos[]" value="{{ $accion['id'] }}">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Acción encendida sobre una pantalla apagada: combinación
                                         válida en la base que no hace nada. Se avisa, no se bloquea:
                                         puede ser deliberado dejarla lista. --}}
                                    <p
                                        class="ag-permisos__huerfana"
                                        data-ag-huerfana
                                        data-ag-plantilla="{{ __('seguridad.roles.permisos_huerfana') }}"
                                        data-ag-codigo="{{ $pantalla['codigo'] }}"
                                        hidden
                                    >
                                        <x-atoms.icon name="warning" size="sm" />
                                        <span data-ag-huerfana-texto></span>
                                    </p>

                                    @if ($bloqueado($pantalla['id']))
                                        <p class="ag-permisos__motivo">{{ __('seguridad.roles.permisos_bloqueado') }}</p>
                                    @elseif (! $concedible($pantalla['id']))
                                        <p class="ag-permisos__motivo">{{ __('seguridad.roles.permisos_no_concedible') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </section>
                    @endforeach

                    @if ($sueltos !== [])
                        <section class="ag-permisos__modulo">
                            <header class="ag-permisos__modulo-head">
                                <x-atoms.icon name="phone_android" size="sm" />
                                <h2 class="ag-permisos__modulo-nombre">{{ __('seguridad.roles.permisos_sueltos_titulo') }}</h2>
                            </header>

                            <div class="ag-permisos__pantalla is-activa">
                                <p class="ag-permisos__motivo">{{ __('seguridad.roles.permisos_sueltos_ayuda') }}</p>

                                <div class="ag-permisos__acciones">
                                    @foreach ($sueltos as $suelto)
                                        @php
                                            $sueltoActivo = $tiene($suelto['id']);
                                            $sueltoTrabado = $bloqueado($suelto['id']) || ! $concedible($suelto['id']);
                                        @endphp
                                        <label
                                            class="ag-permisos__chip"
                                            title="{{ $sueltoTrabado
                                                ? __($bloqueado($suelto['id']) ? 'seguridad.roles.permisos_bloqueado' : 'seguridad.roles.permisos_no_concedible')
                                                : $suelto['descripcion'] }}"
                                            @class(['is-trabada' => $sueltoTrabado])
                                        >
                                            <input
                                                type="checkbox"
                                                name="permisos[]"
                                                value="{{ $suelto['id'] }}"
                                                class="ag-permisos__chip-input"
                                                @checked($sueltoActivo)
                                                @disabled($sueltoTrabado)
                                            >
                                            <span class="ag-permisos__chip-punto" aria-hidden="true"></span>
                                            <span class="ag-permisos__chip-texto">{{ $suelto['codigo'] }}</span>
                                        </label>

                                        @if ($sueltoTrabado && $sueltoActivo)
                                            <input type="hidden" name="permisos[]" value="{{ $suelto['id'] }}">
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif
                </div>
            </div>

            <x-organisms.form-actions-bar
                :status="__('seguridad.roles.permisos_sin_cambios')"
                data-ag-permisos-barra
                data-ag-sin-cambios="{{ __('seguridad.roles.permisos_sin_cambios') }}"
                data-ag-con-cambios="{{ __('seguridad.roles.permisos_con_cambios') }}"
            >
                <x-slot:actions>
                    <x-atoms.button type="reset" variant="outline">
                        {{ __('seguridad.roles.permisos_descartar') }}
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('seguridad.roles.permisos_guardar') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
