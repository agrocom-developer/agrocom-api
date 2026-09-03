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
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
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
                    <x-atoms.button href="{{ route('panel.ordenes-mantenimiento.index') }}" variant="outline">
                        {{ __('ui.action.cancel') }}
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            <x-molecules.form-section
                :title="__('mantenimiento.ordenes.seccion_datos')"
                :count="__('mantenimiento.ordenes.campos_contador', ['cantidad' => 4])"
            >
                <div class="ag-input">
                    <label for="equipo_tipo" class="ag-input__label">
                        {{ __('mantenimiento.ordenes.campo_equipo_tipo') }}
                        <span class="ag-input__required" aria-hidden="true">*</span>
                    </label>
                    <div class="ag-input__control {{ $errors->has('equipo_tipo') ? 'ag-input__control--error' : '' }}">
                        <select name="equipo_tipo" id="equipo_tipo" class="ag-input__field" required data-ag-orden-equipo-tipo>
                            <option value="" disabled @selected($equipoTipo === '')>{{ __('mantenimiento.ordenes.campo_equipo_tipo_placeholder') }}</option>
                            <option value="dron" @selected($equipoTipo === 'dron')>{{ __('mantenimiento.equipo_tipo.dron') }}</option>
                            <option value="vehiculo" @selected($equipoTipo === 'vehiculo')>{{ __('mantenimiento.equipo_tipo.vehiculo') }}</option>
                        </select>
                    </div>
                    @if ($errors->has('equipo_tipo'))
                        <p class="ag-input__error" role="alert">{{ $errors->first('equipo_tipo') }}</p>
                    @endif
                </div>

                <div class="ag-input" data-ag-orden-campo="dron">
                    <label for="equipo_id_dron" class="ag-input__label">{{ __('mantenimiento.ordenes.campo_equipo_dron') }}</label>
                    <div class="ag-input__control {{ $errors->has('equipo_id') ? 'ag-input__control--error' : '' }}">
                        <select name="equipo_id" id="equipo_id_dron" class="ag-input__field">
                            <option value="" @selected($equipoId === '')>{{ __('mantenimiento.ordenes.campo_equipo_dron_placeholder') }}</option>
                            @foreach ($dronesDisponibles as $id => $identificador)
                                <option value="{{ $id }}" @selected($equipoTipo === 'dron' && (string) $equipoId === (string) $id)>{{ $identificador }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ag-input" data-ag-orden-campo="vehiculo">
                    <label for="equipo_id_vehiculo" class="ag-input__label">{{ __('mantenimiento.ordenes.campo_equipo_vehiculo') }}</label>
                    <div class="ag-input__control {{ $errors->has('equipo_id') ? 'ag-input__control--error' : '' }}">
                        <select name="equipo_id" id="equipo_id_vehiculo" class="ag-input__field">
                            <option value="" @selected($equipoId === '')>{{ __('mantenimiento.ordenes.campo_equipo_vehiculo_placeholder') }}</option>
                            @foreach ($vehiculosDisponibles as $id => $identificador)
                                <option value="{{ $id }}" @selected($equipoTipo === 'vehiculo' && (string) $equipoId === (string) $id)>{{ $identificador }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($errors->has('equipo_id'))
                        <p class="ag-input__error" role="alert">{{ $errors->first('equipo_id') }}</p>
                    @endif
                </div>

                <div class="ag-input">
                    <label for="tipo" class="ag-input__label">
                        {{ __('mantenimiento.ordenes.campo_tipo') }}
                        <span class="ag-input__required" aria-hidden="true">*</span>
                    </label>
                    <div class="ag-input__control {{ $errors->has('tipo') ? 'ag-input__control--error' : '' }}">
                        <select name="tipo" id="tipo" class="ag-input__field" required>
                            <option value="" disabled @selected($tipo === '')>{{ __('mantenimiento.ordenes.campo_tipo_placeholder') }}</option>
                            <option value="preventivo" @selected($tipo === 'preventivo')>{{ __('mantenimiento.tipo_orden.preventivo') }}</option>
                            <option value="correctivo" @selected($tipo === 'correctivo')>{{ __('mantenimiento.tipo_orden.correctivo') }}</option>
                        </select>
                    </div>
                    @if ($errors->has('tipo'))
                        <p class="ag-input__error" role="alert">{{ $errors->first('tipo') }}</p>
                    @endif
                </div>

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
