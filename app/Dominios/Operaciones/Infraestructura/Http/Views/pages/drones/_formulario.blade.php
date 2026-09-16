{{--
    Partial: formulario de dron, compartido por create.blade.php y
    edit.blade.php (HU-27, tarea 36) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `clientes/_formulario.blade.php`, pero sin sub-entidad repetible ni
    selects: un dron es cuatro campos planos (identificador, modelo,
    capacidad_l, capacidad_kg — HU-81, tarea 96).

    Espera:
    - $dron (Dron|null): null en alta; el modelo en edición.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que clientes/campos: ningún dato de solo
    lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $dron !== null;
    $accion = $esEdicion ? route('panel.drones.update', $dron) : route('panel.drones.store');
    $identificador = old('identificador', $dron?->identificador ?? '');
    $modelo = old('modelo', $dron?->modelo ?? '');
    $capacidadL = old('capacidad_l', $dron?->capacidad_l !== null ? (string) (int) $dron->capacidad_l : '');
    $capacidadKg = old('capacidad_kg', $dron?->capacidad_kg ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-drones-form" novalidate data-ag-drones-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('operaciones.drones.titulo_editar') : __('operaciones.drones.titulo_crear')"
        :subtitle="__('operaciones.drones.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.drones.index') }}" variant="outline" icon="arrow_back">
                {{ __('operaciones.drones.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-section
        :title="__('operaciones.drones.seccion_datos')"
        :count="__('operaciones.drones.campos_contador', ['cantidad' => 4])"
    >
        <x-atoms.input
            type="text"
            name="identificador"
            label="{{ __('operaciones.drones.campo_identificador') }}"
            value="{{ $identificador }}"
            required
            error="{{ $errors->first('identificador') }}"
        />

        <x-atoms.input
            type="text"
            name="modelo"
            label="{{ __('operaciones.drones.campo_modelo') }}"
            value="{{ $modelo }}"
            help="{{ __('operaciones.drones.campo_modelo_ayuda') }}"
            error="{{ $errors->first('modelo') }}"
        />

        <x-atoms.input
            type="number"
            name="capacidad_l"
            label="{{ __('operaciones.drones.campo_capacidad') }}"
            value="{{ $capacidadL }}"
            help="{{ __('operaciones.drones.campo_capacidad_ayuda') }}"
            error="{{ $errors->first('capacidad_l') }}"
            min="30"
            max="60"
            step="1"
        />

        <x-atoms.input
            type="number"
            name="capacidad_kg"
            label="{{ __('operaciones.drones.campo_capacidad_kg') }}"
            value="{{ $capacidadKg }}"
            help="{{ __('operaciones.drones.campo_capacidad_kg_ayuda') }}"
            error="{{ $errors->first('capacidad_kg') }}"
            min="0"
            step="0.01"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('operaciones.drones.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.drones.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
