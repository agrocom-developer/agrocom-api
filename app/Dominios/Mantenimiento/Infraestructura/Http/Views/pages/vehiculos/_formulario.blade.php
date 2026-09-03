{{--
    Partial: formulario de vehículo, compartido por create.blade.php y
    edit.blade.php (HU-40, tarea 50) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `personas/_formulario.blade.php`: un select nativo (base, opcional) +
    campos planos — sin sub-entidad ni átomo `select` en el catálogo.

    Espera:
    - $vehiculo (Vehiculo|null): null en alta; el modelo en edición.
    - $basesDisponibles (Collection<int, string>): id => nombre, bases vivas
      (ver VehiculosController::basesDisponibles()).
    - $estados (list<EstadoVehiculo>): opciones del select de estado (ver
      VehiculosController) — la vista no importa el enum de dominio, solo
      recorre `->value`/`->name`, mismo criterio que `$roles` en
      `personas/_formulario.blade.php`.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que drones/personas: ningún dato de solo
    lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $vehiculo !== null;
    $accion = $esEdicion ? route('panel.vehiculos.update', $vehiculo) : route('panel.vehiculos.store');
    $identificador = old('identificador', $vehiculo?->identificador ?? '');
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
            <x-atoms.button href="{{ route('panel.vehiculos.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('mantenimiento.vehiculos.seccion_datos')"
        :count="__('mantenimiento.vehiculos.campos_contador', ['cantidad' => 3])"
    >
        <x-atoms.input
            type="text"
            name="identificador"
            label="{{ __('mantenimiento.vehiculos.campo_identificador') }}"
            value="{{ $identificador }}"
            required
            error="{{ $errors->first('identificador') }}"
        />

        <div class="ag-input">
            <label for="base_id" class="ag-input__label">{{ __('mantenimiento.vehiculos.campo_base') }}</label>
            <div class="ag-input__control {{ $errors->has('base_id') ? 'ag-input__control--error' : '' }}">
                <select name="base_id" id="base_id" class="ag-input__field">
                    <option value="">{{ __('mantenimiento.vehiculos.campo_base_placeholder') }}</option>
                    @foreach ($basesDisponibles as $id => $nombreBase)
                        <option value="{{ $id }}" @selected((string) $baseId === (string) $id)>{{ $nombreBase }}</option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('base_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('base_id') }}</p>
            @endif
        </div>

        <div class="ag-input">
            <label for="estado" class="ag-input__label">
                {{ __('mantenimiento.vehiculos.campo_estado') }}
                <span class="ag-input__required" aria-hidden="true">*</span>
            </label>
            <div class="ag-input__control {{ $errors->has('estado') ? 'ag-input__control--error' : '' }}">
                <select name="estado" id="estado" class="ag-input__field" required>
                    @foreach ($estados as $opcionEstado)
                        <option value="{{ $opcionEstado->value }}" @selected($estado === $opcionEstado->value)>
                            {{ __('mantenimiento.estado.'.$opcionEstado->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('estado'))
                <p class="ag-input__error" role="alert">{{ $errors->first('estado') }}</p>
            @endif
        </div>
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
