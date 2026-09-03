{{--
    Partial: formulario de movimiento de stock (HU-36, tarea 52) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Un único
    formulario para los cuatro tipos (compra/salida/ajuste/traslado): los
    campos que no aplican a todos (`base_destino_id`, `sentido`,
    `costo_unitario`, `motivo`) se muestran u ocultan según el `tipo`
    elegido, vía `resources/js/pages/stock-movimiento-form.js`
    (`data-ag-movimiento-campo`, lista separada por espacios de los tipos que
    lo necesitan). Es presentación, no validación — el servidor revalida las
    obligatoriedades condicionales en `RegistrarMovimientoRequest`, un POST
    manual podría enviar cualquier combinación igual.

    Espera:
    - $repuestosDisponibles (Collection<int, string>): id => "código —
      descripción" (ver StockController::repuestosDisponibles()).
    - $basesDisponibles (Collection<int, string>): id => nombre, bases vivas.
    - $tipos (list<TipoMovimientoInventario>): opciones del select de tipo.
    - $sentidos (list<SentidoAjusteInventario>): opciones del select de
      sentido (solo relevante para `ajuste`).

    Sin edición: un movimiento, una vez registrado, es un asiento inmutable
    (ver RegistrarMovimientoStock) — este formulario solo tiene alta, nunca
    `_formulario.blade.php` compartido con un `edit.blade.php` que no existe.

    Tras un error de validación (incluida `StockInsuficiente`, traducida a
    422 por el controlador), `old()` pisa los valores enviados — así el
    usuario no vuelve a tipear todo el formulario.
--}}
@php
    $tipo = old('tipo', '');
    $repuestoId = old('repuesto_id', '');
    $baseId = old('base_id', '');
    $baseDestinoId = old('base_destino_id', '');
    $cantidad = old('cantidad', '');
    $sentido = old('sentido', '');
    $costoUnitario = old('costo_unitario', '');
    $motivo = old('motivo', '');
@endphp

<form method="POST" action="{{ route('panel.stock.movimientos.store') }}" class="ag-stock-form" novalidate data-ag-movimiento-form>
    @csrf

    <x-organisms.page-header
        :title="__('inventario.stock.titulo_crear')"
        :subtitle="__('inventario.stock.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.stock.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('inventario.stock.seccion_datos')"
        :count="__('inventario.stock.campos_contador', ['cantidad' => 8])"
    >
        <div class="ag-input">
            <label for="tipo" class="ag-input__label">
                {{ __('inventario.stock.campo_tipo') }}
                <span class="ag-input__required" aria-hidden="true">*</span>
            </label>
            <div class="ag-input__control {{ $errors->has('tipo') ? 'ag-input__control--error' : '' }}">
                <select name="tipo" id="tipo" class="ag-input__field" required data-ag-movimiento-tipo>
                    <option value="" disabled @selected($tipo === '')>{{ __('inventario.stock.campo_tipo') }}</option>
                    @foreach ($tipos as $opcionTipo)
                        <option value="{{ $opcionTipo->value }}" @selected($tipo === $opcionTipo->value)>
                            {{ __('inventario.tipo_movimiento.'.$opcionTipo->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('tipo'))
                <p class="ag-input__error" role="alert">{{ $errors->first('tipo') }}</p>
            @endif
        </div>

        <div class="ag-input">
            <label for="repuesto_id" class="ag-input__label">
                {{ __('inventario.stock.campo_repuesto') }}
                <span class="ag-input__required" aria-hidden="true">*</span>
            </label>
            <div class="ag-input__control {{ $errors->has('repuesto_id') ? 'ag-input__control--error' : '' }}">
                <select name="repuesto_id" id="repuesto_id" class="ag-input__field" required>
                    <option value="" disabled @selected($repuestoId === '')>{{ __('inventario.stock.campo_repuesto_placeholder') }}</option>
                    @foreach ($repuestosDisponibles as $id => $etiqueta)
                        <option value="{{ $id }}" @selected((string) $repuestoId === (string) $id)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('repuesto_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('repuesto_id') }}</p>
            @endif
        </div>

        <div class="ag-input">
            <label for="base_id" class="ag-input__label">
                {{ __('inventario.stock.campo_base') }}
                <span class="ag-input__required" aria-hidden="true">*</span>
            </label>
            <div class="ag-input__control {{ $errors->has('base_id') ? 'ag-input__control--error' : '' }}">
                <select name="base_id" id="base_id" class="ag-input__field" required>
                    <option value="" disabled @selected($baseId === '')>{{ __('inventario.stock.campo_base_placeholder') }}</option>
                    @foreach ($basesDisponibles as $id => $nombreBase)
                        <option value="{{ $id }}" @selected((string) $baseId === (string) $id)>{{ $nombreBase }}</option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('base_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('base_id') }}</p>
            @endif
        </div>

        <div class="ag-input" data-ag-movimiento-campo="traslado">
            <label for="base_destino_id" class="ag-input__label">{{ __('inventario.stock.campo_base_destino') }}</label>
            <div class="ag-input__control {{ $errors->has('base_destino_id') ? 'ag-input__control--error' : '' }}">
                <select name="base_destino_id" id="base_destino_id" class="ag-input__field">
                    <option value="" @selected($baseDestinoId === '')>{{ __('inventario.stock.campo_base_destino_placeholder') }}</option>
                    @foreach ($basesDisponibles as $id => $nombreBase)
                        <option value="{{ $id }}" @selected((string) $baseDestinoId === (string) $id)>{{ $nombreBase }}</option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('base_destino_id'))
                <p class="ag-input__error" role="alert">{{ $errors->first('base_destino_id') }}</p>
            @endif
        </div>

        <x-atoms.input
            type="number"
            name="cantidad"
            label="{{ __('inventario.stock.campo_cantidad') }}"
            value="{{ $cantidad }}"
            min="0.01"
            step="0.01"
            required
            error="{{ $errors->first('cantidad') }}"
        />

        <div class="ag-input" data-ag-movimiento-campo="ajuste">
            <label for="sentido" class="ag-input__label">{{ __('inventario.stock.campo_sentido') }}</label>
            <div class="ag-input__control {{ $errors->has('sentido') ? 'ag-input__control--error' : '' }}">
                <select name="sentido" id="sentido" class="ag-input__field">
                    <option value="" @selected($sentido === '')>{{ __('inventario.stock.campo_sentido') }}</option>
                    @foreach ($sentidos as $opcionSentido)
                        <option value="{{ $opcionSentido->value }}" @selected($sentido === $opcionSentido->value)>
                            {{ __('inventario.sentido_ajuste.'.$opcionSentido->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($errors->has('sentido'))
                <p class="ag-input__error" role="alert">{{ $errors->first('sentido') }}</p>
            @endif
        </div>

        <div data-ag-movimiento-campo="compra">
            <x-atoms.input
                type="number"
                name="costo_unitario"
                label="{{ __('inventario.stock.campo_costo_unitario') }}"
                value="{{ $costoUnitario }}"
                min="0"
                step="0.01"
                error="{{ $errors->first('costo_unitario') }}"
            />
        </div>

        <div data-ag-movimiento-campo="ajuste traslado">
            <x-atoms.input
                type="text"
                name="motivo"
                label="{{ __('inventario.stock.campo_motivo') }}"
                value="{{ $motivo }}"
                placeholder="{{ __('inventario.stock.campo_motivo_placeholder') }}"
                error="{{ $errors->first('motivo') }}"
            />
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('inventario.stock.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.stock.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
