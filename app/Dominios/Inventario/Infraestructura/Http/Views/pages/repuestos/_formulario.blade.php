{{--
    Partial: formulario de repuesto, compartido por create.blade.php y
    edit.blade.php (HU-36, tarea 52) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `baterias/_formulario.blade.php`.

    Espera:
    - $repuesto (Repuesto|null): null en alta; el modelo en edición.

    Tras un error de validación, `old()` pisa los valores del
    modelo/vacíos — mismo criterio en alta y en edición.
    `costo_unitario` queda vacío por defecto (placeholder "sin compras
    todavía"): no tiene sentido forzar un 0 en el alta.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que baterias/vehiculos: ningún dato de solo
    lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $repuesto !== null;
    $accion = $esEdicion ? route('panel.repuestos.update', $repuesto) : route('panel.repuestos.store');
    $codigo = old('codigo', $repuesto?->codigo ?? '');
    $descripcion = old('descripcion', $repuesto?->descripcion ?? '');
    $unidad = old('unidad', $repuesto?->unidad ?? '');
    $costoUnitario = old('costo_unitario', $repuesto?->costo_unitario ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-repuestos-form" novalidate data-ag-repuestos-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('inventario.repuestos.titulo_editar') : __('inventario.repuestos.titulo_crear')"
        :subtitle="__('inventario.repuestos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button :href="route('panel.repuestos.index')" variant="outline" icon="arrow_back">
                {{ __('inventario.repuestos.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-section
        :title="__('inventario.repuestos.seccion_datos')"
        :count="__('inventario.repuestos.campos_contador', ['cantidad' => 4])"
    >
        <x-atoms.input
            type="text"
            name="codigo"
            :label="__('inventario.repuestos.campo_codigo')"
            :value="$codigo"
            required
            :error="$errors->first('codigo')"
        />

        <x-atoms.input
            type="text"
            name="descripcion"
            :label="__('inventario.repuestos.campo_descripcion')"
            :value="$descripcion"
            required
            :error="$errors->first('descripcion')"
        />

        <x-atoms.input
            type="text"
            name="unidad"
            :label="__('inventario.repuestos.campo_unidad')"
            :value="$unidad"
            :placeholder="__('inventario.repuestos.campo_unidad_placeholder')"
            required
            :error="$errors->first('unidad')"
        />

        <x-atoms.input
            type="number"
            name="costo_unitario"
            :label="__('inventario.repuestos.campo_costo')"
            :value="$costoUnitario"
            :placeholder="__('inventario.repuestos.campo_costo_placeholder')"
            min="0"
            step="0.01"
            :error="$errors->first('costo_unitario')"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('inventario.repuestos.estado_form')">
        <x-slot:actions>
            <x-atoms.button :href="route('panel.repuestos.index')" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
