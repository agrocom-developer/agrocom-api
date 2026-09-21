{{--
    Partial: sección "Calda" del alta de Orden de Trabajo (19/9/2026, pedido
    del dueño). Es la SEGUNDA sección del formulario, justo después de la orden
    de aplicación, porque todo lo que pide depende de ella.

    - Qué lleva la calda: cuatro casillas simples —Glifosato, 2,4-D, Agua,
      Urea—, sin cantidad ni unidad (una primera versión las pedía y el
      dueño la descartó por confusa). Es la constancia de lo que se aplicó, no
      una formulación: eso lo decide el agrónomo del cliente. Viajan como
      `parametros[calda_productos][]`, valores de `Dominio\ProductoCalda`.
    - Según el tipo de insumo de la orden de aplicación:
      · líquido: Ph del agua, Ph de la calda y litros por hectárea.
      · sólido: kilos por hectárea.

    Sin orden elegida no se sabe el tipo de insumo: la sección se dibuja igual
    y muestra el componente de vacío (guía de pantallas §6.3.5).

    Espera:
    - $esLiquido (?bool): `null` si todavía no hay orden elegida.
    - $parametrosAntiguos (array): `old('parametros')`.
    - $litrosHaOrden (?string): litros por hectárea de la orden de
      aplicación, como valor inicial.
--}}
@php
    $productosCalda = collect(\App\Dominios\Operaciones\Dominio\ProductoCalda::cases())
        ->mapWithKeys(fn ($producto): array => [
            $producto->value => __('operaciones.ordenes_trabajo.calda_productos.'.$producto->value),
        ])
        ->all();
@endphp
<x-molecules.form-section
    :title="__('operaciones.asignacion_equipos.seccion_calda')"
    :count="$esLiquido === null
        ? __('operaciones.ordenes_trabajo.calda_contador_vacio')
        : __('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => $esLiquido ? 4 : 2])"
>
    @if ($esLiquido === null)
        <div class="ag-form-section__field--full">
            <x-molecules.empty-state
                icon="water_drop"
                :title="__('operaciones.ordenes_trabajo.calda_vacio_titulo')"
                :detail="__('operaciones.ordenes_trabajo.calda_vacio_detalle')"
            />
        </div>
    @else
        <x-atoms.checkbox-group
            name="parametros[calda_productos]"
            id="calda-productos"
            class="ag-form-section__field--full ag-ordenes-trabajo-form__calda"
            :label="__('operaciones.ordenes_trabajo.campo_calda_productos')"
            :options="$productosCalda"
            :value="$parametrosAntiguos['calda_productos'] ?? []"
            :help="__('operaciones.ordenes_trabajo.calda_ayuda')"
            :error="$errors->first('parametros.calda_productos') ?: $errors->first('parametros.calda_productos.*')"
        />

        @if ($esLiquido)
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
    @endif
</x-molecules.form-section>
