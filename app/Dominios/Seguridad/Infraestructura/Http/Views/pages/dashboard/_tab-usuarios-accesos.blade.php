{{--
    Parcial: pestaña "Usuarios y accesos" del administrador de plataforma
    (tarea 139) — cuántas cuentas hay en cada rol, qué dispositivos de campo
    tienen sesión abierta y qué versiones del APK esperan autorización.

    Cada sección es un permiso propio (el de su pantalla completa), así que
    puede faltar cualquiera; el `@isset` no es un permiso disfrazado:
    `ArmarDashboard` ya decidió qué claves existen y acá solo se pinta lo que
    llegó. Solo lectura: registrar o autorizar una versión, revocar un
    dispositivo o dar de baja una cuenta viven en sus pantallas.

    Bajo 768 px la tabla de dispositivos no scrollea: cada fila se apila y
    `data-label` rotula cada dato (ver dashboard.css).

    Espera, cada una opcional:
    - $secciones['usuarios_por_rol'] (array{total, portal, roles: list<{rol:
      SecRole, usuarios}>}), ver ArmarDashboard::usuariosPorRol().
    - $secciones['versiones_apk'] (array{vigente: ?VersionApkPanel,
      pendientes: list<VersionApkPanel>}), ver ArmarDashboard::versionesApk().
    - $secciones['dispositivos_con_sesion'] (array{total, dispositivos:
      list<SecTokenDispositivo>}), ver ArmarDashboard::dispositivosConSesion().
--}}
@use('App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol')

