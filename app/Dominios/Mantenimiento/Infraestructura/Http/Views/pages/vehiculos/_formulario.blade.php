{{--
    Partial: formulario de vehículo, compartido por create.blade.php y
    edit.blade.php (HU-40, tarea 50; ficha completa y estado `pausa` HU-84,
    tarea 99) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `personas/_formulario.blade.php`: selects nativos (base, combustible,
    ambos opcionales) + campos planos — sin sub-entidad ni átomo `select` en
    el catálogo.

    Espera:
    - $vehiculo (Vehiculo|null): null en alta; el modelo en edición.
    - $basesDisponibles (Collection<int, string>): id => nombre, bases vivas
      (ver VehiculosController::basesDisponibles()).
    - $estados (list<EstadoVehiculo>): opciones del select de estado (ver
      VehiculosController) — la vista no importa el enum de dominio, solo
      recorre `->value`/`->name`, mismo criterio que `$roles` en
      `personas/_formulario.blade.php`.
    - $combustibles (list<TipoCombustibleVehiculo>): ídem, para el select de
      combustible.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición. `kilometraje_inicial` es editable
    en alta y en edición por igual (a diferencia de `ciclos_inicial` en
    `baterias/_formulario.blade.php`): la HU no lo pide inmutable.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que drones/personas: ningún dato de solo
    lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $vehiculo !== null;
    $accion = $esEdicion ? route('panel.vehiculos.update', $vehiculo) : route('panel.vehiculos.store');
    $identificador = old('identificador', $vehiculo?->identificador ?? '');
    $marca = old('marca', $vehiculo?->marca ?? '');
    $modelo = old('modelo', $vehiculo?->modelo ?? '');
    $anio = old('anio', $vehiculo?->anio ?? '');
    $combustible = old('combustible', $vehiculo?->combustible ?? '');
    $es4x4 = (bool) old('es_4x4', $vehiculo?->es_4x4 ?? false);
    $kilometrajeInicial = old('kilometraje_inicial', $vehiculo?->kilometraje_inicial ?? '');
    $kilometrajeActual = old('kilometraje_actual', $vehiculo?->kilometraje_actual ?? '');
    $baseId = old('base_id', $vehiculo?->base_id ?? '');
    $estado = old('estado', $vehiculo?->estado ?? 'activo');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-vehiculos-form" novalidate data-ag-vehiculos-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('mantenimiento.vehiculos.titulo_editar') : __('mantenimiento.vehiculos.titulo_crear')"
        :subtitle="__('mantenimiento.vehiculos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.vehiculos.index') }}" variant="outline" icon="arrow_back">
                {{ __('mantenimiento.vehiculos.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('mantenimiento.vehiculos.seccion_datos')"
        :count="__('mantenimiento.vehiculos.campos_contador', ['cantidad' => 10])"
    >
        <x-atoms.input
            type="text"
            name="identificador"
            label="{{ __('mantenimiento.vehiculos.campo_identificador') }}"
            value="{{ $identificador }}"
            required
            error="{{ $errors->first('identificador') }}"
        />

        <x-atoms.input
            type="text"
            name="marca"
            label="{{ __('mantenimiento.vehiculos.campo_marca') }}"
            value="{{ $marca }}"
            error="{{ $errors->first('marca') }}"
        />

        <x-atoms.input
            type="text"
            name="modelo"
            label="{{ __('mantenimiento.vehiculos.campo_modelo') }}"
            value="{{ $modelo }}"
            error="{{ $errors->first('modelo') }}"
        />

        <x-atoms.input
            type="number"
            name="anio"
            label="{{ __('mantenimiento.vehiculos.campo_anio') }}"
            value="{{ $anio }}"
            error="{{ $errors->first('anio') }}"
            step="1"
        />

        @php
            $opcionesCombustible = collect($combustibles)->mapWithKeys(fn ($opcion) => [
                $opcion->value => __('mantenimiento.vehiculos.combustible.'.$opcion->value)
            ])->all();
        @endphp
        <x-atoms.select
            name="combustible"
            id="combustible"
            label="{{ __('mantenimiento.vehiculos.campo_combustible') }}"
            :options="$opcionesCombustible"
            :value="$combustible"
            placeholder="{{ __('mantenimiento.vehiculos.campo_combustible_placeholder') }}"
            error="{{ $errors->first('combustible') }}"
        />

        <input type="hidden" name="es_4x4" value="0">
        <x-atoms.checkbox
            name="es_4x4"
            value="1"
            label="{{ __('mantenimiento.vehiculos.campo_es_4x4') }}"
            :checked="$es4x4"
        />

        <x-atoms.input
            type="number"
            name="kilometraje_inicial"
            label="{{ __('mantenimiento.vehiculos.campo_kilometraje_inicial') }}"
            value="{{ $kilometrajeInicial }}"
            help="{{ __('mantenimiento.vehiculos.campo_kilometraje_inicial_ayuda') }}"
            error="{{ $errors->first('kilometraje_inicial') }}"
            min="0"
            step="0.01"
        />

        <x-atoms.input
            type="number"
            name="kilometraje_actual"
            label="{{ __('mantenimiento.vehiculos.campo_kilometraje_actual') }}"
            value="{{ $kilometrajeActual }}"
            error="{{ $errors->first('kilometraje_actual') }}"
            min="0"
            step="0.01"
        />

        <x-atoms.select
            name="base_id"
            id="base_id"
            label="{{ __('mantenimiento.vehiculos.campo_base') }}"
            :options="$basesDisponibles"
            :value="$baseId"
            placeholder="{{ __('mantenimiento.vehiculos.campo_base_placeholder') }}"
            error="{{ $errors->first('base_id') }}"
        />

        @php
            $opcionesEstado = collect($estados)->mapWithKeys(fn ($opcion) => [
                $opcion->value => __('mantenimiento.estado.'.$opcion->value)
            ])->all();
        @endphp
        <x-atoms.select
            name="estado"
            id="estado"
            label="{{ __('mantenimiento.vehiculos.campo_estado') }}"
            :options="$opcionesEstado"
            :value="$estado"
            required
            error="{{ $errors->first('estado') }}"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('mantenimiento.vehiculos.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.vehiculos.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
