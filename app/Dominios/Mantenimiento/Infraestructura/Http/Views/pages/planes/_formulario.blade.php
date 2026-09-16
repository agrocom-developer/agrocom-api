{{--
    Partial: formulario de plan de mantenimiento, compartido por
    create.blade.php y edit.blade.php (HU-38, tarea 54) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `baterias/_formulario.blade.php`, con tres campos (sin selects de
    catálogo: `modelo` es texto libre, sin FK — ver docblock de la migración
    y de `App\Dominios\Operaciones\Contratos\LecturaHorasVueloPorModelo`).

    Espera:
    - $plan (PlanMantenimiento|null): null en alta; el modelo en edición.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que baterias/vehiculos: ningún dato de solo
    lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $plan !== null;
    $accion = $esEdicion ? route('panel.planes-mantenimiento.update', $plan) : route('panel.planes-mantenimiento.store');
    $modelo = old('modelo', $plan?->modelo ?? '');
    $tarea = old('tarea', $plan?->tarea ?? '');
    $horasUmbral = old('horas_umbral', $plan?->horas_umbral ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-planes-form" novalidate data-ag-planes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('mantenimiento.planes.titulo_editar') : __('mantenimiento.planes.titulo_crear')"
        :subtitle="__('mantenimiento.planes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.planes-mantenimiento.index') }}" variant="outline" icon="arrow_back">
                {{ __('mantenimiento.planes.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-section
        :title="__('mantenimiento.planes.seccion_datos')"
        :count="__('mantenimiento.planes.campos_contador', ['cantidad' => 3])"
    >
        <x-atoms.input
            type="text"
            name="modelo"
            label="{{ __('mantenimiento.planes.campo_modelo') }}"
            value="{{ $modelo }}"
            help="{{ __('mantenimiento.planes.campo_modelo_ayuda') }}"
            required
            error="{{ $errors->first('modelo') }}"
        />

        <x-atoms.input
            type="text"
            name="tarea"
            label="{{ __('mantenimiento.planes.campo_tarea') }}"
            value="{{ $tarea }}"
            required
            error="{{ $errors->first('tarea') }}"
        />

        <x-atoms.input
            type="number"
            name="horas_umbral"
            label="{{ __('mantenimiento.planes.campo_horas_umbral') }}"
            value="{{ $horasUmbral }}"
            min="0.01"
            step="0.01"
            required
            error="{{ $errors->first('horas_umbral') }}"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('mantenimiento.planes.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.planes-mantenimiento.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
