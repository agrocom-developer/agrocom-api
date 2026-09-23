{{--
    Partial: indicaciones compartidas de la tanda —calda, límites climáticos y
    parámetros de vuelo— (tarea 127). Compartido por `create.blade.php` y
    `edit.blade.php`: las tres secciones se cargan una sola vez y valen para
    todos los trabajos de la orden, así que el alta y la edición piden
    exactamente lo mismo con las mismas reglas (`Concerns\ValidaIndicacionesOrdenTrabajo`).

    Espera:
    - $esLiquido (?bool): `null` si todavía no hay orden elegida (solo pasa en
      el alta, antes de elegir la orden de aplicación).
    - $parametrosAntiguos (array): `old('parametros')` en el alta; los valores
      actuales de la cabecera en la edición.
    - $litrosHaOrden (?string): litros por hectárea de la orden de aplicación,
      como valor inicial (solo aporta algo en el alta; en edición ya viene
      dentro de `$parametrosAntiguos`).
--}}
@include('operaciones::pages.ordenes-trabajo._calda', [
    'esLiquido' => $esLiquido,
    'parametrosAntiguos' => $parametrosAntiguos,
    'litrosHaOrden' => $litrosHaOrden ?? null,
])

<x-molecules.form-section
    :title="__('operaciones.ordenes_trabajo.seccion_clima')"
    :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => 4])"
>
    <p class="ag-form-section__field--full ag-ordenes-trabajo-form__ayuda">
        {{ __('operaciones.ordenes_trabajo.seccion_parametros_ayuda') }}
    </p>

    <x-atoms.input
        type="number"
        name="parametros[humedad_min_pct]"
        id="parametros-humedad-min"
        :label="__('operaciones.asignacion_equipos.campo_humedad_min_pct')"
        :value="$parametrosAntiguos['humedad_min_pct'] ?? ''"
        min="0"
        max="100"
        step="0.01"
        :error="$errors->first('parametros.humedad_min_pct')"
    />

    <x-atoms.input
        type="number"
        name="parametros[humedad_max_pct]"
        id="parametros-humedad-max"
        :label="__('operaciones.asignacion_equipos.campo_humedad_max_pct')"
        :value="$parametrosAntiguos['humedad_max_pct'] ?? ''"
        min="0"
        max="100"
        step="0.01"
        :error="$errors->first('parametros.humedad_max_pct')"
    />

    <x-atoms.input
        type="number"
        name="parametros[viento_max_kmh]"
        id="parametros-viento-max"
        :label="__('operaciones.asignacion_equipos.campo_viento_max_kmh')"
        :value="$parametrosAntiguos['viento_max_kmh'] ?? ''"
        min="0.01"
        step="0.01"
        :error="$errors->first('parametros.viento_max_kmh')"
    />

    <x-atoms.input
        type="number"
        name="parametros[temperatura_max_c]"
        id="parametros-temperatura-max"
        :label="__('operaciones.asignacion_equipos.campo_temperatura_max_c')"
        :value="$parametrosAntiguos['temperatura_max_c'] ?? ''"
        step="0.01"
        :error="$errors->first('parametros.temperatura_max_c')"
    />
</x-molecules.form-section>

<x-molecules.form-section
    :title="__('operaciones.ordenes_trabajo.seccion_vuelo')"
    :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => 3])"
>
    <x-atoms.input
        type="number"
        name="parametros[altura_vuelo_m]"
        id="parametros-altura-vuelo"
        :label="__('operaciones.asignacion_equipos.campo_altura_vuelo_m')"
        :value="$parametrosAntiguos['altura_vuelo_m'] ?? ''"
        min="0.01"
        step="0.01"
        :error="$errors->first('parametros.altura_vuelo_m')"
    />

    <x-atoms.input
        type="number"
        name="parametros[velocidad_vuelo_kmh]"
        id="parametros-velocidad-vuelo"
        :label="__('operaciones.asignacion_equipos.campo_velocidad_vuelo_kmh')"
        :value="$parametrosAntiguos['velocidad_vuelo_kmh'] ?? ''"
        min="0.01"
        step="0.01"
        :error="$errors->first('parametros.velocidad_vuelo_kmh')"
    />

    <x-atoms.input
        type="number"
        name="parametros[ancho_pasada_m]"
        id="parametros-ancho-pasada"
        :label="__('operaciones.asignacion_equipos.campo_ancho_pasada_m')"
        :value="$parametrosAntiguos['ancho_pasada_m'] ?? ''"
        min="0.01"
        step="0.01"
        :error="$errors->first('parametros.ancho_pasada_m')"
    />
</x-molecules.form-section>
