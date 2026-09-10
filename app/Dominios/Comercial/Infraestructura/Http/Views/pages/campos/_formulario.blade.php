{{--
    Partial: formulario de campo, compartido por create.blade.php y
    edit.blade.php (HU-24, tarea 35; actualizado ADR 0018) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `clientes/_formulario.blade.php` (tarea 33): las dos páginas arman el
    MISMO formulario; lo único que cambia es contra qué URL/método postea y
    los valores iniciales.

    Espera:
    - $campo (Campo|null): null en alta; el modelo, con `lotes` y `propiedad`
      ya cargadas, en edición.
    - $clientesDisponibles (Collection<int, string>): id => razón social,
      clientes activos (ver CamposController::clientesActivos()) — la vista
      no conoce el modelo Cliente.
    - $propiedadesDisponibles (Collection<int, Propiedad>): id => Propiedad
      con `cliente_id` y `nombre` — para el cascade cliente → propiedad
      (ADR 0018: campo ahora cuelga de propiedad, no directo de cliente).

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo (summary-card/progress-meter) se omite a
    propósito, mismo criterio que clientes: ningún dato de solo lectura
    justifica hoy la columna lateral.
--}}
@php
    $esEdicion = $campo !== null;
    $accion = $esEdicion ? route('panel.campos.update', $campo) : route('panel.campos.store');
    $propiedadId = old('propiedad_id', $campo?->propiedad_id ?? '');
    $clienteId = old('cliente_id', $campo?->propiedad?->cliente_id ?? '');
    $nombre = old('nombre', $campo?->nombre ?? '');
    $lotesPorDefecto = $esEdicion
        ? $campo->lotes->map(fn ($lote) => [
            'id' => $lote->id,
            'codigo' => $lote->codigo,
            'hectareas' => $lote->hectareas,
            'geometria' => $lote->geometria !== null ? json_encode($lote->geometria) : '',
            'restricciones' => $lote->restricciones,
        ])->all()
        : [[]];
    $lotesIniciales = old('lotes', $lotesPorDefecto);
    $mapaClientePropiedad = $propiedadesDisponibles->pluck('cliente_id', 'id');
    $propiedadesOptions = $propiedadesDisponibles->mapWithKeys(fn ($propiedad) => [
        $propiedad->id => $propiedad->nombre,
    ]);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-campos-form" novalidate data-ag-campos-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.campos.titulo_editar') : __('comercial.campos.titulo_crear')"
        :subtitle="__('comercial.campos.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.campos.index') }}" variant="outline" icon="arrow_back">
                {{ __('comercial.campos.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('comercial.campos.seccion_datos')"
        :count="__('comercial.campos.campos_contador', ['cantidad' => 2])"
    >
        <x-atoms.select
            name="cliente_id"
            id="cliente_id"
            label="{{ __('comercial.campos.campo_cliente') }}"
            :options="$clientesDisponibles"
            :value="$clienteId"
            placeholder="{{ __('comercial.campos.campo_cliente_placeholder') }}"
            required
            data-ag-campo-cliente
        />

        <x-atoms.select
            name="propiedad_id"
            id="propiedad_id"
            label="{{ __('comercial.campos.campo_propiedad') }}"
            :options="$propiedadesOptions"
            :value="$propiedadId"
            placeholder="{{ __('comercial.campos.campo_propiedad_placeholder') }}"
            required
            error="{{ $errors->first('propiedad_id') }}"
            data-ag-campo-propiedad
            data-mapa-cliente-propiedad="{{ $mapaClientePropiedad->toJson() }}"
        />

        <x-atoms.input
            type="text"
            name="nombre"
            label="{{ __('comercial.campos.campo_nombre') }}"
            value="{{ $nombre }}"
            required
            error="{{ $errors->first('nombre') }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section :title="__('comercial.campos.seccion_lotes')" class="ag-campos-form__lotes-seccion">
        <div class="ag-form-section__field--full ag-campos-form__lotes" data-ag-lotes>
            @if ($errors->has('lotes'))
                <p class="ag-input__error" role="alert">{{ $errors->first('lotes') }}</p>
            @endif

            <div data-ag-lotes-lista>
                @foreach ($lotesIniciales as $indice => $lote)
                    @include('comercial::pages.campos._lote-fila', ['indice' => $indice, 'lote' => $lote])
                @endforeach
            </div>

            <x-atoms.button type="button" variant="outline" icon="add" data-ag-lotes-agregar>
                {{ __('comercial.campos.lote_agregar') }}
            </x-atoms.button>

            {{-- Plantilla clonable (JS vanilla, resources/js/pages/campos-form.js):
                 el índice literal se reemplaza por el próximo número al clonar. Un
                 <template> nunca se renderiza ni se envía con el form. --}}
            <template data-ag-lote-template>
                @include('comercial::pages.campos._lote-fila', ['indice' => '__INDICE__', 'lote' => []])
            </template>
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.campos.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.campos.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
