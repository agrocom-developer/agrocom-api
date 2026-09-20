{{--
    Partial: sección "Calda" del alta de Orden de Trabajo (19/9/2026, pedido
    del dueño). Reemplaza la lista libre de productos por cuatro casillas
    fijas —Glifosato, 2,4-D, Urea y Agua—: marcar una habilita su cantidad.
    Es un REGISTRO de lo que se aplicó, no una formulación: qué lleva la calda
    lo decide el agrónomo del cliente.

    Cada casilla marcada viaja con la misma forma que ya esperaba el servidor
    (`parametros[calda][<clave>]{producto, cantidad, unidad}`), así que
    `ValidaTandaDeTrabajo` y `Mezclas\Contratos\EscrituraMezclas` no cambian.
    Una casilla sin marcar no envía nada: sus campos quedan `disabled`
    (`ordenes-trabajo-form.js`) y `CrearOrdenTrabajoRequest` descarta igual
    cualquier renglón que llegue sin producto.

    Según el tipo de insumo de la orden de aplicación:
    - líquido: Ph del agua, Ph de la calda y litros por hectárea.
    - sólido: kilos por hectárea.

    Sin orden elegida el tipo de insumo todavía no se sabe: las casillas se
    ofrecen igual (no dependen de la orden) y, en lugar de los campos por
    tipo, va una línea que dice qué se agrega al elegirla.

    Espera:
    - $esLiquido (?bool): `null` si todavía no hay orden elegida.
    - $parametrosAntiguos (array): `old('parametros')`.
    - $litrosHaOrden (?string): litros por hectárea de la orden de
      aplicación, como valor inicial.
--}}
@php
    // clave => unidad inicial. El agua siempre va en litros: sin selector.
    $productosCalda = ['glifosato' => 'l', 'dos_cuatro_d' => 'l', 'urea' => 'kg', 'agua' => 'l'];
    $caldaAntigua = $parametrosAntiguos['calda'] ?? [];
    $unidades = [
        'l' => __('operaciones.asignacion_equipos.unidad_l'),
        'ml' => __('operaciones.asignacion_equipos.unidad_ml'),
        'kg' => __('operaciones.asignacion_equipos.unidad_kg'),
        'g' => __('operaciones.asignacion_equipos.unidad_g'),
    ];
@endphp
<x-molecules.form-section
    :title="__('operaciones.asignacion_equipos.seccion_calda')"
    :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => match ($esLiquido) { true => 7, false => 5, null => 4 }])"
>
    <p class="ag-form-section__field--full ag-ordenes-trabajo-form__ayuda">
        {{ __('operaciones.ordenes_trabajo.calda_ayuda') }}
    </p>

    <div class="ag-form-section__field--full ag-ordenes-trabajo-form__calda">
        @foreach ($productosCalda as $clave => $unidadInicial)
            @php
                $marcado = isset($caldaAntigua[$clave]['producto']);
                $nombreProducto = __('operaciones.ordenes_trabajo.calda_productos.'.$clave);
            @endphp
            <div class="ag-ordenes-trabajo-form__calda-fila" data-ag-calda-fila>
                <x-atoms.checkbox
                    name="parametros[calda][{{ $clave }}][producto]"
                    id="calda-{{ $clave }}-producto"
                    :label="$nombreProducto"
                    :value="$nombreProducto"
                    :checked="$marcado"
                    data-ag-calda-casilla
                />

                <x-atoms.input
                    type="number"
                    name="parametros[calda][{{ $clave }}][cantidad]"
                    id="calda-{{ $clave }}-cantidad"
                    :label="$clave === 'agua' ? __('operaciones.ordenes_trabajo.calda_litros') : __('operaciones.asignacion_equipos.campo_calda_cantidad')"
                    :value="$caldaAntigua[$clave]['cantidad'] ?? ''"
                    min="0.01"
                    step="0.01"
                    :disabled="! $marcado"
                    :error="$errors->first('parametros.calda.'.$clave.'.cantidad')"
                    data-ag-calda-campo
                />

                @if ($clave === 'agua')
                    <input
                        type="hidden"
                        name="parametros[calda][{{ $clave }}][unidad]"
                        value="l"
                        @disabled(! $marcado)
                        data-ag-calda-campo
                    >
                @else
                    <x-atoms.select
                        name="parametros[calda][{{ $clave }}][unidad]"
                        id="calda-{{ $clave }}-unidad"
                        :label="__('operaciones.asignacion_equipos.campo_calda_unidad')"
                        :options="$unidades"
                        :value="$caldaAntigua[$clave]['unidad'] ?? $unidadInicial"
                        :searchable="false"
                        :error="$errors->first('parametros.calda.'.$clave.'.unidad')"
                        data-ag-calda-campo
                    />
                @endif
            </div>
        @endforeach
    </div>

    @if ($esLiquido === null)
        <p class="ag-form-section__field--full ag-ordenes-trabajo-form__ayuda">
            {{ __('operaciones.ordenes_trabajo.calda_sin_orden') }}
        </p>
    @elseif ($esLiquido)
        <x-atoms.input
            type="number"
            name="parametros[ph_agua]"
            id="parametros-ph-agua"
            :label="__('operaciones.asignacion_equipos.campo_ph_agua')"
            :value="$parametrosAntiguos['ph_agua'] ?? ''"
            min="0"
            max="14"
            step="0.01"
            :error="$errors->first('parametros.ph_agua')"
        />

        <x-atoms.input
            type="number"
            name="parametros[ph_calda]"
            id="parametros-ph-calda"
            :label="__('operaciones.asignacion_equipos.campo_ph_calda')"
            :value="$parametrosAntiguos['ph_calda'] ?? ''"
            min="0"
            max="14"
            step="0.01"
            :error="$errors->first('parametros.ph_calda')"
        />

        <x-atoms.input
            type="number"
            name="parametros[litros_ha]"
            id="parametros-litros-ha"
            :label="__('operaciones.ordenes_trabajo.campo_litros_ha')"
            :value="$parametrosAntiguos['litros_ha'] ?? ($litrosHaOrden ?? '')"
            min="0.01"
            step="0.01"
            :error="$errors->first('parametros.litros_ha')"
        />
    @else
        <x-atoms.input
            type="number"
            name="parametros[kilos_ha]"
            id="parametros-kilos-ha"
            :label="__('operaciones.ordenes_trabajo.campo_kilos_ha')"
            :value="$parametrosAntiguos['kilos_ha'] ?? ''"
            min="0.01"
            step="0.01"
            :error="$errors->first('parametros.kilos_ha')"
        />
    @endif
</x-molecules.form-section>
