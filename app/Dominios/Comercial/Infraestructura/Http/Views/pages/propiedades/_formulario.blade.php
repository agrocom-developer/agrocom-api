{{--
    Partial: formulario de propiedad, compartido por create.blade.php y
    edit.blade.php (ADR 0018) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `clientes/_formulario.blade.php` (tarea 33): las dos páginas arman el
    MISMO formulario; lo único que cambia es contra qué URL/método postea y
    los valores iniciales.

    Espera:
    - $propiedad (Propiedad|null): null en alta; el modelo, en edición.
    - $clientesDisponibles (Collection<int, string>): id => razón social,
      clientes activos (ver PropiedadesController::clientesActivos()) — la vista
      no conoce el modelo Cliente.
    - $clienteIdPreseleccionado (int|null, tarea "resumen de cliente"): solo
      en alta, desde `?cliente_id=` (ver PropiedadesController::create()) —
      el atajo "Nueva propiedad" del aside de `panel.clientes.edit` llega acá
      con el cliente ya elegido. `edit()` no lo pasa (`null` por el `??` de
      abajo).

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito: ninguna métrica de solo lectura de la propiedad EN SÍ
    justifica hoy la columna lateral (distinto del cliente, que desde la
    tarea "resumen de cliente" sí tiene una: contratos/propiedades/campañas
    relacionados, no de la propiedad).
--}}
@php
    $esEdicion = $propiedad !== null;
    $accion = $esEdicion ? route('panel.propiedades.update', $propiedad) : route('panel.propiedades.store');
    // $clienteIdPreseleccionado (tarea "resumen de cliente"): solo llega en
    // alta, desde el atajo del aside de `panel.clientes.edit`.
    $clienteId = old('cliente_id', $propiedad?->cliente_id ?? $clienteIdPreseleccionado ?? '');
    $nombre = old('nombre', $propiedad?->nombre ?? '');
    $ubicacion = old('ubicacion', $propiedad?->ubicacion ?? '');
    $departamento = old('departamento', $propiedad?->departamento ?? '');
    $municipio = old('municipio', $propiedad?->municipio ?? '');
    $localidad = old('localidad', $propiedad?->localidad ?? '');
    $latitud = old('latitud', $propiedad?->latitud ?? '');
    $longitud = old('longitud', $propiedad?->longitud ?? '');
    $geometria = old('geometria', $propiedad?->geometria !== null ? json_encode($propiedad->geometria) : '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-propiedades-form" novalidate>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif
    {{-- Alta rápida desde otro formulario (tarea "contratos-lotes", 16/9/2026):
         solo hace falta reenviarlo en el alta — en edición ya llega vía
         sesión (`PropiedadesController::edit()`), no como campo del form. --}}
    @if (! $esEdicion && ! empty($volverA))
        <input type="hidden" name="volver_a" value="{{ $volverA }}">
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.propiedades.titulo_editar') : __('comercial.propiedades.titulo_crear')"
        :subtitle="__('comercial.propiedades.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.propiedades.index') }}" variant="outline" icon="arrow_back">
                {{ __('comercial.propiedades.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('comercial.propiedades.seccion_datos')"
        :count="__('comercial.propiedades.campos_contador', ['cantidad' => 8])"
    >
        <x-atoms.select
            name="cliente_id"
            id="cliente_id"
            label="{{ __('comercial.propiedades.campo_cliente') }}"
            :options="$clientesDisponibles"
            :value="$clienteId"
            placeholder="{{ __('comercial.propiedades.campo_cliente_placeholder') }}"
            required
            error="{{ $errors->first('cliente_id') }}"
        />

        <x-atoms.input
            type="text"
            name="nombre"
            label="{{ __('comercial.propiedades.campo_nombre') }}"
            value="{{ $nombre }}"
            required
            error="{{ $errors->first('nombre') }}"
        />

        <x-atoms.input
            type="text"
            name="ubicacion"
            label="{{ __('comercial.propiedades.campo_ubicacion') }}"
            value="{{ $ubicacion }}"
            placeholder="{{ __('comercial.propiedades.campo_ubicacion_placeholder') }}"
            help="{{ __('comercial.propiedades.campo_ubicacion_ayuda') }}"
            error="{{ $errors->first('ubicacion') }}"
        />

        <x-atoms.input
            type="text"
            name="departamento"
            label="{{ __('comercial.propiedades.campo_departamento') }}"
            value="{{ $departamento }}"
            error="{{ $errors->first('departamento') }}"
        />

        <x-atoms.input
            type="text"
            name="municipio"
            label="{{ __('comercial.propiedades.campo_municipio') }}"
            value="{{ $municipio }}"
            error="{{ $errors->first('municipio') }}"
        />

        <x-atoms.input
            type="text"
            name="localidad"
            label="{{ __('comercial.propiedades.campo_localidad') }}"
            value="{{ $localidad }}"
            error="{{ $errors->first('localidad') }}"
        />

        <x-atoms.input
            type="number"
            name="latitud"
            label="{{ __('comercial.propiedades.campo_latitud') }}"
            value="{{ $latitud }}"
            step="0.000001"
            min="-90"
            max="90"
            error="{{ $errors->first('latitud') }}"
        />

        <x-atoms.input
            type="number"
            name="longitud"
            label="{{ __('comercial.propiedades.campo_longitud') }}"
            value="{{ $longitud }}"
            step="0.000001"
            min="-180"
            max="180"
            error="{{ $errors->first('longitud') }}"
        />

        <textarea
            name="geometria"
            class="form-control @error('geometria') is-invalid @enderror"
            placeholder="{{ __('comercial.propiedades.campo_geometria_placeholder') }}"
            rows="4"
            aria-label="{{ __('comercial.propiedades.campo_geometria') }}"
        >{{ $geometria }}</textarea>
        @error('geometria')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if (isset($help))
            <small class="form-text text-muted">{{ __('comercial.propiedades.campo_geometria_ayuda') }}</small>
        @endif
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.propiedades.estado_form')">
        <x-slot:actions>
            @if ($esEdicion && ! empty($volverA))
                <x-atoms.button href="{{ $volverA }}{{ str_contains($volverA, '?') ? '&' : '?' }}propiedad_id={{ $propiedad->id }}" variant="outline" icon="arrow_back">
                    {{ __('comercial.propiedades.volver_a_formulario_origen') }}
                </x-atoms.button>
            @endif
            <x-atoms.button href="{{ route('panel.propiedades.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
