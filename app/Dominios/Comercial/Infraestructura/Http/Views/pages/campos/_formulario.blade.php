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
    - $cultivosDisponibles / $campaniasDisponibles: SOLO en alta (HU-72,
      tarea 88) — `CamposController::create()` las pasa, `edit()` no. Por eso
      el bloque "Generar lotes" que las usa está adentro de
      `@if (! $esEdicion)`: en edición esas variables ni siquiera existen, y
      el generador no aplica (dibujar/renombrar un lote ya creado es la ficha
      existente del lote, no esta pantalla).

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
            'desnivel' => $lote->desnivel,
            'limpieza' => $lote->limpieza,
        ])->all()
        : [[]];
    $lotesIniciales = old('lotes', $lotesPorDefecto);
    $mapaClientePropiedad = $propiedadesDisponibles->pluck('cliente_id', 'id');
    $propiedadesOptions = $propiedadesDisponibles->mapWithKeys(fn ($propiedad) => [
        $propiedad->id => $propiedad->nombre,
    ]);
    // Solo llegan en alta (ver nota de arriba) — `?? collect()` evita el
    // acceso a variable indefinida cuando este partial se renderiza desde
    // edit.blade.php, aunque el bloque que las usa nunca se pinte ahí.
    $mapaClienteCampania = ($campaniasDisponibles ?? collect())->pluck('cliente_id', 'id');
    $campaniasOptions = ($campaniasDisponibles ?? collect())->mapWithKeys(fn ($campania) => [
        $campania->id => $campania->codigo,
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

    @if (! $esEdicion)
        {{--
            Generador de alta masiva (HU-72, tarea 88): rellena `lotes[]` con N
            filas provisorias ("Lote 1".."Lote N", JS en
            resources/js/pages/campos-form.js) y, si se elige cultivo, siembra
            todos los lotes del campo en la campaña elegida —`cultivo_id`/
            `campania_id` viajan a NIVEL FORMULARIO (no por lote), los procesa
            `CrearCampo` una sola vez para todo el campo. Renombrar cada lote o
            dibujar su perímetro es la ficha existente del lote, después de
            crear el campo — no algo de esta pantalla.
        --}}
        <x-molecules.form-section
            :title="__('comercial.campos.seccion_generador')"
            :count="__('comercial.campos.campos_contador', ['cantidad' => 4])"
            data-ag-generador
        >
            <x-atoms.input
                type="number"
                name="generador_cantidad"
                label="{{ __('comercial.campos.generador_cantidad') }}"
                min="1"
                step="1"
                data-ag-generador-cantidad
            />

            <x-atoms.input
                type="number"
                name="generador_hectareas"
                label="{{ __('comercial.campos.generador_hectareas') }}"
                min="0.01"
                step="0.01"
                data-ag-generador-hectareas
            />

            <x-atoms.select
                name="cultivo_id"
                id="cultivo_id"
                label="{{ __('comercial.campos.generador_cultivo') }}"
                :options="$cultivosDisponibles"
                :value="old('cultivo_id')"
                placeholder="{{ __('comercial.campos.generador_cultivo_placeholder') }}"
                error="{{ $errors->first('cultivo_id') }}"
                data-ag-generador-cultivo
            />

            <x-atoms.select
                name="campania_id"
                id="campania_id"
                label="{{ __('comercial.campos.generador_campania') }}"
                :options="$campaniasOptions"
                :value="old('campania_id')"
                placeholder="{{ __('comercial.campos.generador_campania_placeholder') }}"
                error="{{ $errors->first('campania_id') }}"
                data-ag-generador-campania
                data-mapa-cliente-campania="{{ $mapaClienteCampania->toJson() }}"
            />

            <div class="ag-form-section__field--full ag-campos-form__generador-pie">
                <x-atoms.button type="button" variant="outline" icon="auto_awesome" data-ag-generador-generar>
                    {{ __('comercial.campos.generador_generar') }}
                </x-atoms.button>
                <p class="ag-input__help">{{ __('comercial.campos.generador_ayuda') }}</p>
            </div>
        </x-molecules.form-section>
    @endif

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
