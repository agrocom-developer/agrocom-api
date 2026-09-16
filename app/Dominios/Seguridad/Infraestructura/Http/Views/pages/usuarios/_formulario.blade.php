{{--
    Partial: formulario de usuario, compartido por create.blade.php y
    edit.blade.php (HU-45, tarea 39; tarea 65 le agrega el camino portal).
    Arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    Un solo formulario para los dos `type`: en ALTA, un `<select>` de tipo
    (oculto si el actor no tiene `seguridad.usuario.portal`: nunca ofrece una
    opción que el submit va a rechazar) alterna entre la sección "interna"
    (persona + roles) y la "portal" (cliente → contrato, select dependiente,
    mismo patrón que cliente→propiedad de `lotes/_formulario.blade.php` vía
    `resources/js/pages/usuarios-form.js`). En EDICIÓN no hay `<select>` de
    tipo — se muestra como dato fijo (CLAUDE.md, tarea 65: una cuenta no
    muta de interna a cliente ni al revés) y solo se renderiza la sección
    que corresponde al `type` ya persistido.

    Espera:
    - $usuario (SecUser|null): null en alta; el modelo en edición.
    - $rolesAsignados (list<int>): ids de rol ya asignados (solo edición).
    - $rolesDisponibles (Collection<int, SecRole>): opciones del selector de
      roles, ya sin "dueño" si el actor no tiene `asignar_rol_dueno`.
    - $personasDisponibles (Collection<int, string>): id => nombre.
    - $clientesDisponibles (Collection<int, string>): id => razón social —
      SOLO filtra el select de contrato, no viaja como columna propia (mismo
      criterio que `cliente_id` en `lotes/_formulario.blade.php`).
    - $contratosVigentesDisponibles (Collection<int, object{id,cliente_id,razon_social,hectareas_contratadas}>).
    - $puedeCrearPortal (bool): solo en alta — si falta, la opción `cliente`
      ni aparece en el `<select>` de tipo (la guarda real sigue siendo
      `seguridad.usuario.portal` en el controlador/caso de uso).
    - $emailPorCliente (array<int, string>): id de cliente => correo
      sugerido (tarea 66) — SOLO precarga el campo `email` cuando se elige
      un cliente en el camino portal, vía `usuarios-form.js`; no viaja como
      columna propia (mismo criterio que $clientesDisponibles). No hay
      precarga equivalente para el camino interno: `per_personas` no tiene
      correo ni teléfono (decisión de la tarea 66).

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición. `password` NUNCA se repuebla con
    `old()`.
--}}
@php
    $esEdicion = $usuario !== null;
    $accion = $esEdicion ? route('panel.usuarios.update', $usuario) : route('panel.usuarios.store');
    $name = old('name', $usuario?->name ?? '');
    $username = old('username', $usuario?->username ?? '');
    $email = old('email', $usuario?->email ?? '');
    $personaId = old('persona_id', $usuario?->persona_id ?? '');
    $rolesSeleccionados = array_map('strval', old('roles', $rolesAsignados ?? []));
    $opcionesRoles = $rolesDisponibles->mapWithKeys(fn ($rol) => [
        $rol->id => \App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol::nombreLegible($rol),
    ]);

    $tipoActual = $usuario?->type?->value ?? old('type', 'interno');
    $esCliente = $tipoActual === 'cliente';

    $opcionesContrato = $contratosVigentesDisponibles->mapWithKeys(fn ($contrato) => [
        $contrato->id => __('seguridad.usuarios.campo_contrato_opcion', [
            'cliente' => $contrato->razon_social,
            'hectareas' => $contrato->hectareas_contratadas,
        ]),
    ]);
    $mapaClienteContrato = $contratosVigentesDisponibles->pluck('cliente_id', 'id');
    $contratoActual = $contratosVigentesDisponibles->firstWhere('id', (int) ($usuario?->contrato_id ?? 0));
    $clienteId = old('cliente_id', $contratoActual?->cliente_id ?? '');
    $contratoId = old('contrato_id', $usuario?->contrato_id ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-usuarios-form" novalidate data-ag-usuarios-form data-tipo-inicial="{{ $tipoActual }}">
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('seguridad.usuarios.titulo_editar') : __('seguridad.usuarios.titulo_crear')"
        :subtitle="__('seguridad.usuarios.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.usuarios.index') }}" variant="outline" icon="arrow_back">
                {{ __('seguridad.usuarios.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-section :title="__('seguridad.usuarios.seccion_datos')">
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
            type="email"
            name="email"
            label="{{ __('seguridad.usuarios.campo_email') }}"
            value="{{ $email }}"
            help="{{ __('seguridad.usuarios.campo_email_ayuda') }}"
            error="{{ $errors->first('email') }}"
            data-ag-usuario-email
            data-mapa-cliente-email="{{ json_encode($emailPorCliente ?? []) }}"
        />

        <x-atoms.input
            type="password"
            name="password"
            label="{{ __('seguridad.usuarios.campo_password') }}"
            help="{{ $esEdicion ? __('seguridad.usuarios.campo_password_ayuda_edicion') : __('seguridad.usuarios.campo_password_ayuda_alta') }}"
            :required="! $esEdicion"
            error="{{ $errors->first('password') }}"
        />

        @if ($esEdicion)
            <div class="ag-input">
                <span class="ag-input__label">{{ __('seguridad.usuarios.campo_tipo') }}</span>
                <div class="ag-input__control">
                    <x-atoms.badge variant="neutral">
                        {{ __($esCliente ? 'seguridad.usuarios.tipo_cliente' : 'seguridad.usuarios.tipo_interno') }}
                    </x-atoms.badge>
                </div>
                <p class="ag-select__help">{{ __('seguridad.usuarios.campo_tipo_ayuda_edicion') }}</p>
            </div>
        @else
            <x-atoms.select
                name="type"
                id="type"
                label="{{ __('seguridad.usuarios.campo_tipo') }}"
                :options="[
                    'interno' => __('seguridad.usuarios.tipo_interno'),
                    ...($puedeCrearPortal ? ['cliente' => __('seguridad.usuarios.tipo_cliente')] : []),
                ]"
                :value="$tipoActual"
                required
                error="{{ $errors->first('type') }}"
                data-ag-usuario-tipo
            />
        @endif
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('seguridad.usuarios.seccion_interno')"
        data-ag-usuario-seccion-interno
        :hidden="$esCliente"
    >
        <x-atoms.select
            name="persona_id"
            id="persona_id"
            label="{{ __('seguridad.usuarios.campo_persona') }}"
            :options="$personasDisponibles"
            :value="$personaId"
            placeholder="{{ __('seguridad.usuarios.campo_persona_placeholder') }}"
            error="{{ $errors->first('persona_id') }}"
            :disabled="$esCliente"
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
            :disabled="$esCliente"
        />
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('seguridad.usuarios.seccion_portal')"
        data-ag-usuario-seccion-cliente
        :hidden="! $esCliente"
    >
        <x-atoms.select
            name="cliente_id"
            id="cliente_id"
            label="{{ __('seguridad.usuarios.campo_cliente') }}"
            :options="$clientesDisponibles"
            :value="$clienteId"
            placeholder="{{ __('seguridad.usuarios.campo_cliente_placeholder') }}"
            help="{{ __('seguridad.usuarios.campo_cliente_ayuda') }}"
            :disabled="! $esCliente"
            data-ag-usuario-cliente
        />

        <x-atoms.select
            name="contrato_id"
            id="contrato_id"
            label="{{ __('seguridad.usuarios.campo_contrato') }}"
            :options="$opcionesContrato"
            :value="$contratoId"
            placeholder="{{ __('seguridad.usuarios.campo_contrato_placeholder') }}"
            :required="$esCliente"
            error="{{ $errors->first('contrato_id') }}"
            :disabled="! $esCliente"
            data-ag-usuario-contrato
            data-mapa-cliente-contrato="{{ $mapaClienteContrato->toJson() }}"
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
