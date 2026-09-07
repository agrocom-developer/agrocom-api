{{--
    Partial: formulario de rol, compartido por create.blade.php y
    edit.blade.php — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Tres campos planos: nombre interno,
    descripción y estado.

    Los PERMISOS no están acá: viven en `permisos.blade.php`, con su propia
    ruta y su propio permiso (`asignar_permiso`). Son dos operaciones
    distintas — una cambia cómo se llama el rol, la otra reparte poder — y
    mezclarlas en un submit obligaría a exigir los dos permisos para
    cualquiera de las dos cosas.

    Espera:
    - $rol (SecRole|null): null en alta; el modelo en edición.
    - $esRolActivo (bool): si es el rol con el que está operando quien mira.
      Bloquea el switch de estado — desactivarlo lo dejaría fuera del panel
      en el request siguiente, y GuardarRol lo rechaza igual del lado del
      servidor (esto es solo no ofrecer lo que va a fallar).

    Tras un error de validación `old()` pisa los valores del modelo. `state`
    necesita el `old()` con centinela: un checkbox desmarcado no se envía, así
    que `old('state')` es `null` tanto en "no marcó" como en "primera carga",
    y sin distinguirlos la edición de un rol activo lo mostraría desactivado
    al volver de un error.
--}}
@php
    $esEdicion = $rol !== null;
    $accion = $esEdicion ? route('panel.roles.update', $rol) : route('panel.roles.store');
    $nombre = old('name', $rol?->name ?? '');
    $descripcion = old('description', $rol?->description ?? '');
    $activo = old('_enviado') === null ? ($rol?->state ?? true) : old('state') !== null;
@endphp

<form method="POST" action="{{ $accion }}" class="ag-rol-form" novalidate>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif
    {{-- Centinela para distinguir "no marcó el checkbox" de "primera carga". --}}
    <input type="hidden" name="_enviado" value="1">

    <x-organisms.page-header
        :title="$esEdicion ? __('seguridad.roles.editar_titulo', ['rol' => $rol->name]) : __('seguridad.roles.crear_titulo')"
        :subtitle="__($esEdicion ? 'seguridad.roles.editar_subtitulo' : 'seguridad.roles.crear_subtitulo')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.roles.index') }}" variant="outline">
                {{ __('seguridad.roles.cancelar') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __($esEdicion ? 'ui.action.save' : 'seguridad.roles.guardar_y_permisos') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if ($errors->has('estado'))
        <x-molecules.alert-strip variant="danger" icon="error" class="ag-rol-form__aviso">
            {{ $errors->first('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-section :title="__('seguridad.roles.seccion_identidad')">
        <x-atoms.input
            type="text"
            name="name"
            label="{{ __('seguridad.roles.campo_nombre') }}"
            :value="$nombre"
            :help="__('seguridad.roles.campo_nombre_ayuda')"
            :error="$errors->first('name')"
            required
            autocomplete="off"
            spellcheck="false"
        />

        <x-atoms.input
            class="ag-form-section__field--full"
            type="text"
            name="description"
            label="{{ __('seguridad.roles.campo_descripcion') }}"
            :value="$descripcion"
            :help="__('seguridad.roles.campo_descripcion_ayuda')"
            :error="$errors->first('description')"
            required
        />

        {{-- El átomo reenvía `$attributes` al <input>, no a su raíz, así que
             el ancho completo se pide desde un envoltorio propio. --}}
        <div class="ag-form-section__field--full">
            <x-atoms.switch
                name="state"
                label="{{ __('seguridad.roles.campo_activo') }}"
                :checked="$activo"
                :disabled="$esRolActivo"
                :help="__($esRolActivo ? 'seguridad.roles.campo_activo_bloqueado' : 'seguridad.roles.campo_activo_ayuda')"
            />

            {{-- Un checkbox deshabilitado no se envía: sin este hidden, editar
                 el nombre del propio rol activo lo desactivaría por omisión. --}}
            @if ($esRolActivo)
                <input type="hidden" name="state" value="1">
            @endif
        </div>
    </x-molecules.form-section>

    @unless ($esEdicion)
        <x-molecules.alert-strip variant="info" icon="tune" class="ag-rol-form__aviso">
            {{ __('seguridad.roles.creado') }}
        </x-molecules.alert-strip>
    @endunless
</form>
