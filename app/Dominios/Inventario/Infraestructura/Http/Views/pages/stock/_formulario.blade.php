{{--
    Partial: formulario de movimiento de stock (HU-36, tarea 52) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Homogeneizado en la
    tarea 117 con `form-layout` (sin aside: ver más abajo). Un único
    formulario para los cuatro tipos (compra/salida/ajuste/traslado): los
    campos que no aplican a todos (`base_destino_id`, `sentido`,
    `costo_unitario`, `motivo`) se muestran u ocultan según el `tipo`
    elegido, vía `resources/js/pages/stock-movimiento-form.js`
    (`data-ag-movimiento-campo`, lista separada por espacios de los tipos que
    lo necesitan). Es presentación, no validación — el servidor revalida las
    obligatoriedades condicionales en `RegistrarMovimientoRequest`, un POST
    manual podría enviar cualquier combinación igual. El asterisco de
    «obligatorio» de cada campo condicional solo se ve mientras el campo se ve,
    que es justo cuando el servidor lo exige.

    Espera:
    - $repuestosDisponibles (Collection<int, string>): id => "código —
      descripción" (ver StockController::repuestosDisponibles()).
    - $basesDisponibles (array<int, string>): id => nombre, bases vivas, que el
      controlador toma del contrato de Personal.
    - $tipos (list<TipoMovimientoInventario>): opciones del select de tipo.
    - $sentidos (list<SentidoAjusteInventario>): opciones del select de
      sentido (solo relevante para `ajuste`).
    - $repuestoIdInicial (int|null): repuesto ya elegido, cuando se llega desde
      el resumen de la ficha de un repuesto (`?repuesto_id=`).

    Sin edición y sin aside: un movimiento, una vez registrado, es un asiento
    inmutable (ver RegistrarMovimientoStock) — este formulario solo tiene alta,
    nunca `_formulario.blade.php` compartido con un `edit.blade.php` que no
    existe, y un registro que todavía no existe no tiene nada relacionado que
    resumir (guía §6.3.1). Tras guardar se vuelve al listado con su aviso.

    Tras un error de validación (incluida `StockInsuficiente`, traducida a
    422 por el controlador), `old()` pisa los valores enviados — así el
    usuario no vuelve a tipear todo el formulario.
--}}
@php
    $tipo = old('tipo', '');
    $repuestoId = old('repuesto_id', $repuestoIdInicial ?? '');
    $baseId = old('base_id', '');
    $baseDestinoId = old('base_destino_id', '');
    $cantidad = old('cantidad', '');
    $sentido = old('sentido', '');
    $costoUnitario = old('costo_unitario', '');
    $motivo = old('motivo', '');

    $opcionesTipo = collect($tipos)->mapWithKeys(fn ($t) => [$t->value => __('inventario.tipo_movimiento.'.$t->value)]);
    $opcionesSentido = collect($sentidos)->mapWithKeys(fn ($s) => [$s->value => __('inventario.sentido_ajuste.'.$s->value)]);
@endphp

<form method="POST" action="{{ route('panel.stock.movimientos.store') }}" class="ag-stock-form" novalidate data-ag-movimiento-form>
    @csrf

    <x-organisms.page-header
        :title="__('inventario.stock.titulo_crear')"
        :subtitle="__('inventario.stock.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.stock.index')" :label="__('inventario.stock.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            :title="__('inventario.stock.seccion_datos')"
            :count="__('inventario.stock.campos_contador', ['cantidad' => 8])"
        >
            <x-atoms.select
                name="tipo"
                id="tipo"
                :label="__('inventario.stock.campo_tipo')"
                :options="$opcionesTipo"
                :value="$tipo"
                :placeholder="__('inventario.stock.campo_tipo')"
                :error="$errors->first('tipo')"
                required
                data-ag-movimiento-tipo
            />

            <x-atoms.select
                name="repuesto_id"
                id="repuesto_id"
                :label="__('inventario.stock.campo_repuesto')"
                :options="$repuestosDisponibles"
                :value="$repuestoId"
                :placeholder="__('inventario.stock.campo_repuesto_placeholder')"
                :error="$errors->first('repuesto_id')"
                required
            />

            <x-atoms.select
                name="base_id"
                id="base_id"
                :label="__('inventario.stock.campo_base')"
                :options="$basesDisponibles"
                :value="$baseId"
                :placeholder="__('inventario.stock.campo_base_placeholder')"
                :help="__('inventario.stock.campo_base_ayuda')"
                :error="$errors->first('base_id')"
                required
            />

            <div data-ag-movimiento-campo="traslado">
                <x-atoms.select
                    name="base_destino_id"
                    id="base_destino_id"
                    :label="__('inventario.stock.campo_base_destino')"
                    :options="$basesDisponibles"
                    :value="$baseDestinoId"
                    :placeholder="__('inventario.stock.campo_base_destino_placeholder')"
                    :error="$errors->first('base_destino_id')"
                    required
                />
            </div>

            <x-atoms.input
                type="number"
                name="cantidad"
                :label="__('inventario.stock.campo_cantidad')"
                :value="$cantidad"
                :help="__('inventario.stock.campo_cantidad_ayuda')"
                min="0.01"
                step="0.01"
                required
                :error="$errors->first('cantidad')"
            />

            <div data-ag-movimiento-campo="ajuste">
                <x-atoms.select
                    name="sentido"
                    id="sentido"
                    :label="__('inventario.stock.campo_sentido')"
                    :options="$opcionesSentido"
                    :value="$sentido"
                    :placeholder="__('inventario.stock.campo_sentido')"
                    :error="$errors->first('sentido')"
                    required
                />
            </div>

            <div data-ag-movimiento-campo="compra">
                <x-atoms.input
                    type="number"
                    name="costo_unitario"
                    :label="__('inventario.stock.campo_costo_unitario')"
                    :value="$costoUnitario"
                    :suffix="__('inventario.stock.unidad_moneda')"
                    :help="__('inventario.stock.campo_costo_unitario_ayuda')"
                    min="0"
                    step="0.01"
                    required
                    :error="$errors->first('costo_unitario')"
                />
            </div>

            <div data-ag-movimiento-campo="ajuste traslado">
                <x-atoms.input
                    type="text"
                    name="motivo"
                    :label="__('inventario.stock.campo_motivo')"
                    :value="$motivo"
                    :placeholder="__('inventario.stock.campo_motivo_placeholder')"
                    required
                    :error="$errors->first('motivo')"
                />
            </div>
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('inventario.stock.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.stock.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">
                    {{ __('ui.action.save') }}
                </x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>
    </x-molecules.form-layout>
</form>
