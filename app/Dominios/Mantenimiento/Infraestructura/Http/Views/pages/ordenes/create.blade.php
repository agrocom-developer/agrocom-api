{{--
    Page: ordenes/create (GET /panel/ordenes-mantenimiento/crear, panel.ordenes-mantenimiento.create)
    Alta de una orden de mantenimiento (HU-37, tarea 53): abre la orden en
    estado `Abierta` (MaquinaEstadosOrdenMantenimiento::abrir()) — el cierre
    es otra pantalla (ordenes/edit.blade.php), otra responsabilidad
    (invariante 7 de CLAUDE.md).

    Datos esperados (ver OrdenesMantenimientoController::create()): la
    cáscara de CascaraPanel, más:
    - $dronesDisponibles (Collection<int, string>): id => identificador,
      drones vivos.
    - $vehiculosDisponibles (Collection<int, string>): id => identificador,
      vehículos vivos.

    Gateada por `mantenimiento.orden.crear`, verificado server-side en el
    controlador.
--}}
@php
    $equipoTipo = old('equipo_tipo', '');
    $equipoId = old('equipo_id', '');
    $tipo = old('tipo', '');
    $descripcion = old('descripcion', '');
@endphp
<x-templates.panel-shell :title="__('mantenimiento.ordenes.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.ordenes.titulo_crear')"
    >
        <form method="POST" action="{{ route('panel.ordenes-mantenimiento.store') }}" class="ag-ordenes-mantenimiento-form" novalidate data-ag-orden-form>
            @csrf

            <x-organisms.page-header
                :title="__('mantenimiento.ordenes.titulo_crear')"
                :subtitle="__('mantenimiento.ordenes.subtitulo_form')"
            >
                <x-slot:actions>
                    <x-atoms.button href="{{ route('panel.ordenes-mantenimiento.index') }}" variant="outline" icon="arrow_back">
                        {{ __('mantenimiento.ordenes.volver') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            <x-molecules.form-section
                :title="__('mantenimiento.ordenes.seccion_datos')"
                :count="__('mantenimiento.ordenes.campos_contador', ['cantidad' => 4])"
            >
                @php
                    $opcionesEquipoTipo = [
                        'dron' => __('mantenimiento.equipo_tipo.dron'),
                        'vehiculo' => __('mantenimiento.equipo_tipo.vehiculo'),
                    ];
                    $opcionesTipo = [
                        'preventivo' => __('mantenimiento.tipo_orden.preventivo'),
                        'correctivo' => __('mantenimiento.tipo_orden.correctivo'),
                    ];
                @endphp
                <x-atoms.select
                    name="equipo_tipo"
                    id="equipo_tipo"
                    label="{{ __('mantenimiento.ordenes.campo_equipo_tipo') }}"
                    :options="$opcionesEquipoTipo"
                    :value="$equipoTipo"
                    placeholder="{{ __('mantenimiento.ordenes.campo_equipo_tipo_placeholder') }}"
                    required
                    error="{{ $errors->first('equipo_tipo') }}"
                    data-ag-orden-equipo-tipo
                />

                <div data-ag-orden-campo="dron">
                    <x-atoms.select
                        name="equipo_id"
                        id="equipo_id_dron"
                        label="{{ __('mantenimiento.ordenes.campo_equipo_dron') }}"
                        :options="$dronesDisponibles"
                        :value="$equipoTipo === 'dron' ? $equipoId : ''"
                        placeholder="{{ __('mantenimiento.ordenes.campo_equipo_dron_placeholder') }}"
                    />
                </div>

                <div data-ag-orden-campo="vehiculo">
                    <x-atoms.select
                        name="equipo_id"
                        id="equipo_id_vehiculo"
                        label="{{ __('mantenimiento.ordenes.campo_equipo_vehiculo') }}"
                        :options="$vehiculosDisponibles"
                        :value="$equipoTipo === 'vehiculo' ? $equipoId : ''"
                        placeholder="{{ __('mantenimiento.ordenes.campo_equipo_vehiculo_placeholder') }}"
                        error="{{ $errors->first('equipo_id') }}"
                    />
                </div>

                <x-atoms.select
                    name="tipo"
                    id="tipo"
                    label="{{ __('mantenimiento.ordenes.campo_tipo') }}"
                    :options="$opcionesTipo"
                    :value="$tipo"
                    placeholder="{{ __('mantenimiento.ordenes.campo_tipo_placeholder') }}"
                    required
                    error="{{ $errors->first('tipo') }}"
                />

                <x-atoms.input
                    type="text"
                    name="descripcion"
                    label="{{ __('mantenimiento.ordenes.campo_descripcion') }}"
                    value="{{ $descripcion }}"
                    required
                    error="{{ $errors->first('descripcion') }}"
                />
            </x-molecules.form-section>

            <x-organisms.form-actions-bar :status="__('mantenimiento.ordenes.estado_form')">
                <x-slot:actions>
                    <x-atoms.button href="{{ route('panel.ordenes-mantenimiento.index') }}" variant="outline">
                        {{ __('ui.action.cancel') }}
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
