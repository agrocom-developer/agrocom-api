{{--
    Partial: la sección de repuestos de la ficha de una orden de mantenimiento
    (tarea 116). Es una tabla de detalle dentro de su `form-section`
    (`molecules/index-table`, mismo molde que las tablas de
    `personal::pages.cuadrillas._formulario`), y muestra una cosa distinta
    según dónde esté la orden:

    - ABIERTA y con permiso de cerrar: el SELECTOR — una fila por repuesto del
      catálogo, con su casilla, la disponibilidad en la base elegida, la
      cantidad y la base de esa línea. Reemplaza al `checkbox-group` con
      campos inyectados por `extraPorOpcion` de la tarea 80: lo que se elige
      por fila es tabular, y una tabla lo dice mejor que una lista de casillas
      con bloques colgando.
      **No pagina a propósito**: cambiar de página recarga y perdería lo ya
      marcado. La tabla scrollea dentro de su contenedor si crece.
    - ABIERTA sin permiso: el vacío que explica quién elige los repuestos.
    - CERRADA: lo que el cierre descontó de verdad, leído de Inventario por su
      contrato (`LecturaConsumosPorOrden`) — nunca de `inv_movimientos`
      directo. Esta sí pagina: es solo lectura y no hay nada que perder.

    Espera (todo resuelto por `_formulario.blade.php`, ninguna consulta acá):
    - $orden (OrdenMantenimiento), $esAbierta, $puedeElegirRepuestos (bool).
    - $repuestosDisponibles / $basesDisponibles (Collection<int, string>).
    - $stockPorRepuesto (array<int, array<int, string>>).
    - $repuestosMarcados (list<int>), $lineasOld (Collection), $baseGlobalId.
    - $consumos (LengthAwarePaginator<int, DatosConsumoOrden>|null).
    - $nombresBase (array<int, string>): base_id => nombre.

    `resources/js/pages/ordenes-mantenimiento-form.js` habilita los campos de
    la fila al marcar su casilla, sincroniza la base de cada línea con la base
    de la orden mientras esa fila no tenga una propia, y calcula la
    disponibilidad y el aviso de stock. Todo eso es presentación: la guarda
    real sigue en `MaquinaEstadosOrdenMantenimiento::cerrar()`.
--}}
<x-molecules.form-section
    :title="__('mantenimiento.ordenes.seccion_repuestos')"
    :count="$puedeElegirRepuestos
        ? __('mantenimiento.ordenes.repuestos_contador', ['cantidad' => $repuestosDisponibles->count()])
        : __('mantenimiento.ordenes.repuestos_consumidos_contador', ['cantidad' => $consumos?->total() ?? 0])"
