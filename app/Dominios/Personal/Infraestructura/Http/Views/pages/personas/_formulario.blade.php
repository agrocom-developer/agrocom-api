{{--
    Partial: formulario de persona, compartido por create.blade.php y
    edit.blade.php (HU-26, tarea 37) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin sub-entidad repetible: dos
    selects nativos (rol, base) + tres campos planos (nombre, tarifa_ha,
    activo) — mismo criterio de select nativo que `cliente_id` en
    `campos/_formulario.blade.php` (no hay átomo `select` en el catálogo).

    Espera:
    - $persona (PerPersona|null): null en alta; el modelo en edición.
    - $roles (list<RolOperativoPersona>): opciones del select de rol (ver
      PersonasController) — la vista no conoce el enum de dominio más allá
      de sus `value`/`name` para armar el <option>.
    - $basesDisponibles (Collection<int, string>): id => nombre, bases
      activas (ver PersonasController::basesActivas()).

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que bases/clientes/campos/drones: ningún dato
    de solo lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $persona !== null;
    $accion = $esEdicion ? route('panel.personas.update', $persona) : route('panel.personas.store');
    $nombre = old('nombre', $persona?->nombre ?? '');
    $rol = old('rol', $persona?->rol?->value ?? '');
    $baseId = old('base_id', $persona?->base_id ?? '');
    $tarifaHa = old('tarifa_ha', $persona?->tarifa_ha ?? '');
    $activo = old('activo', $persona?->activo ?? true);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-personas-form" novalidate data-ag-personas-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('personal.personas.titulo_editar') : __('personal.personas.titulo_crear')"
        :subtitle="__('personal.personas.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.personas.index') }}" variant="outline" icon="arrow_back">
                {{ __('personal.personas.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('personal.personas.seccion_datos')"
        :count="__('personal.personas.campos_contador', ['cantidad' => 5])"
    >
        <x-atoms.input
            type="text"
            name="nombre"
            label="{{ __('personal.personas.campo_nombre') }}"
            value="{{ $nombre }}"
            required
            error="{{ $errors->first('nombre') }}"
        />

        @php
            $opcionesRol = collect($roles)->mapWithKeys(
                fn ($opcionRol) => [$opcionRol->value => __('personal.roles.'.$opcionRol->value)]
            );
        @endphp

        <x-atoms.select
            name="rol"
            id="rol"
            label="{{ __('personal.personas.campo_rol') }}"
            :options="$opcionesRol"
            :value="$rol"
            placeholder="{{ __('personal.personas.campo_rol_placeholder') }}"
            error="{{ $errors->first('rol') }}"
            required
        />

        <x-atoms.select
            name="base_id"
            id="base_id"
            label="{{ __('personal.personas.campo_base') }}"
            :options="$basesDisponibles"
            :value="$baseId"
            placeholder="{{ __('personal.personas.campo_base_placeholder') }}"
            error="{{ $errors->first('base_id') }}"
        />

        <x-atoms.input
            type="number"
            name="tarifa_ha"
            label="{{ __('personal.personas.campo_tarifa') }}"
            value="{{ $tarifaHa }}"
            help="{{ __('personal.personas.campo_tarifa_ayuda') }}"
            error="{{ $errors->first('tarifa_ha') }}"
            min="0"
            step="0.01"
        />

        <div class="ag-form-section__field--full">
            <input type="hidden" name="activo" value="0">
            <x-atoms.switch
                name="activo"
                value="1"
                label="{{ __('personal.personas.campo_activo') }}"
                :checked="(bool) $activo"
                help="{{ __('personal.personas.campo_activo_ayuda') }}"
            />
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('personal.personas.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.personas.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
