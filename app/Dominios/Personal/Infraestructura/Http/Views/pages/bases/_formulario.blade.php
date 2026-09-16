{{--
    Partial: formulario de base, compartido por create.blade.php y
    edit.blade.php (HU-26, tarea 37) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `drones/_formulario.blade.php`: sin sub-entidad repetible ni selects, una
    base es dos campos planos (nombre, ubicación).

    Espera:
    - $base (PerBase|null): null en alta; el modelo en edición.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que clientes/campos/drones: ningún dato de
    solo lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $base !== null;
    $accion = $esEdicion ? route('panel.bases.update', $base) : route('panel.bases.store');
    $nombre = old('nombre', $base?->nombre ?? '');
    $ubicacion = old('ubicacion', $base?->ubicacion ?? '');
    $latitud = old('latitud', $base?->latitud ?? '');
    $longitud = old('longitud', $base?->longitud ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-bases-form" novalidate data-ag-bases-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('personal.bases.titulo_editar') : __('personal.bases.titulo_crear')"
        :subtitle="__('personal.bases.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.bases.index') }}" variant="outline" icon="arrow_back">
                {{ __('personal.bases.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-section
        :title="__('personal.bases.seccion_datos')"
        :count="__('personal.bases.campos_contador', ['cantidad' => 4])"
    >
        <x-atoms.input
            type="text"
            name="nombre"
            label="{{ __('personal.bases.campo_nombre') }}"
            value="{{ $nombre }}"
            required
            error="{{ $errors->first('nombre') }}"
        />

        <x-atoms.input
            type="text"
            name="ubicacion"
            label="{{ __('personal.bases.campo_ubicacion') }}"
            value="{{ $ubicacion }}"
            error="{{ $errors->first('ubicacion') }}"
        />

        <x-atoms.input
            type="number"
            name="latitud"
            label="{{ __('personal.bases.campo_latitud') }}"
            value="{{ $latitud }}"
            step="0.000001"
            min="-90"
            max="90"
            error="{{ $errors->first('latitud') }}"
        />

        <x-atoms.input
            type="number"
            name="longitud"
            label="{{ __('personal.bases.campo_longitud') }}"
            value="{{ $longitud }}"
            step="0.000001"
            min="-180"
            max="180"
            error="{{ $errors->first('longitud') }}"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('personal.bases.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.bases.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
