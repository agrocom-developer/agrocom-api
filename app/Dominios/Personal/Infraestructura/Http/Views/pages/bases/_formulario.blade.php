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
            <x-atoms.button href="{{ route('panel.bases.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('personal.bases.seccion_datos')"
        :count="__('personal.bases.campos_contador', ['cantidad' => 2])"
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