<div class="ag-dash__stack">
    @if (isset($secciones['usuarios_por_rol']) || isset($secciones['versiones_apk']))
        <div class="ag-dash__par">
            @isset($secciones['usuarios_por_rol'])
                @php($usuarios = $secciones['usuarios_por_rol'])
                <section>
                    <x-molecules.section-head :title="__('seguridad.dashboard.seccion_usuarios_por_rol')">
                        <x-slot:actions>
                            <a class="ag-dash__link" href="{{ route('panel.usuarios.index') }}">{{ __('seguridad.dashboard.usuarios_por_rol_ver') }}</a>
                        </x-slot:actions>
                    </x-molecules.section-head>
                    <div class="ag-card">
                        <div class="ag-table ag-table--roles" role="table">
                            <div class="ag-table__head" role="row">
                                <span role="columnheader">{{ __('seguridad.dashboard.usuarios_por_rol_col_rol') }}</span>
                                <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.usuarios_por_rol_col_usuarios') }}</span>
                            </div>
                            @foreach ($usuarios['roles'] as $fila)
                                <div class="ag-table__row" role="row">
                                    <span role="cell">{{ PresentadorRol::nombreLegible($fila['rol']) }}</span>
                                    <span role="cell" class="ag-table__ha">{{ $fila['usuarios'] }}</span>
                                </div>
                            @endforeach
                            <div class="ag-table__row" role="row">
                                <span role="cell" class="ag-table__strong">{{ __('seguridad.dashboard.usuarios_por_rol_total') }}</span>
                                <span role="cell" class="ag-table__ha ag-table__strong">{{ $usuarios['total'] }}</span>
                            </div>
                            <div class="ag-table__row" role="row">
                                <span role="cell">{{ __('seguridad.dashboard.usuarios_por_rol_portal') }}</span>
                                <span role="cell" class="ag-table__ha">{{ $usuarios['portal'] }}</span>
                            </div>
                        </div>
                        <p class="ag-dash__nota">{{ __('seguridad.dashboard.usuarios_por_rol_nota') }}</p>
                    </div>
                </section>
            @endisset

            @isset($secciones['versiones_apk'])
                @php($versiones = $secciones['versiones_apk'])
                <section>
                    <x-molecules.section-head :title="__('seguridad.dashboard.seccion_versiones_apk')">
                        <x-slot:actions>
                            <a class="ag-dash__link" href="{{ route('panel.versiones-apk.index') }}">{{ __('seguridad.dashboard.versiones_apk_ver') }}</a>
                        </x-slot:actions>
                    </x-molecules.section-head>
                    <div class="ag-card ag-card--padded">
                        <div>
                            <h3 class="ag-card__subtitle">{{ __('seguridad.dashboard.versiones_apk_vigente') }}</h3>
                            @if ($versiones['vigente'] !== null)
                                <p class="ag-dash__version">
                                    <span class="ag-table__strong">{{ $versiones['vigente']->version }}</span>
                                    <span class="ag-dash__mono-note">{{ __('seguridad.dashboard.versiones_apk_codigo', ['codigo' => $versiones['vigente']->versionCode]) }}</span>
                                </p>
                            @else
                                <p class="ag-dash__version ag-dash__version--vacia">{{ __('seguridad.dashboard.versiones_apk_sin_vigente') }}</p>
                            @endif
                        </div>
                        <div>
                            <h3 class="ag-card__subtitle">{{ __('seguridad.dashboard.versiones_apk_pendientes') }}</h3>
                            @forelse ($versiones['pendientes'] as $pendiente)
                                <p class="ag-dash__version">
                                    <span class="ag-table__strong">{{ $pendiente->version }}</span>
                                    <span class="ag-dash__mono-note">{{ __('seguridad.dashboard.versiones_apk_codigo', ['codigo' => $pendiente->versionCode]) }}</span>
                                </p>
                            @empty
                                <p class="ag-dash__version ag-dash__version--vacia">{{ __('seguridad.dashboard.versiones_apk_sin_pendientes') }}</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            @endisset
        </div>
    @endif

    @isset($secciones['dispositivos_con_sesion'])
        @php($dispositivos = $secciones['dispositivos_con_sesion'])
        <section>
            <x-molecules.section-head :title="__('seguridad.dashboard.seccion_dispositivos')">
                <x-slot:actions>
                    <a class="ag-dash__link" href="{{ route('panel.dispositivos.index') }}">
                        {{ trans_choice('seguridad.dashboard.dispositivos_ver', $dispositivos['total'], ['cantidad' => $dispositivos['total']]) }}
                    </a>
                </x-slot:actions>
            </x-molecules.section-head>
            <div class="ag-card">
                <div class="ag-table-scroll">
                    <div class="ag-table ag-table--dispositivos" role="table">
                        <div class="ag-table__head" role="row">
                            <span role="columnheader">{{ __('seguridad.dispositivos.col_usuario') }}</span>
                            <span role="columnheader">{{ __('seguridad.dispositivos.col_dispositivo') }}</span>
                            <span role="columnheader">{{ __('seguridad.dispositivos.col_rol') }}</span>
                            <span role="columnheader">{{ __('seguridad.dispositivos.col_ultimo_uso') }}</span>
                        </div>
                        @foreach ($dispositivos['dispositivos'] as $dispositivo)
                            <div class="ag-table__row" role="row">
                                <span class="ag-table__strong" role="cell">{{ $dispositivo->tokenable?->name ?? __('seguridad.dispositivos.usuario_desconocido') }}</span>
                                <span role="cell" data-label="{{ __('seguridad.dispositivos.col_dispositivo') }}">{{ $dispositivo->nombre_dispositivo ?? __('seguridad.dispositivos.equipo_sin_nombre') }}</span>
                                <span role="cell" data-label="{{ __('seguridad.dispositivos.col_rol') }}">{{ $dispositivo->rol !== null ? PresentadorRol::nombreLegible($dispositivo->rol) : '—' }}</span>
                                <span role="cell" class="ag-table__sub" data-label="{{ __('seguridad.dispositivos.col_ultimo_uso') }}">{{ $dispositivo->last_used_at?->diffForHumans() ?? __('seguridad.dispositivos.sin_uso') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endisset
</div>
