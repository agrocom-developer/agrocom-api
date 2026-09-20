{{--
    Partial: formulario de generador, compartido por create.blade.php y
    edit.blade.php (tarea 72, HU-49; horas inicial/actual HU-86, tarea 101)
    — arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    Homogeneizado con el patrón de Propiedades y Drones (tarea 115): el cuerpo
    va en `molecules/form-layout` y, SOLO en edición, el aside con el resumen
    relacionado (§6.3.1) — un generador recién creado no puede tener todavía
    cuadrillas ni cargas de combustible. Dos secciones: los datos del
    generador y sus horas de uso, con la unidad («h») como sufijo del campo.

    Espera:
    - $generador (Generador|null): null en alta; el modelo en edición.
    - $basesDisponibles (Collection<int, string>): id => nombre, bases vivas
      (ver GeneradoresController::basesDisponibles()).
    - $estados (list<EstadoGenerador>): opciones del select de estado (ver
      GeneradoresController) — la vista no importa el enum de dominio, solo
      recorre `->value`/`->name`, mismo criterio que `$estados` en
      `vehiculos/_formulario.blade.php`.
    - $resumenRelacionado (list<array{...}>|null): solo en edición, ver
      GeneradoresController::resumenRelacionado(). `null`/ausente en alta.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.
--}}
@php
    $esEdicion = $generador !== null;
    $accion = $esEdicion ? route('panel.generadores.update', $generador) : route('panel.generadores.store');
    $identificador = old('identificador', $generador?->identificador ?? '');
    $modelo = old('modelo', $generador?->modelo ?? '');
    $baseId = old('base_id', $generador?->base_id ?? '');
    $estado = old('estado', $generador?->estado ?? 'activo');
    $horasInicial = old('horas_inicial', $generador?->horas_inicial ?? '');
    $horasActual = old('horas_actual', $generador?->horas_actual ?? '');
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
            <x-molecules.boton-volver
                :href="route('panel.generadores.index')"
                :label="__('mantenimiento.generadores.volver')"
            />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('mantenimiento.generadores.seccion_datos')"
            :count="__('mantenimiento.generadores.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.input
                type="text"
                name="identificador"
                :label="__('mantenimiento.generadores.campo_identificador')"
                :value="$identificador"
                required
                :error="$errors->first('identificador')"
            />

            <x-atoms.input
                type="text"
                name="modelo"
                :label="__('mantenimiento.generadores.campo_modelo')"
                :value="$modelo"
                :error="$errors->first('modelo')"
            />

            <x-atoms.select
                name="base_id"
                id="base_id"
                :label="__('mantenimiento.generadores.campo_base')"
                :options="$basesDisponibles"
                :value="$baseId"
                :placeholder="__('mantenimiento.generadores.campo_base_placeholder')"
                :error="$errors->first('base_id')"
            />

            @php
                $opcionesEstado = collect($estados)->mapWithKeys(fn ($opcion) => [
                    $opcion->value => __('mantenimiento.estado.'.$opcion->value),
                ])->all();
            @endphp
            <x-atoms.select
                name="estado"
                id="estado"
                :label="__('mantenimiento.generadores.campo_estado')"
                :options="$opcionesEstado"
                :value="$estado"
                required
                :error="$errors->first('estado')"
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            :title="__('mantenimiento.generadores.seccion_horas')"
            :count="__('mantenimiento.generadores.campos_contador', ['cantidad' => 2])"
        >
            <x-atoms.input
                type="number"
                name="horas_inicial"
                :label="__('mantenimiento.generadores.campo_horas_inicial')"
                :value="$horasInicial"
                :suffix="__('mantenimiento.generadores.unidad_horas')"
                :help="__('mantenimiento.generadores.campo_horas_inicial_ayuda')"
                min="0"
                step="0.01"
                :error="$errors->first('horas_inicial')"
            />

            <x-atoms.input
                type="number"
                name="horas_actual"
                :label="__('mantenimiento.generadores.campo_horas_actual')"
                :value="$horasActual"
                :suffix="__('mantenimiento.generadores.unidad_horas')"
                min="0"
                step="0.01"
                :error="$errors->first('horas_actual')"
            />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('mantenimiento.generadores.estado_form')">
            <x-slot:actions>
                <x-atoms.button :href="route('panel.generadores.index')" variant="outline">
                    {{ __('ui.action.cancel') }}
                </x-atoms.button>
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>

        @if ($esEdicion)
            <x-slot:aside>
                @include('mantenimiento::pages._resumen-relacionado', ['resumenRelacionado' => $resumenRelacionado ?? []])
            </x-slot:aside>
        @endif
    </x-molecules.form-layout>
</form>
