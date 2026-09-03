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
      opciones de los selects del formulario de cierre.
    - $puedeCerrar (bool): gatea el formulario de cierre (presentación, no
      autorización — el servidor revalida en el controlador).

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
    $repuestosIniciales = old('repuestos', [[]]);
@endphp
<x-templates.panel-shell :title="__('mantenimiento.ordenes.titulo_detalle')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('mantenimiento.ordenes.titulo')"
    >
        <div class="ag-orden-mantenimiento-detalle">
            <x-atoms.button href="{{ route('panel.ordenes-mantenimiento.index') }}" variant="text" size="sm" icon="arrow_back">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>

            <x-organisms.page-header
                :title="__('mantenimiento.ordenes.titulo_detalle')"
                :subtitle="__('mantenimiento.ordenes.subtitulo_detalle')"
            />

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
                @endif
                <span class="ag-orden-mantenimiento-detalle__campo ag-orden-mantenimiento-detalle__campo--full">
                    <strong>{{ __('mantenimiento.ordenes.detalle_descripcion') }}</strong>
                    {{ $orden->descripcion }}
                </span>
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

                        <div class="ag-form-section__field--full ag-orden-mantenimiento-detalle__repuestos" data-ag-repuestos>
                            <div data-ag-repuestos-lista>
                                @foreach ($repuestosIniciales as $indice => $linea)
                                    @include('mantenimiento::pages.ordenes._repuesto-linea', [
                                        'indice' => $indice,
                                        'linea' => $linea,
                                        'repuestosDisponibles' => $repuestosDisponibles,
                                        'basesDisponibles' => $basesDisponibles,
                                    ])
                                @endforeach
                            </div>

                            <x-atoms.button type="button" variant="outline" icon="add" data-ag-repuestos-agregar>
                                {{ __('mantenimiento.ordenes.repuesto_agregar') }}
                            </x-atoms.button>

                            {{-- Plantilla clonable (JS vanilla,
                                 resources/js/pages/ordenes-mantenimiento-form.js): el
                                 índice literal se reemplaza por el próximo número al
                                 clonar. Un <template> nunca se renderiza ni se envía
                                 con el form. --}}
                            <template data-ag-repuestos-template>
                                @include('mantenimiento::pages.ordenes._repuesto-linea', [
                                    'indice' => '__INDICE__',
                                    'linea' => [],
                                    'repuestosDisponibles' => $repuestosDisponibles,
                                    'basesDisponibles' => $basesDisponibles,
                                ])
                            </template>
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
