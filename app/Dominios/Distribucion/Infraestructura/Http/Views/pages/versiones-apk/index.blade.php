{{--
    Page: versiones-apk/index (GET /panel/versiones-apk, panel.versiones-apk.index)
    Pantalla desde la que el dueño registra y autoriza versiones del APK de
    agrocom-field (HU-20): "ningún RC se actualiza sin su visto bueno". El
    binario vive en el release de agrocom-field (GitHub Releases); acá solo
    se registra su URL.

    Datos esperados (ver VersionesApkController::index()): la cáscara de
    CascaraPanel, más:
    - $versiones (Collection<VersionApk>): todas, más nueva primero.

    El formulario de registro y el botón "Autorizar" se gatean con `@puede`
    (evalúa contra el ROL ACTIVO, invariante 10 de CLAUDE.md); el servidor
    igual vuelve a verificar en VersionesApkController::store()/autorizar().

    Estilos en resources/css/pages/versiones-apk.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('distribucion.versiones.titulo')" :tema="$tema">
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
    >
        <x-organisms.page-header
            :title="__('distribucion.versiones.titulo')"
            :subtitle="__('distribucion.versiones.subtitulo')"
        />

        @if (session('estado'))
            <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-versiones-apk__aviso">
                {{ session('estado') }}
            </x-molecules.alert-strip>
        @endif

        @puede('distribucion.version.autorizar')
            <form
                method="POST"
                action="{{ route('panel.versiones-apk.subir') }}"
                class="ag-versiones-apk__form"
            >
                @csrf

                <x-molecules.form-section :title="__('distribucion.versiones.subir')">
                    <x-atoms.input
                        type="text"
                        name="version"
                        :label="__('distribucion.versiones.campo_version')"
                        placeholder="1.4.2"
                        :value="old('version')"
                        :error="$errors->first('version')"
                        :required="true"
                    />

                    <x-atoms.input
                        type="number"
                        name="version_code"
                        :label="__('distribucion.versiones.campo_version_code')"
                        :value="old('version_code')"
                        :error="$errors->first('version_code')"
                        :required="true"
                        min="1"
                    />

                    <x-atoms.input
                        type="url"
                        name="url_apk"
                        :label="__('distribucion.versiones.campo_url_apk')"
                        placeholder="https://github.com/agrocom-developer/agrocom-field/releases/download/..."
                        :value="old('url_apk')"
                        :error="$errors->first('url_apk')"
                        :required="true"
                    />

                    <x-atoms.button type="submit" variant="accent" icon="upload">
                        {{ __('distribucion.versiones.subir') }}
                    </x-atoms.button>
                </x-molecules.form-section>
            </form>
        @endpuede

        @if ($versiones->isEmpty())
            <x-molecules.alert-strip variant="info" icon="system_update" class="ag-versiones-apk__aviso">
                {{ __('distribucion.versiones.vacio') }}
            </x-molecules.alert-strip>
        @else
            <div class="ag-versiones-apk__tabla" role="table">
                <div class="ag-versiones-apk__head" role="row">
                    <span role="columnheader">{{ __('distribucion.versiones.col_version') }}</span>
                    <span role="columnheader">{{ __('distribucion.versiones.col_version_code') }}</span>
                    <span role="columnheader">{{ __('distribucion.versiones.col_estado') }}</span>
                    <span role="columnheader" aria-hidden="true"></span>
                </div>

                @foreach ($versiones as $versionApk)
                    <div class="ag-versiones-apk__fila" role="row">
                        <span role="cell">{{ $versionApk->version }}</span>
                        <span role="cell">{{ $versionApk->version_code }}</span>

                        <span role="cell">
                            @php
                                $variantePorEstado = [
                                    'pendiente' => 'warning',
                                    'autorizada' => 'success',
                                    'rechazada' => 'danger',
                                ];
                            @endphp
                            <x-atoms.badge variant="{{ $variantePorEstado[$versionApk->estado->value] }}">
                                {{ __("distribucion.versiones.estado_{$versionApk->estado->value}") }}
                            </x-atoms.badge>
                        </span>

                        <span role="cell" class="ag-versiones-apk__accion">
                            @puede('distribucion.version.autorizar')
                                @if ($versionApk->estado->value !== 'autorizada')
                                    <form method="POST" action="{{ route('panel.versiones-apk.autorizar', $versionApk) }}">
                                        @csrf
                                        <x-atoms.button type="submit" variant="primary" size="sm" icon="verified">
                                            {{ __('distribucion.versiones.autorizar') }}
                                        </x-atoms.button>
                                    </form>
                                @endif
                            @endpuede
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
