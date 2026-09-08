{{--
    Partial: formulario de cultivo, compartido por create.blade.php y
    edit.blade.php (HU-48, tarea 71) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `bases/_formulario.blade.php`: catálogo simple, un campo de texto y un
    interruptor.

    Espera:
    - $cultivo (Cultivo|null): null en alta; el modelo en edición.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que bases/clientes/campos: ningún dato de solo
    lectura justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $cultivo !== null;
    $accion = $esEdicion ? route('panel.cultivos.update', $cultivo) : route('panel.cultivos.store');
    $nombre = old('nombre', $cultivo?->nombre ?? '');
    $activo = old('activo', $cultivo?->activo ?? true);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-cultivos-form" novalidate data-ag-cultivos-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.cultivos.titulo_editar') : __('comercial.cultivos.titulo_crear')"
        :subtitle="__('comercial.cultivos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.cultivos.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('comercial.cultivos.seccion_datos')"
        :count="__('comercial.cultivos.campos_contador', ['cantidad' => 2])"
    >
        <x-atoms.input
            type="text"
            name="nombre"
            label="{{ __('comercial.cultivos.campo_nombre') }}"
            value="{{ $nombre }}"
            required
            error="{{ $errors->first('nombre') }}"
        />

        <div class="ag-form-section__field--full">
            <input type="hidden" name="activo" value="0">
            <x-atoms.switch
                name="activo"
                value="1"
                label="{{ __('comercial.cultivos.campo_activo') }}"
                :checked="(bool) $activo"
                help="{{ __('comercial.cultivos.campo_activo_ayuda') }}"
            />
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.cultivos.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.cultivos.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
