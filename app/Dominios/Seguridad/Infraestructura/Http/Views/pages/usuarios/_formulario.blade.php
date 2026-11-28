{{--
    Partial: formulario de usuario, compartido por create.blade.php y edit.blade.php
    (HU-45, tarea 39). Arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    Solo cuentas de panel (ADR 0046): datos de la cuenta y un solo rol, administrador u
    operador. El alta termina en la ficha (`show`); desde la edición, Volver y Cancelar
    regresan a la ficha de la cuenta.

    Espera:
    - $usuario (SecUser|null): null en alta; el modelo en edición.
    - $rolesAsignados (list<int>): ids de rol ya asignados (solo edición).
    - $rolesDisponibles (Collection<int, SecRole>): opciones del selector de rol, ya sin
      `administrador` si el actor no tiene `asignar_rol_administrador`.
    - $resumenRelacionado (list<array>, solo edición): las tarjetas del aside (`titulo`,
      `icono`, `tieneDatos`, `items`, `vacioTitulo`, `vacioDetalle`, `acciones`), armadas por
      UsuariosController::resumenRelacionado() y ya gateadas por el permiso `.ver` del módulo
      de cada una. En el alta no hay aside: una cuenta que todavía no existe no tiene nada
      relacionado.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos — mismo criterio
    en alta y en edición. `password` NUNCA se repuebla con `old()`.
--}}
@php
    $esEdicion = $usuario !== null;
    $accion = $esEdicion ? route('panel.usuarios.update', $usuario) : route('panel.usuarios.store');
    $name = old('name', $usuario?->name ?? '');
    $username = old('username', $usuario?->username ?? '');
    $email = old('email', $usuario?->email ?? '');
    $rolesSeleccionados = array_map('strval', old('roles', $rolesAsignados ?? []));
    $opcionesRoles = $rolesDisponibles->mapWithKeys(fn ($rol) => [
        $rol->id => \App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol::nombreLegible($rol),
    ]);
    $urlVolver = $esEdicion ? route('panel.usuarios.show', $usuario) : route('panel.usuarios.index');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-proveedores" novalidate>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('seguridad.usuarios.titulo_editar') : __('seguridad.usuarios.titulo_crear')"
        :subtitle="__('seguridad.usuarios.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver
                :href="$urlVolver"
                :label="__($esEdicion ? 'seguridad.usuarios.volver_ficha' : 'seguridad.usuarios.volver')"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    {{-- Los casos de uso rechazan un usuario o un correo duplicados con este
         error y el controlador vuelve al formulario: sin pintarlo, guardar no
         mostraba nada. --}}
    @if ($errors->has('estado'))
        <x-molecules.alert-strip variant="danger" icon="error">
            {{ $errors->first('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            accent="primary-2"
            :title="__('seguridad.usuarios.seccion_datos')"
            :count="__('seguridad.usuarios.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.input
                type="text"
                name="name"
                :label="__('seguridad.usuarios.campo_name')"
                :value="$name"
                required
                :error="$errors->first('name')"
            />

            <x-atoms.input
                type="text"
                name="username"
                :label="__('seguridad.usuarios.campo_username')"
                :value="$username"
                required
                :error="$errors->first('username')"
            />

            <x-atoms.input
                type="email"
                name="email"
                :label="__('seguridad.usuarios.campo_email')"
                :value="$email"
                :help="__('seguridad.usuarios.campo_email_ayuda')"
                :error="$errors->first('email')"
            />

            <x-atoms.input
                type="password"
                name="password"
                :label="__('seguridad.usuarios.campo_password')"
                :help="$esEdicion ? __('seguridad.usuarios.campo_password_ayuda_edicion') : __('seguridad.usuarios.campo_password_ayuda_alta')"
                :required="! $esEdicion"
                :error="$errors->first('password')"
            />

            @unless ($esEdicion)
                {{-- Las cuentas que se crean desde el panel son de panel; las de app se registran desde la app (ADR 0046). --}}
                <input type="hidden" name="type" value="interno">
            @endunless
        </x-molecules.form-section>

        <x-molecules.form-section
            accent="info"
            :title="__('seguridad.usuarios.seccion_rol')"
            :count="__('seguridad.usuarios.campos_contador', ['cantidad' => 1])"
        >
            {{-- Una cuenta de panel tiene un solo rol (ADR 0046): radios, y `roles[]` viaja con un solo valor. --}}
            <x-atoms.radio-group
                name="roles[]"
                id="roles"
                :label="__('seguridad.usuarios.campo_roles')"
                :options="$opcionesRoles"
                :value="$rolesSeleccionados[0] ?? null"
                :help="__('seguridad.usuarios.campo_roles_ayuda')"
                :error="$errors->first('roles') ?: $errors->first('roles.0')"
                class="ag-form-section__field--full"
                required
            />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('seguridad.usuarios.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="$urlVolver" cancelar />
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @foreach ($resumenRelacionado ?? [] as $resumen)
                    @if ($resumen['tieneDatos'])
                        <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                            @if ($resumen['acciones'] !== [])
                                <x-slot:action>
                                    @foreach ($resumen['acciones'] as $accionResumen)
                                        <x-atoms.button :href="$accionResumen['href']" variant="outline" :icon="$accionResumen['icono'] ?? 'arrow_forward'" block>
                                            {{ $accionResumen['label'] }}
                                        </x-atoms.button>
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                    @else
                        <x-molecules.empty-state
                            :icon="$resumen['icono']"
                            :title="$resumen['vacioTitulo']"
                            :detail="$resumen['vacioDetalle']"
                        />
                    @endif
                @endforeach
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
