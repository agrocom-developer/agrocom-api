{{--
    Partial: formulario de campaña, compartido por create.blade.php y
    edit.blade.php (ADR 0015 punto 1, tarea 69) — arquetipo Formulario, §6.3
    de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `personal/bases/_formulario.blade.php`, con el selector de cliente que
    agrega la corrección del 8/9/2026: la campaña es del cliente.

    Espera:
    - $campania (Campania|null): null en alta; el modelo en edición.
    - $clientesDisponibles (Collection<int, string>): id => razón social
      (ver CampaniasController::clientesDisponibles()) — la vista no conoce
      el modelo Cliente (cross-módulo, ADR 0003 regla 3).

    `estado` NUNCA es un campo de este formulario: lo cambia
    `panel.campanias.cambiar-estado` (otra pantalla, otra responsabilidad —
    invariante 7) — ver docblock de `CampaniasController`.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que bases/personas: ningún dato de solo lectura
    justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $campania !== null;
    $accion = $esEdicion ? route('panel.campanias.update', $campania) : route('panel.campanias.store');
    $clienteId = old('cliente_id', $campania?->cliente_id ?? '');
    $codigo = old('codigo', $campania?->codigo ?? '');
    $nombre = old('nombre', $campania?->nombre ?? '');
    $fechaInicio = old('fecha_inicio', $campania?->fecha_inicio?->toDateString() ?? '');
    $fechaFin = old('fecha_fin', $campania?->fecha_fin?->toDateString() ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-campanias-form" novalidate data-ag-campanias-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('campania.campanias.titulo_editar') : __('campania.campanias.titulo_crear')"
        :subtitle="__('campania.campanias.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.campanias.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('campania.campanias.seccion_datos')"
        :count="__('campania.campanias.campos_contador', ['cantidad' => 5])"
    >
        <x-atoms.select
            name="cliente_id"
            id="cliente_id"
            label="{{ __('campania.campanias.campo_cliente') }}"
            placeholder="{{ __('campania.campanias.campo_cliente_placeholder') }}"
            :options="$clientesDisponibles"
            value="{{ $clienteId }}"
            required
            error="{{ $errors->first('cliente_id') }}"
        />

        <x-atoms.input
            type="text"
            name="codigo"
            label="{{ __('campania.campanias.campo_codigo') }}"
            value="{{ $codigo }}"
            required
            maxlength="20"
            error="{{ $errors->first('codigo') }}"
        />

        <x-atoms.input
            type="text"
            name="nombre"
            label="{{ __('campania.campanias.campo_nombre') }}"
            value="{{ $nombre }}"
            error="{{ $errors->first('nombre') }}"
        />

        <x-atoms.date
            name="fecha_inicio"
            label="{{ __('campania.campanias.campo_fecha_inicio') }}"
            value="{{ $fechaInicio }}"
            required
            error="{{ $errors->first('fecha_inicio') }}"
        />

        <x-atoms.date
            name="fecha_fin"
            label="{{ __('campania.campanias.campo_fecha_fin') }}"
            value="{{ $fechaFin }}"
            required
            error="{{ $errors->first('fecha_fin') }}"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('campania.campanias.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.campanias.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
