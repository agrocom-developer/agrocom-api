{{--
    Page: versiones-apk/index (GET /panel/versiones-apk, panel.versiones-apk.index)
    Pantalla desde la que el dueño registra y autoriza versiones del APK de
    agrocom-field (HU-20): "ningún RC se actualiza sin su visto bueno". El
    binario vive en el release de agrocom-field (GitHub Releases); acá solo
    se registra su URL — por eso el alta es de tres campos de texto y no un
    `file-field`. Arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md,
    con la tarjeta de registro encima de la tabla.

    Datos esperados (ver VersionesApkController::index()): la cáscara de
    CascaraPanel, más:
    - $versiones (LengthAwarePaginator<VersionApk>): todas, más nueva primero.
    - $tonoPorEstado (array<string, string>): estado → tono, el mismo mapa para
      el badge, el botón de la fila y el modal
      (`VersionesApkController::TONO_POR_ESTADO`).
    - $autorizables (array<int, bool>): id de versión → si la tabla de
      transiciones deja pasarla a `autorizada` (hoy, solo una pendiente).

    Una versión no tiene ficha de edición, así que no lleva pasos: solo el
    badge de su estado y, en la fila, la acción que lleva al estado siguiente.

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
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('distribucion.versiones.titulo')"
    >
        <div class="ag-versiones-apk">
            <x-organisms.page-header
                :title="__('distribucion.versiones.titulo')"
                :subtitle="__('distribucion.versiones.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @puede('distribucion.version.autorizar')
                <form
                    method="POST"
                    action="{{ route('panel.versiones-apk.subir') }}"
                    class="ag-versiones-apk__form"
                    novalidate
                >
                    @csrf

                    <x-molecules.form-section
                        :title="__('distribucion.versiones.subir')"
                        :count="__('distribucion.versiones.campos_contador', ['cantidad' => 3])"
                    >
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
                            class="ag-form-section__field--full"
                            type="url"
                            name="url_apk"
                            :label="__('distribucion.versiones.campo_url_apk')"
                            placeholder="https://github.com/agrocom-developer/agrocom-field/releases/download/..."
                            :value="old('url_apk')"
                            :error="$errors->first('url_apk')"
                            :required="true"
                        />

                        <div class="ag-form-section__field--full ag-versiones-apk__enviar">
                            <x-atoms.button type="submit" variant="primary" icon="upload">
                                {{ __('distribucion.versiones.subir') }}
                            </x-atoms.button>
                        </div>
                    </x-molecules.form-section>
                </form>
            @endpuede

            @if ($versiones->isEmpty())
                {{-- Sin botón adentro: el vacío de un listado solo explica. El alta
                     está en la tarjeta de arriba. --}}
                <x-molecules.empty-state
                    icon="system_update"
                    :title="__('distribucion.versiones.vacio_titulo')"
                    :detail="__('distribucion.versiones.vacio_detalle')"
                />
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('distribucion.versiones.col_version') }}</span>
                        <span role="columnheader">{{ __('distribucion.versiones.col_version_code') }}</span>
                        <span role="columnheader">{{ __('distribucion.versiones.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($versiones as $versionApk)
                        @php
                            $estadoValor = $versionApk->estado->value;
                            $formIdAutorizar = "version-autorizar-{$versionApk->id}";
                            $modalIdAutorizar = "version-autorizar-modal-{$versionApk->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($versiones->currentPage() - 1) * $versiones->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-index-table__mono">{{ $versionApk->version }}</span>
                            <span role="cell" class="ag-index-table__mono">{{ $versionApk->version_code }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">
                                    {{ __('distribucion.versiones.estado_'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @puede('distribucion.version.autorizar')
                                    @if ($autorizables[$versionApk->id] ?? false)
                                        {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                             repite su slot dos veces (visible/menú, ver su docblock), así
                                             que un <form> o un modal con id ahí adentro se duplicaría — y
                                             el que cae dentro del menú ⋮ queda oculto con él y nunca abre.
                                             El disparador sí va adentro (es un botón sin id propio). --}}
                                        <form id="{{ $formIdAutorizar }}" method="POST" action="{{ route('panel.versiones-apk.autorizar', $versionApk) }}">
                                            @csrf
                                        </form>

                                        <x-molecules.confirm-modal
                                            :id="$modalIdAutorizar"
                                            :form-id="$formIdAutorizar"
                                            :title="__('distribucion.versiones.confirmar_autorizar_titulo')"
                                            :message="__('distribucion.versiones.confirmar_autorizar', ['version' => $versionApk->version])"
                                            :confirm-label="__('distribucion.versiones.autorizar')"
                                            :tone="$tonoPorEstado['autorizada']"
                                            modal-icon="verified"
                                        >
                                            @include('distribucion::pages.versiones-apk._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'autorizada'])
                                        </x-molecules.confirm-modal>

                                        <x-organisms.row-actions>
                                            <x-atoms.button
                                                type="button"
                                                data-bs-toggle="modal"
                                                :data-bs-target="'#'.$modalIdAutorizar"
                                                :variant="$tonoPorEstado['autorizada'].'-outline'"
                                                size="sm"
                                                icon="verified"
                                            >
                                                {{ __('distribucion.versiones.autorizar') }}
                                            </x-atoms.button>
                                        </x-organisms.row-actions>
                                    @endif
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$versiones" :aria-label="__('distribucion.versiones.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
