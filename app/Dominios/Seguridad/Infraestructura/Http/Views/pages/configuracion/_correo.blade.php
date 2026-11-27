{{--
    Parcial de la pestaña "Correo" de configuracion/index (HU-55, recuperar el acceso).
    Variables: $filas (de ListarConfiguracionPorGrupo, grupo `correo`), $correoDeBase (bool:
    ya hay un servidor guardado en la base), $presetsCorreo, $puedeEditar.

    Contraseña de aplicación: el `<input>` llega SIEMPRE vacío y no se muestra ni el final
    del valor; solo el estado "Guardada" / "Sin configurar". Dejarlo vacío conserva lo guardado.

    Sin servidor en la base el formulario arranca con Gmail y su preset (no con el respaldo
    del `.env`, que en desarrollo es Mailpit): así quien configura parte de un valor útil.
--}}
@php
    $porClave = collect($filas)->keyBy('clave');
    $antiguos = (array) old('valores', []);
    $valor = function (string $clave, ?string $predeterminado = null) use ($porClave, $antiguos, $correoDeBase) {
        if (array_key_exists($clave, $antiguos)) {
            return $antiguos[$clave];
        }
        if (! $correoDeBase && $predeterminado !== null) {
            return $predeterminado;
        }

        return $correoDeBase ? ($porClave[$clave]['valorVisible'] ?? $predeterminado) : $predeterminado;
    };

    $proveedor = $valor('correo.proveedor', 'gmail');
    $preset = $presetsCorreo[$proveedor] ?? $presetsCorreo['gmail'];
    $opcionesProveedor = ['gmail' => __('configuracion.correo.proveedor_gmail'), 'outlook' => __('configuracion.correo.proveedor_outlook'), 'otro' => __('configuracion.correo.proveedor_otro')];
    $opcionesCifrado = ['tls' => __('configuracion.correo.cifrado_tls'), 'ssl' => __('configuracion.correo.cifrado_ssl'), 'ninguno' => __('configuracion.correo.cifrado_ninguno')];
    $password = $porClave['correo.password'] ?? ['configurada' => false];
@endphp

<form
    class="ag-configuracion__form"
    method="POST"
    action="{{ route('panel.configuracion.actualizar', ['grupo' => 'correo']) }}"
    data-ag-correo-presets='@json($presetsCorreo)'
>
    @csrf
    <x-molecules.form-section :title="__('configuracion.grupo_correo')" :count="__('configuracion.correo.contador')">
        @unless ($correoDeBase)
            <div class="ag-form-section__field--full">
                <x-molecules.alert-strip variant="info" icon="info">
                    {{ __('configuracion.correo.sin_servidor_guardado') }}
                </x-molecules.alert-strip>
            </div>
        @endunless

        <x-atoms.select
            name="valores[correo.proveedor]"
            :label="__('configuracion.claves')['correo.proveedor']"
            :options="$opcionesProveedor"
            :value="$proveedor"
            :error="$errors->first('valores.correo.proveedor')"
            :disabled="! $puedeEditar"
            :searchable="false"
            data-ag-correo-proveedor
        />

        <x-atoms.input
            name="valores[correo.host]"
            :label="__('configuracion.claves')['correo.host']"
            :value="$valor('correo.host', $preset['host'])"
            :error="$errors->first('valores.correo.host')"
            :disabled="! $puedeEditar"
            autocomplete="off"
            data-ag-correo-host
        />

        <x-atoms.input
            name="valores[correo.puerto]"
            type="number"
            :label="__('configuracion.claves')['correo.puerto']"
            :value="$valor('correo.puerto', $preset['puerto'])"
            :error="$errors->first('valores.correo.puerto')"
            :disabled="! $puedeEditar"
            data-ag-correo-puerto
        />

        <x-atoms.select
            name="valores[correo.cifrado]"
            :label="__('configuracion.claves')['correo.cifrado']"
            :options="$opcionesCifrado"
            :value="$valor('correo.cifrado', $preset['cifrado'])"
            :error="$errors->first('valores.correo.cifrado')"
            :help="__('configuracion.correo.ayuda_cifrado')"
            :disabled="! $puedeEditar"
            :searchable="false"
            data-ag-correo-cifrado
        />

        <x-atoms.input
            name="valores[correo.usuario]"
            :label="__('configuracion.claves')['correo.usuario']"
            :value="$valor('correo.usuario')"
            :error="$errors->first('valores.correo.usuario')"
            :help="__('configuracion.correo.ayuda_usuario')"
            :disabled="! $puedeEditar"
            autocomplete="off"
        />

        <div class="ag-form-section__field--full ag-configuracion__campo-secreto">
            <x-atoms.input
                type="password"
                name="valores[correo.password]"
                :label="__('configuracion.claves')['correo.password']"
                value=""
                :help="__('configuracion.correo.ayuda_password')"
                :disabled="! $puedeEditar"
                autocomplete="new-password"
            />

            <div class="ag-configuracion__estado">
                @if ($password['configurada'])
                    <x-atoms.badge variant="success" icon="check_circle">
                        {{ __('configuracion.correo.password_guardada') }}
                    </x-atoms.badge>

                    @if ($puedeEditar)
                        <label class="ag-configuracion__borrar">
                            <input type="checkbox" name="borrar[correo.password]" value="1">
                            {{ __('configuracion.accion_borrar') }}
                        </label>
                    @endif
                @else
                    <x-atoms.badge variant="neutral">
                        {{ __('configuracion.estado_sin_configurar') }}
                    </x-atoms.badge>
                @endif
            </div>

            <details class="ag-configuracion__guia">
                <summary>{{ __('configuracion.correo.guia_titulo') }}</summary>
                <ol>
                    <li>{{ __('configuracion.correo.guia_paso_1') }}</li>
                    <li>{{ __('configuracion.correo.guia_paso_2') }}</li>
                    <li>{{ __('configuracion.correo.guia_paso_3') }}</li>
                    <li>{{ __('configuracion.correo.guia_paso_4') }}</li>
                </ol>
            </details>
        </div>

        <x-atoms.input
            type="email"
            name="valores[correo.remitente]"
            :label="__('configuracion.claves')['correo.remitente']"
            :value="$valor('correo.remitente')"
            :error="$errors->first('valores.correo.remitente')"
            :help="__('configuracion.correo.ayuda_remitente')"
            :disabled="! $puedeEditar"
        />

        <x-atoms.input
            name="valores[correo.remitente_nombre]"
            :label="__('configuracion.claves')['correo.remitente_nombre']"
            :value="$valor('correo.remitente_nombre', 'DronAgro')"
            :error="$errors->first('valores.correo.remitente_nombre')"
            :disabled="! $puedeEditar"
        />
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

@if ($puedeEditar)
    <form class="ag-configuracion__prueba" method="POST" action="{{ route('panel.configuracion.correo.prueba') }}">
        @csrf
        <p class="ag-configuracion__ayuda">{{ __('configuracion.correo.prueba_ayuda') }}</p>
        <x-atoms.button type="submit" variant="outline" icon="forward_to_inbox">
            {{ __('configuracion.correo.prueba_boton') }}
        </x-atoms.button>
    </form>
@endif