>
    @if ($puedeElegirRepuestos)
        <div
            class="ag-form-section__field--full ag-ordenes-mantenimiento-form__repuestos"
            data-ag-repuestos
            data-stock-por-repuesto="{{ json_encode($stockPorRepuesto) }}"
            data-plantilla-disponible="{{ __('mantenimiento.ordenes.repuesto_disponible', ['cantidad' => '__CANTIDAD__']) }}"
            data-plantilla-aviso="{{ __('mantenimiento.ordenes.repuesto_aviso_stock', ['disponible' => '__DISPONIBLE__']) }}"
            data-texto-sin-base="{{ __('mantenimiento.ordenes.repuesto_sin_base') }}"
        >
            <x-atoms.select
                name="base_global"
                id="orden_base_global"
                :label="__('mantenimiento.ordenes.campo_base_orden')"
                :help="__('mantenimiento.ordenes.campo_base_orden_ayuda')"
                :options="$basesDisponibles"
                :value="$baseGlobalId"
                :placeholder="__('mantenimiento.ordenes.campo_base_orden_placeholder')"
                data-ag-orden-base-global
            />

            @if ($repuestosDisponibles->isEmpty())
                <x-molecules.empty-state
                    icon="inventory_2"
                    :title="__('mantenimiento.ordenes.catalogo_vacio_titulo')"
                    :detail="__('mantenimiento.ordenes.catalogo_vacio_detalle')"
                />
            @else
                {{-- La columna de la base pide ancho propio: es un combobox, y a su
                     texto le compiten adentro el botón de limpiar y la flecha —con
                     menos de 14rem el nombre elegido («Base Santa Cruz») sale
                     recortado (medido en navegador el 20/9/2026). --}}
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 1fr) 6.5rem minmax(14rem, 1.9fr)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_repuesto') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_disponible') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_cantidad') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_base') }}</span>
                    </x-slot:head>

                    @foreach ($repuestosDisponibles as $repuestoId => $etiquetaRepuesto)
                        @php
                            $marcado = in_array((int) $repuestoId, $repuestosMarcados, true);
                            $baseLinea = $lineasOld[$repuestoId]['base_id'] ?? $baseGlobalId;
                            $tieneBasePropia = $marcado
                                && ($lineasOld[$repuestoId]['base_id'] ?? null) !== null
                                && (string) $lineasOld[$repuestoId]['base_id'] !== (string) $baseGlobalId;
                        @endphp

                        <div
                            class="ag-index-table__row"
                            role="row"
                            data-ag-repuesto-campos
                            data-repuesto-id="{{ $repuestoId }}"
                            data-base-propia="{{ $tieneBasePropia ? '1' : '0' }}"
                            data-etiqueta="{{ $etiquetaRepuesto }}"
                        >
                            <span role="cell" class="ag-index-table__indice">{{ $loop->iteration }}</span>

                            {{-- La casilla va acá y no en la celda de índice a
                                 propósito: `index-table` oculta el índice en
                                 mobile, y sin casilla no habría forma de
                                 elegir el repuesto desde el teléfono. --}}
                            <span role="cell">
                                <x-atoms.checkbox
                                    name="repuestos_marcados[]"
                                    :id="'repuesto-marcado-'.$repuestoId"
                                    :value="$repuestoId"
                                    :label="$etiquetaRepuesto"
                                    :checked="$marcado"
                                />

                                <input
                                    type="hidden"
                                    name="repuestos[{{ $repuestoId }}][repuesto_id]"
                                    value="{{ $repuestoId }}"
                                    @disabled(! $marcado)
                                    data-ag-repuesto-campo="repuesto_id"
                                >
                                <input
                                    type="hidden"
                                    name="repuestos[{{ $repuestoId }}][base_id]"
                                    value="{{ $baseLinea }}"
                                    @disabled(! $marcado)
                                    data-ag-repuesto-campo="base_id"
                                >
                            </span>

                            <span role="cell" class="ag-ordenes-mantenimiento-form__disponible">
                                <span class="ag-ordenes-mantenimiento-form__disponible-texto" data-ag-repuesto-disponibilidad></span>
                                <span class="ag-ordenes-mantenimiento-form__aviso" data-ag-repuesto-aviso hidden role="alert">
                                    <x-atoms.icon name="warning" size="sm" />
                                    <span data-ag-repuesto-aviso-texto></span>
                                </span>
                            </span>

                            <span role="cell">
                                {{-- `:disabled`, no la directiva `@ disabled()` (sin el
                                     espacio): dentro de la lista de atributos de un tag de
                                     componente, el compilador de tags de Blade se come el
                                     límite del tag y lo deja SIN COMPILAR —el `<x-atoms.input>`
                                     sale crudo al HTML—. En un `<input>` plano, como los
                                     hidden de arriba, sí funciona. Misma nota que
                                     `comercial::pages.contratos._lotes-tabla`. --}}
                                <x-atoms.input
                                    type="number"
                                    :name="'repuestos['.$repuestoId.'][cantidad]'"
                                    :id="'repuesto-cantidad-'.$repuestoId"
                                    :aria-label="__('mantenimiento.ordenes.campo_cantidad_aria', ['repuesto' => $etiquetaRepuesto])"
                                    :value="$lineasOld[$repuestoId]['cantidad'] ?? null"
                                    min="0.01"
                                    step="0.01"
                                    :disabled="! $marcado"
                                    data-ag-repuesto-campo="cantidad"
                                    :error="$errors->first('repuestos.'.$repuestoId.'.cantidad')"
                                />
                            </span>

                            <span role="cell">
                                {{-- Nombre transitorio (`__repuestos_base_override`,
                                     no `repuestos.*`): el request lo ignora.
                                     Su valor lo copia el JS al `base_id` real
                                     de la línea, nunca viaja él mismo.

                                     Nace habilitado aunque la fila no esté
                                     marcada, y lo deshabilita el JS: el combobox
                                     de `atoms/select` pinta `tabindex`/
                                     `aria-disabled` una sola vez, al renderizar,
                                     así que nacer deshabilitado dejaría el
                                     disparador fuera del foco de teclado incluso
                                     después de marcar la fila. `nativo.disabled`
                                     sí lo relee en cada interacción. --}}
                                <x-atoms.select
                                    :name="'__repuestos_base_override['.$repuestoId.']'"
                                    :id="'repuesto-base-'.$repuestoId"
                                    :aria-label="__('mantenimiento.ordenes.campo_base_aria', ['repuesto' => $etiquetaRepuesto])"
                                    :options="$basesDisponibles"
                                    :value="$baseLinea"
                                    :placeholder="__('mantenimiento.ordenes.campo_base_placeholder')"
                                    data-ag-repuesto-base-override
                                />
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <p
                    class="ag-ordenes-mantenimiento-form__contador"
                    data-ag-repuestos-resumen
                    data-plantilla-contador="{{ __('mantenimiento.ordenes.resumen_contador', ['cantidad' => '__CANTIDAD__']) }}"
                    aria-live="polite"
                >
                    <span data-ag-repuestos-resumen-vacio>{{ __('mantenimiento.ordenes.resumen_vacio') }}</span>
                    <span data-ag-repuestos-resumen-contador hidden></span>
                </p>
            @endif
        </div>
    @elseif ($esAbierta)
        <div class="ag-form-section__field--full">
            <x-molecules.empty-state
                icon="inventory_2"
                :title="__('mantenimiento.ordenes.repuestos_sin_permiso_titulo')"
                :detail="__('mantenimiento.ordenes.repuestos_sin_permiso_detalle')"
            />
        </div>
    @else
        <div class="ag-form-section__field--full">
            @if ($consumos === null || $consumos->isEmpty())
                <x-molecules.empty-state
                    icon="inventory_2"
                    :title="__('mantenimiento.ordenes.consumos_vacio_titulo')"
                    :detail="__('mantenimiento.ordenes.consumos_vacio_detalle')"
                />
            @else
                <x-molecules.index-table columns="3rem minmax(0, 2.2fr) minmax(0, 1.2fr) 8rem 9rem">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_repuesto') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_base') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_cantidad') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_costo') }}</span>
                    </x-slot:head>

                    @foreach ($consumos as $consumo)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($consumos->currentPage() - 1) * $consumos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">{{ $consumo->etiqueta() }}</span>
                            <span role="cell">{{ $nombresBase[$consumo->baseId] ?? '#'.$consumo->baseId }}</span>
                            <span role="cell" class="ag-ordenes-mantenimiento-form__mono">{{ $consumo->cantidad }}</span>
                            <span role="cell" class="ag-ordenes-mantenimiento-form__mono">
                                {{ __('mantenimiento.aside.monto_valor', ['monto' => number_format((float) $consumo->costoTotal, 2, ',', '.')]) }}
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$consumos" :aria-label="__('mantenimiento.ordenes.paginacion_repuestos')" />
            @endif
        </div>
    @endif
</x-molecules.form-section>
