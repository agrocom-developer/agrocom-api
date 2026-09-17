{{--
    Page: ordenes/edit (GET /panel/ordenes-mantenimiento/{orden}/editar, panel.ordenes-mantenimiento.edit)
    Detalle de una orden de mantenimiento (HU-37, tarea 53): arquetipo
    Detalle, §6 de docs/diseno/guia_pantalla_panel.md — resumen → cierre (si
    corresponde). Pese al nombre de la ruta (`edit`, mismo molde de URL que
    el resto del panel), NO es un formulario de edición de datos
    descriptivos: `man_ordenes_mantenimiento` no tiene esa mutación en este
    alcance (ver docblock de `OrdenesMantenimientoController`) — esta
    pantalla es el detalle desde el que se dispara `cerrar()`, la única
    transición de `estado` posible (invariante 7 de CLAUDE.md).

    Datos esperados (ver OrdenesMantenimientoController::edit()): la cáscara
    de CascaraPanel, más:
    - $orden (OrdenMantenimiento).
    - $etiquetaEquipo (string): identificador del equipo, ya resuelto por el
      controlador (`DB::table(...)` — OrdenMantenimiento no tiene relación
      Eloquent hacia Dron/Vehiculo).
    - $repuestosDisponibles / $basesDisponibles (Collection<int, string>):
      opciones del selector de repuestos y de la base de la orden.
    - $stockPorRepuesto (array<int, array<int, string>>): `repuesto_id =>
      [base_id => cantidad]`, solo para pintar la disponibilidad en el
      cliente (HU-57, tarea 80) — el servidor revalida igual en
      `MaquinaEstadosOrdenMantenimiento::cerrar()`.
    - $puedeCerrar (bool): gatea el formulario de cierre (presentación, no
      autorización — el servidor revalida en el controlador).

    Selector de repuestos por casillas (HU-57, tarea 80, reemplaza la fila
    repetible de dos selects de la tarea 53): cada repuesto del catálogo ya
    tiene su bloque de campos (`_repuesto-campos.blade.php`) inyectado vía
    `extraPorOpcion` de `x-atoms.checkbox-group` — deshabilitado hasta que se
    marca la casilla, así que no hace falta "agregar/quitar línea".
    `resources/js/pages/ordenes-mantenimiento-form.js` habilita esos campos,
    sincroniza `base_id` con la base global salvo override por línea, calcula
    la disponibilidad/aviso de stock (presentación — la guarda real sigue en
    el servidor) y arma el resumen siempre visible. El nombre final de cada
    campo no cambia: `repuestos[N][repuesto_id|base_id|cantidad]`, con N el
    id del propio repuesto (array asociativo — el controlador lo reindexa a
    lista antes de pasarlo a la máquina de estados, que no se toca).

    El formulario de cierre solo se pinta si la orden está `Abierta` Y
    `$puedeCerrar` — una orden `Cerrada` no vuelve a mostrarlo (no hay
    transición de reapertura en este alcance).

    Gateada por `mantenimiento.orden.ver`. `RepuestosInsuficientes`/
    `TransicionOrdenMantenimientoNoPermitida` vuelven acá como errores de
    sesión sobre el campo `repuestos` (ver
    `OrdenesMantenimientoController::cerrar()`).

    Estilos en resources/css/pages/ordenes-mantenimiento.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    // old('repuestos') llega keyed por repuesto_id (mismo esquema que envía
    // el formulario nuevo) — se arma un mapa repuesto_id => línea para
    // repintar cantidad/override de base tras un error de validación.
    $lineasOld = collect(old('repuestos', []))
        ->filter(fn ($linea) => is_array($linea) && ! empty($linea['repuesto_id'] ?? null))
        ->mapWithKeys(fn (array $linea) => [(int) $linea['repuesto_id'] => [
            'base_id' => $linea['base_id'] ?? null,
            'cantidad' => $linea['cantidad'] ?? null,
        ]]);
    $baseGlobalId = old('base_global') ?? $lineasOld->first()['base_id'] ?? null;
    $repuestosMarcados = $lineasOld->keys()->all();
    $extraPorRepuesto = $repuestosDisponibles->keys()
        ->mapWithKeys(fn ($id) => [
            $id => view('mantenimiento::pages.ordenes._repuesto-campos', [
                'repuestoId' => $id,
                'marcado' => in_array($id, $repuestosMarcados, true),
                'basesDisponibles' => $basesDisponibles,
                'baseGlobalId' => $baseGlobalId,
                'cantidadInicial' => $lineasOld[$id]['cantidad'] ?? null,
                'baseInicialLinea' => $lineasOld[$id]['base_id'] ?? null,
            ])->render(),
        ])
        ->all();
@endphp
<x-templates.panel-shell :title="__('mantenimiento.ordenes.titulo_detalle')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('mantenimiento.ordenes.titulo')"
    >
        <div class="ag-orden-mantenimiento-detalle">
            <x-atoms.button :href="route('panel.ordenes-mantenimiento.index')" variant="text" size="sm" icon="arrow_back">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>

            <x-organisms.page-header
                :title="__('mantenimiento.ordenes.titulo_detalle')"
                :subtitle="__('mantenimiento.ordenes.subtitulo_detalle')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('repuestos'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-orden-mantenimiento-detalle__aviso">
                    {{ $errors->first('repuestos') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-orden-mantenimiento-detalle__resumen">
                <span class="ag-orden-mantenimiento-detalle__campo">
                    <strong>{{ __('mantenimiento.ordenes.detalle_equipo') }}</strong>
                    {{ __('mantenimiento.equipo_tipo.'.$orden->equipo_tipo) }} · {{ $etiquetaEquipo }}
                </span>
                <span class="ag-orden-mantenimiento-detalle__campo">
                    <strong>{{ __('mantenimiento.ordenes.detalle_tipo') }}</strong>
                    {{ __('mantenimiento.tipo_orden.'.$orden->tipo) }}
                </span>
                <span class="ag-orden-mantenimiento-detalle__campo">
                    <strong>{{ __('mantenimiento.ordenes.detalle_estado') }}</strong>
                    <x-atoms.badge :variant="$orden->estado->value === 'abierta' ? 'neutral' : 'success'">
                        {{ __('mantenimiento.estado_orden.'.$orden->estado->value) }}
                    </x-atoms.badge>
                </span>
                <span class="ag-orden-mantenimiento-detalle__campo">
                    <strong>{{ __('mantenimiento.ordenes.detalle_fecha_apertura') }}</strong>
                    {{ $orden->fecha_apertura->format('d/m/Y H:i') }}
                </span>
                @if ($orden->fecha_cierre !== null)
                    <span class="ag-orden-mantenimiento-detalle__campo">
                        <strong>{{ __('mantenimiento.ordenes.detalle_fecha_cierre') }}</strong>
                        {{ $orden->fecha_cierre->format('d/m/Y H:i') }}
                    </span>
                @endif
                @if ($orden->gasto_id !== null)
                    <span class="ag-orden-mantenimiento-detalle__campo">
                        <strong>{{ __('mantenimiento.ordenes.detalle_gasto') }}</strong>
                        {{ __('mantenimiento.ordenes.detalle_gasto_valor', ['id' => $orden->gasto_id]) }}
                    </span>
                    @if ($montoGasto !== null)
                        <span class="ag-orden-mantenimiento-detalle__campo">
                            <strong>{{ __('mantenimiento.ordenes.detalle_precio_final') }}</strong>
                            Bs {{ $montoGasto }}
                        </span>
                    @endif
                @endif
                <span class="ag-orden-mantenimiento-detalle__campo ag-orden-mantenimiento-detalle__campo--full">
                    <strong>{{ __('mantenimiento.ordenes.detalle_descripcion') }}</strong>
                    {{ $orden->descripcion }}
                </span>
                @if ($orden->descripcion_final !== null)
                    <span class="ag-orden-mantenimiento-detalle__campo ag-orden-mantenimiento-detalle__campo--full">
                        <strong>{{ __('mantenimiento.ordenes.detalle_descripcion_final') }}</strong>
                        {{ $orden->descripcion_final }}
                    </span>
                @endif
            </div>

            @if ($orden->estado->value === 'abierta' && $puedeCerrar)
                <form
                    method="POST"
                    action="{{ route('panel.ordenes-mantenimiento.cerrar', $orden) }}"
                    class="ag-orden-mantenimiento-detalle__cierre"
                    novalidate
                    data-ag-orden-form
                    onsubmit="return confirm('{{ __('mantenimiento.ordenes.confirmar_cierre') }}')"
                >
                    @csrf

                    <x-molecules.form-section
                        :title="__('mantenimiento.ordenes.seccion_cierre')"
                    >
                        <p class="ag-form-section__field--full ag-orden-mantenimiento-detalle__ayuda">
                            {{ __('mantenimiento.ordenes.seccion_cierre_ayuda') }}
                        </p>

                        <x-atoms.textarea
                            name="descripcion_final"
                            :label="__('mantenimiento.ordenes.campo_descripcion_final')"
                            :help="__('mantenimiento.ordenes.campo_descripcion_final_ayuda')"
                            :value="old('descripcion_final')"
                            required
                            :error="$errors->first('descripcion_final')"
                            class="ag-form-section__field--full"
                        />

                        <div
                            class="ag-form-section__field--full ag-orden-mantenimiento-detalle__repuestos"
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

                            {{-- Sin data-ag-* propio acá: el LSP de checkbox-group
                                 reenvía cualquier atributo fuera de `class` a CADA
                                 `<input>` del grupo, no al `<fieldset>` — el JS
                                 engancha directo por `name="repuestos_marcados[]"`. --}}
                            <x-atoms.checkbox-group
                                name="repuestos_marcados"
                                :label="__('mantenimiento.ordenes.campo_repuestos')"
                                :options="$repuestosDisponibles"
                                :value="$repuestosMarcados"
                                :extra-por-opcion="$extraPorRepuesto"
                            />

                            <aside
                                class="ag-repuestos-resumen"
                                data-ag-repuestos-resumen
                                data-plantilla-contador="{{ __('mantenimiento.ordenes.resumen_contador', ['cantidad' => '__CANTIDAD__']) }}"
                                aria-live="polite"
                            >
                                <p class="ag-repuestos-resumen__titulo">{{ __('mantenimiento.ordenes.resumen_titulo') }}</p>
                                <p class="ag-repuestos-resumen__contador" data-ag-repuestos-resumen-contador hidden></p>
                                <p class="ag-repuestos-resumen__vacio" data-ag-repuestos-resumen-vacio>
                                    {{ __('mantenimiento.ordenes.resumen_vacio') }}
                                </p>
                                <ul class="ag-repuestos-resumen__lista" data-ag-repuestos-resumen-lista></ul>
                            </aside>
                        </div>
                    </x-molecules.form-section>

                    <x-organisms.form-actions-bar>
                        <x-slot:actions>
                            <x-atoms.button type="submit" variant="primary" icon="build">
                                {{ __('mantenimiento.ordenes.boton_cerrar') }}
                            </x-atoms.button>
                        </x-slot:actions>
                    </x-organisms.form-actions-bar>
                </form>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
