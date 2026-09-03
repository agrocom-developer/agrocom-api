{{--
    Partial: formulario de orden de aplicación, compartido por
    create.blade.php y edit.blade.php (HU-25, tarea 38) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `contratos/_formulario.blade.php`, sin sub-entidad repetible.

    Espera:
    - $orden (OrdenAplicacion|null): null en alta; el modelo en edición.
    - $contratosDisponibles / $lotesDisponibles / $contactosDisponibles
      (Collection<int, string>): id => etiqueta ya resuelta por el
      controlador (ver OrdenesController) — la vista no conoce los modelos
      de Comercial (ADR 0003 regla 3).

    `estado` NUNCA es un campo de este formulario: lo fija la máquina de
    estados al crear, y lo cambia `panel.ordenes.activar` (otra pantalla,
    otra responsabilidad — invariante 7). En edición, el formulario solo se
    ofrece con sentido para una orden `emitida` (ver docblock de
    `Aplicacion/ActualizarOrden`) — el link para llegar acá ya queda oculto
    para cualquier otro estado en `ordenes/index.blade.php`; si de todos
    modos se llega con una orden no editable, el submit vuelve con el error
    de dominio en `withErrors(['estado' => ...])`, nunca aplica el cambio.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo se omite a propósito, mismo criterio que
    drones/campos: ningún dato de solo lectura justifica hoy la columna
    lateral.
--}}
@php
    $esEdicion = $orden !== null;
    $accion = $esEdicion ? route('panel.ordenes.update', $orden) : route('panel.ordenes.store');
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $orden?->{$campo} ?? $porDefecto);
    $contratoId = old('contrato_id', $orden?->contrato_id ?? '');
    $loteId = old('lote_id', $orden?->lote_id ?? '');
    $contactoId = old('emitida_por_contacto_id', $orden?->emitida_por_contacto_id ?? '');
    $fechaEmision = old('fecha_emision', $orden?->fecha_emision?->toDateString() ?? '');
@endphp

