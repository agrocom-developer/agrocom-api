{{--
    Page: configuracion/index (GET /panel/configuracion, panel.configuracion.index)
    Tarea 78 (HU-55): llaves y tokens con que EL SISTEMA se parametriza (mapas,
    correo, integraciones) — separada a propósito de `/panel/organizacion`
    (los datos de LA EMPRESA). Exclusiva del rol `dueno`.

    EL CORAZÓN DE LA TAREA: ningún campo `esSecreto` trae su valor real acá.
    El `<input>` de un secreto SIEMPRE llega vacío (`value=""` fijo, nunca
    `$fila['...']`) — lo único que puede mostrarse es el badge de estado
    ("Configurada"/"Sin configurar") y, como mucho, los últimos 4 caracteres
    (`ListarConfiguracionPorGrupo`). Si algún día alguien agrega
    `value="{{ $fila['valorSecreto'] }}"` acá, está filtrando una llave —
    no existe tal clave en el array que llega a esta vista.

    Mismo mecanismo de pestañas que `/panel/organizacion` (Bootstrap
    `data-bs-toggle="tab"`, sin recarga): los TRES sectores llegan renderizados
    siempre, alternados por clase — por eso el controlador arma
    `filasPorGrupo` para los tres, no solo el activo.
--}}
<x-templates.panel-shell :title="__('configuracion.titulo')" :tema="$tema">
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
        :vista-actual="__('configuracion.titulo')"
    >
        <div class="ag-configuracion">
            <x-organisms.page-header
                :title="__('configuracion.titulo')"
                :subtitle="__('configuracion.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.tabs
                :items="collect($grupos)->map(fn (array $g) => ['id' => 'ag-tab-'.$g['clave'], 'label' => $g['label'], 'active' => $g['active']])->all()"
                :aria-label="__('configuracion.tabs_aria')"
            />

            <div class="tab-content ag-configuracion__panes">
                @foreach ($grupos as $grupo)
                    @php $filas = $filasPorGrupo[$grupo['clave']]; @endphp

                    <div class="tab-pane fade {{ $grupo['active'] ? 'show active' : '' }}" id="ag-tab-{{ $grupo['clave'] }}" role="tabpanel" tabindex="0">
                        @if (count($filas) === 0)
                            <p class="ag-configuracion__vacio">{{ __('configuracion.vacio_integraciones') }}</p>
                        @else
                            <form
                                class="ag-configuracion__form"
                                method="POST"
                                action="{{ route('panel.configuracion.actualizar', ['grupo' => $grupo['clave']]) }}"
                            >
                                @csrf
                                <x-molecules.form-section
                                    :title="$grupo['label']"
                                    :count="__('seguridad.organizacion.campos_contador', ['cantidad' => count($filas)])"
                                >
                                    @foreach ($filas as $fila)
                                        @if ($fila['esSecreto'])
                                            <div class="ag-form-section__field--full ag-configuracion__campo-secreto">
                                                <x-atoms.input
                                                    type="password"
                                                    name="valores[{{ $fila['clave'] }}]"
                                                    :label="$fila['descripcion']"
                                                    value=""
                                                    :help="__('configuracion.ayuda_secreto')"
                                                    :disabled="! $puedeEditar"
                                                    :data-ag-config-llave-google="$fila['clave'] === 'mapas.google_maps_api_key'"
                                                />

                                                <div class="ag-configuracion__estado">
                                                    @if ($fila['configurada'])
                                                        <x-atoms.badge variant="success" icon="check_circle">
                                                            {{ __('configuracion.estado_configurada') }}
                                                        </x-atoms.badge>
                                                        <span class="ag-configuracion__ultimos4">
                                                            {{ __('configuracion.estado_termina_en', ['ultimos4' => $fila['ultimos4']]) }}
                                                        </span>

                                                        @if ($puedeEditar)
                                                            <label class="ag-configuracion__borrar">
                                                                <input type="checkbox" name="borrar[{{ $fila['clave'] }}]" value="1">
                                                                {{ __('configuracion.accion_borrar') }}
                                                            </label>
                                                        @endif
                                                    @else
                                                        <x-atoms.badge variant="neutral">
                                                            {{ __('configuracion.estado_sin_configurar') }}
                                                        </x-atoms.badge>
                                                    @endif
                                                </div>
                                            </div>
                                        @elseif ($fila['tipo'] === 'switch')
                                            <div class="ag-form-section__field--full">
                                                <x-atoms.switch
                                                    name="valores[{{ $fila['clave'] }}]"
                                                    value="{{ $fila['valorActivado'] }}"
                                                    :label="$fila['descripcion']"
                                                    :help="__('configuracion.ayuda_forzar_leaflet')"
                                                    :checked="old('valores.'.$fila['clave']) !== null ? old('valores.'.$fila['clave']) === $fila['valorActivado'] : $fila['activado']"
                                                    :disabled="! $puedeEditar"
                                                    :data-ag-config-switch-forzar-leaflet="$fila['clave'] === 'mapas.proveedor_preferido'"
                                                />
                                            </div>
                                        @else
                                            <x-atoms.input
                                                type="text"
                                                name="valores[{{ $fila['clave'] }}]"
                                                :label="$fila['descripcion']"
                                                :value="old('valores.'.$fila['clave'], $fila['valorVisible'])"
                                                :disabled="! $puedeEditar"
                                            />
                                        @endif
                                    @endforeach
                                </x-molecules.form-section>

                                @if ($puedeEditar)
                                    <x-organisms.form-actions-bar>
                                        <x-slot:actions>
                                            <x-atoms.button type="submit" variant="primary">
                                                {{ __('ui.action.save') }}
                                            </x-atoms.button>
                                        </x-slot:actions>
                                    </x-organisms.form-actions-bar>
                                @endif
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
