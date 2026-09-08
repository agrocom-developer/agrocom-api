{{--
    Partial: formulario de usuario, compartido por create.blade.php y
    edit.blade.php (HU-45, tarea 39) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin sub-entidad repetible: `x-atoms.select`
    de persona + `x-atoms.checkbox-group` de roles (selección múltiple, tarea
    76/HU-53) + tres campos planos (name, username, password).

    Espera:
    - $usuario (SecUser|null): null en alta; el modelo en edición.
    - $rolesAsignados (list<int>): ids de rol ya asignados (solo en
      edición — ver UsuariosController::edit()).
    - $rolesDisponibles (Collection<int, SecRole>): opciones del selector de
      roles, ya sin "dueño" si el actor no tiene `asignar_rol_dueno` (ver
      UsuariosController::rolesDisponibles()).
    - $personasDisponibles (Collection<int, string>): id => nombre, personas
      vivas sin cuenta asignada + la propia persona en edición.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición. `password` NUNCA se repuebla con
    `old()`: un hash no se reconstruye y mostrar la contraseña tecleada de
    vuelta en un campo de error es una fuga innecesaria.
--}}
@php
    $esEdicion = $usuario !== null;
    $accion = $esEdicion ? route('panel.usuarios.update', $usuario) : route('panel.usuarios.store');
    $name = old('name', $usuario?->name ?? '');
    $username = old('username', $usuario?->username ?? '');
    $personaId = old('persona_id', $usuario?->persona_id ?? '');
    $rolesSeleccionados = array_map('strval', old('roles', $rolesAsignados ?? []));
    $opcionesRoles = $rolesDisponibles->mapWithKeys(fn ($rol) => [
        $rol->id => \App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol::nombreLegible($rol),
    ]);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-usuarios-form" novalidate data-ag-usuarios-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('seguridad.usuarios.titulo_editar') : __('seguridad.usuarios.titulo_crear')"
        :subtitle="__('seguridad.usuarios.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.usuarios.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('seguridad.usuarios.seccion_datos')"
        :count="__('seguridad.usuarios.campos_contador', ['cantidad' => 5])"
    >
        <x-atoms.input
            type="text"
            name="name"
            label="{{ __('seguridad.usuarios.campo_name') }}"
            value="{{ $name }}"
            required
            error="{{ $errors->first('name') }}"
        />

        <x-atoms.input
            type="text"
            name="username"
            label="{{ __('seguridad.usuarios.campo_username') }}"
            value="{{ $username }}"
            required
            error="{{ $errors->first('username') }}"
        />

        <x-atoms.input
            type="password"
            name="password"
            label="{{ __('seguridad.usuarios.campo_password') }}"
            help="{{ $esEdicion ? __('seguridad.usuarios.campo_password_ayuda_edicion') : __('seguridad.usuarios.campo_password_ayuda_alta') }}"
            :required="! $esEdicion"
            error="{{ $errors->first('password') }}"
        />

        <x-atoms.select
            name="persona_id"
            id="persona_id"
            label="{{ __('seguridad.usuarios.campo_persona') }}"
            :options="$personasDisponibles"
            :value="$personaId"
            placeholder="{{ __('seguridad.usuarios.campo_persona_placeholder') }}"
            error="{{ $errors->first('persona_id') }}"
        />

        <x-atoms.checkbox-group
            name="roles"
            id="roles"
            label="{{ __('seguridad.usuarios.campo_roles') }}"
            :options="$opcionesRoles"
            :value="$rolesSeleccionados"
            help="{{ __('seguridad.usuarios.campo_roles_ayuda') }}"
            error="{{ $errors->first('roles') }}"
            class="ag-form-section__field--full"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('seguridad.usuarios.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.usuarios.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