<form method="POST" action="{{ $accion }}" class="ag-ordenes-form" novalidate data-ag-ordenes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('operaciones.ordenes.titulo_editar') : __('operaciones.ordenes.titulo_crear')"
        :subtitle="__('operaciones.ordenes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.ordenes.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_datos')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 7])"
    >
        <div class="ag-input">
            <label for="contrato_id" class="ag-input__label">
                {{ __('operaciones.ordenes.campo_contrato') }}
                <span class="ag-input__required" aria-hidden="true">*</span>
            </label>
            <div class="ag-input__control {{ $errors->has('contrato_id') ? 'ag-input__control--error' : '' }}">
                <select name="contrato_id" id="contrato_id" class="ag-input__field" required>
                    <option value="">{{ __('operaciones.ordenes.campo_contrato_placeholder') }}</option>
                    @foreach ($contratosDisponibles as $id => $etiqueta)
                        <option value="{{ $id }}" @selected((string) $contratoId === (string) $id)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('contrato_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('contrato_id') }}</p>
            @endif
        </div>

        <div class="ag-input">
            <label for="lote_id" class="ag-input__label">
                {{ __('operaciones.ordenes.campo_lote') }}
                <span class="ag-input__required" aria-hidden="true">*</span>
            </label>
            <div class="ag-input__control {{ $errors->has('lote_id') ? 'ag-input__control--error' : '' }}">
                <select name="lote_id" id="lote_id" class="ag-input__field" required>
                    <option value="">{{ __('operaciones.ordenes.campo_lote_placeholder') }}</option>
                    @foreach ($lotesDisponibles as $id => $etiqueta)
                        <option value="{{ $id }}" @selected((string) $loteId === (string) $id)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('lote_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('lote_id') }}</p>
            @endif
        </div>

        <x-atoms.input
            type="number"
            name="nro_aplicacion"
            label="{{ __('operaciones.ordenes.campo_nro_aplicacion') }}"
            value="{{ $valor('nro_aplicacion') }}"
            min="1"
            step="1"
            required
            error="{{ $errors->first('nro_aplicacion') }}"
        />

        <x-atoms.input
            type="number"
            name="litros_ha"
            label="{{ __('operaciones.ordenes.campo_litros_ha') }}"
            value="{{ $valor('litros_ha') }}"
            min="0.01"
            step="0.01"
            required
            error="{{ $errors->first('litros_ha') }}"
        />

        <x-atoms.input
            type="date"
            name="fecha_emision"
            label="{{ __('operaciones.ordenes.campo_fecha_emision') }}"
            value="{{ $fechaEmision }}"
            required
            error="{{ $errors->first('fecha_emision') }}"
        />

        <div class="ag-input">
            <label for="emitida_por_contacto_id" class="ag-input__label">{{ __('operaciones.ordenes.campo_contacto') }}</label>
            <div class="ag-input__control {{ $errors->has('emitida_por_contacto_id') ? 'ag-input__control--error' : '' }}">
                <select name="emitida_por_contacto_id" id="emitida_por_contacto_id" class="ag-input__field">
                    <option value="">{{ __('operaciones.ordenes.campo_contacto_placeholder') }}</option>
                    @foreach ($contactosDisponibles as $id => $etiqueta)
                        <option value="{{ $id }}" @selected((string) $contactoId === (string) $id)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('emitida_por_contacto_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('emitida_por_contacto_id') }}</p>
            @endif
        </div>

        <div class="ag-form-section__field--full">
            <x-atoms.input
                type="text"
                name="observaciones"
                label="{{ __('operaciones.ordenes.campo_observaciones') }}"
                value="{{ $valor('observaciones') }}"
                error="{{ $errors->first('observaciones') }}"
            />
        </div>
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_limites')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 5])"
    >
        <div class="ag-form-section__field--full ag-ordenes-form__ayuda">
            {{ __('operaciones.ordenes.seccion_limites_ayuda') }}
        </div>

        <x-atoms.input
            type="number"
            name="humedad_min_pct"
            label="{{ __('operaciones.ordenes.campo_humedad_min_pct') }}"
            value="{{ $valor('humedad_min_pct') }}"
            min="0"
            max="100"
            step="0.01"
            error="{{ $errors->first('humedad_min_pct') }}"
        />

        <x-atoms.input
            type="number"
            name="humedad_max_pct"
            label="{{ __('operaciones.ordenes.campo_humedad_max_pct') }}"
            value="{{ $valor('humedad_max_pct') }}"
            min="0"
            max="100"
            step="0.01"
            error="{{ $errors->first('humedad_max_pct') }}"
        />

        <x-atoms.input
            type="number"
            name="viento_max_kmh"
            label="{{ __('operaciones.ordenes.campo_viento_max_kmh') }}"
            value="{{ $valor('viento_max_kmh') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('viento_max_kmh') }}"
        />

        <x-atoms.input
            type="number"
            name="temperatura_max_c"
            label="{{ __('operaciones.ordenes.campo_temperatura_max_c') }}"
            value="{{ $valor('temperatura_max_c') }}"
            step="0.01"
            error="{{ $errors->first('temperatura_max_c') }}"
        />

        <x-atoms.input
            type="number"
            name="velocidad_max_kmh"
            label="{{ __('operaciones.ordenes.campo_velocidad_max_kmh') }}"
            value="{{ $valor('velocidad_max_kmh') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('velocidad_max_kmh') }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_vuelo')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 3])"
    >
        <x-atoms.input
            type="number"
            name="altura_vuelo_m"
            label="{{ __('operaciones.ordenes.campo_altura_vuelo_m') }}"
            value="{{ $valor('altura_vuelo_m') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('altura_vuelo_m') }}"
        />

        <x-atoms.input
            type="number"
            name="velocidad_vuelo_kmh"
            label="{{ __('operaciones.ordenes.campo_velocidad_vuelo_kmh') }}"
            value="{{ $valor('velocidad_vuelo_kmh') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('velocidad_vuelo_kmh') }}"
        />

        <x-atoms.input
            type="number"
            name="ancho_pasada_m"
            label="{{ __('operaciones.ordenes.campo_ancho_pasada_m') }}"
            value="{{ $valor('ancho_pasada_m') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('ancho_pasada_m') }}"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('operaciones.ordenes.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.ordenes.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
