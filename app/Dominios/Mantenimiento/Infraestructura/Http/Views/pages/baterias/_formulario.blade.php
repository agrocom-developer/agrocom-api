{{--
    Partial: formulario de batería, compartido por create.blade.php y
    edit.blade.php (HU-39, tarea 51) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `vehiculos/_formulario.blade.php`, con un campo numérico adicional
    (ciclos acumulados).

    Espera:
    - $bateria (Bateria|null): null en alta; el modelo en edición.
    - $basesDisponibles (Collection<int, string>): id => nombre, bases vivas
      (ver BateriasController::basesDisponibles()).
    - $estados (list<EstadoBateria>): opciones del select de estado (ver
      BateriasController) — la vista no importa el enum de dominio, solo
      recorre `->value`/`->name`, mismo criterio que `$estados` en
      `vehiculos/_formulario.blade.php`.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición. `ciclos_acumulados` arranca en 0
    en alta (no vacío): el campo es requerido y numérico, un placeholder
    vacío invitaría a dejarlo en blanco.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que vehiculos/personas: ningún dato de solo
    lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $bateria !== null;
    $accion = $esEdicion ? route('panel.baterias.update', $bateria) : route('panel.baterias.store');
    $identificador = old('identificador', $bateria?->identificador ?? '');
    $ciclosAcumulados = old('ciclos_acumulados', $bateria?->ciclos_acumulados ?? 0);
    $baseId = old('base_id', $bateria?->base_id ?? '');
    $estado = old('estado', $bateria?->estado ?? 'activa');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-baterias-form" novalidate data-ag-baterias-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('mantenimiento.baterias.titulo_editar') : __('mantenimiento.baterias.titulo_crear')"
        :subtitle="__('mantenimiento.baterias.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.baterias.index') }}" variant="outline" icon="arrow_back">
                {{ __('mantenimiento.baterias.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('mantenimiento.baterias.seccion_datos')"
        :count="__('mantenimiento.baterias.campos_contador', ['cantidad' => 4])"
    >
        <x-atoms.input
            type="text"
            name="identificador"
            label="{{ __('mantenimiento.baterias.campo_identificador') }}"
            value="{{ $identificador }}"
            required
            error="{{ $errors->first('identificador') }}"
        />

        <x-atoms.input
            type="number"
            name="ciclos_acumulados"
            label="{{ __('mantenimiento.baterias.campo_ciclos') }}"
            value="{{ $ciclosAcumulados }}"
            min="0"
            required
            error="{{ $errors->first('ciclos_acumulados') }}"
        />

        <x-atoms.select
            name="base_id"
            id="base_id"
            label="{{ __('mantenimiento.baterias.campo_base') }}"
            :options="$basesDisponibles"
            :value="$baseId"
            placeholder="{{ __('mantenimiento.baterias.campo_base_placeholder') }}"
            error="{{ $errors->first('base_id') }}"
        />

        @php
            $opcionesEstado = collect($estados)->mapWithKeys(fn ($opcion) => [
                $opcion->value => __('mantenimiento.estado_bateria.'.$opcion->value)
            ])->all();
        @endphp
        <x-atoms.select
            name="estado"
            id="estado"
            label="{{ __('mantenimiento.baterias.campo_estado') }}"
            :options="$opcionesEstado"
            :value="$estado"
            required
            error="{{ $errors->first('estado') }}"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('mantenimiento.baterias.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.baterias.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
