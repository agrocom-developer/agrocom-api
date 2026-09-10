{{--
    Partial: formulario de generador, compartido por create.blade.php y
    edit.blade.php (tarea 72, HU-49) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `vehiculos/_formulario.blade.php`, con dos campos adicionales opcionales
    (modelo, horas de uso).

    Espera:
    - $generador (Generador|null): null en alta; el modelo en edición.
    - $basesDisponibles (Collection<int, string>): id => nombre, bases vivas
      (ver GeneradoresController::basesDisponibles()).
    - $estados (list<EstadoGenerador>): opciones del select de estado (ver
      GeneradoresController) — la vista no importa el enum de dominio, solo
      recorre `->value`/`->name`, mismo criterio que `$estados` en
      `vehiculos/_formulario.blade.php`.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que vehiculos/baterias: ningún dato de solo
    lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $generador !== null;
    $accion = $esEdicion ? route('panel.generadores.update', $generador) : route('panel.generadores.store');
    $identificador = old('identificador', $generador?->identificador ?? '');
    $modelo = old('modelo', $generador?->modelo ?? '');
    $baseId = old('base_id', $generador?->base_id ?? '');
    $estado = old('estado', $generador?->estado ?? 'activo');
    $horasUso = old('horas_uso', $generador?->horas_uso ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-generadores-form" novalidate data-ag-generadores-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('mantenimiento.generadores.titulo_editar') : __('mantenimiento.generadores.titulo_crear')"
        :subtitle="__('mantenimiento.generadores.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.generadores.index') }}" variant="outline" icon="arrow_back">
                {{ __('mantenimiento.generadores.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('mantenimiento.generadores.seccion_datos')"
        :count="__('mantenimiento.generadores.campos_contador', ['cantidad' => 5])"
    >
        <x-atoms.input
            type="text"
            name="identificador"
            label="{{ __('mantenimiento.generadores.campo_identificador') }}"
            value="{{ $identificador }}"
            required
            error="{{ $errors->first('identificador') }}"
        />

        <x-atoms.input
            type="text"
            name="modelo"
            label="{{ __('mantenimiento.generadores.campo_modelo') }}"
            value="{{ $modelo }}"
            error="{{ $errors->first('modelo') }}"
        />

        <x-atoms.select
            name="base_id"
            id="base_id"
            label="{{ __('mantenimiento.generadores.campo_base') }}"
            :options="$basesDisponibles"
            :value="$baseId"
            placeholder="{{ __('mantenimiento.generadores.campo_base_placeholder') }}"
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
            label="{{ __('mantenimiento.generadores.campo_estado') }}"
            :options="$opcionesEstado"
            :value="$estado"
            required
            error="{{ $errors->first('estado') }}"
        />

        <x-atoms.input
            type="number"
            name="horas_uso"
            label="{{ __('mantenimiento.generadores.campo_horas_uso') }}"
            value="{{ $horasUso }}"
            min="0"
            step="0.01"
            error="{{ $errors->first('horas_uso') }}"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('mantenimiento.generadores.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.generadores.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
