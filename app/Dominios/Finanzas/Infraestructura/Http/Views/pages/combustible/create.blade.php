{{--
    Page: combustible/create (GET /panel/combustible/crear, panel.combustible.create)
    Alta de una carga de combustible (HU-35, tarea 49) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Sin partial
    `_formulario` compartido con una edición: no existe caso de uso de
    edición (invariante de esta tarea, ver `Aplicacion/CrearCombustible`) —
    este archivo ES el formulario completo.

    Datos esperados (ver CombustibleController::create()): la cáscara de
    CascaraPanel, más:
    - $basesDisponibles (Collection<int, string>): id => nombre, para el
      <select> de base (obligatorio — "carga por base y fecha" es el CA
      literal, a diferencia de la base opcional de gastos).

    Tras un error de validación, `old()` pisa los valores vacíos.

    Estilos en resources/css/pages/combustible.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $fecha = old('fecha', now()->toDateString());
    $baseId = old('base_id', '');
    $destino = old('destino', '');
    $litros = old('litros', '');
    $monto = old('monto', '');
    $descripcion = old('descripcion', '');
@endphp

<x-templates.panel-shell :title="__('finanzas.combustible.titulo_crear')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('finanzas.combustible.titulo_crear')"
    >
        <div class="ag-combustible-form-page">
            <form method="POST" action="{{ route('panel.combustible.store') }}" class="ag-combustible-form" novalidate data-ag-combustible-form>
                @csrf

                <x-organisms.page-header
                    :title="__('finanzas.combustible.titulo_crear')"
                    :subtitle="__('finanzas.combustible.subtitulo_form')"
                >
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.combustible.index') }}" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.page-header>

                <x-molecules.form-section
                    :title="__('finanzas.combustible.seccion_datos')"
                    :count="__('finanzas.combustible.campos_contador', ['cantidad' => 6])"
                >
                    <x-atoms.input
                        type="date"
                        name="fecha"
                        label="{{ __('finanzas.combustible.campo_fecha') }}"
                        value="{{ $fecha }}"
                        required
                        error="{{ $errors->first('fecha') }}"
                    />

                    <div class="ag-input">
                        <label for="base_id" class="ag-input__label">
                            {{ __('finanzas.combustible.campo_base') }}
                            <span class="ag-input__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ag-input__control {{ $errors->has('base_id') ? 'ag-input__control--error' : '' }}">
                            <select name="base_id" id="base_id" class="ag-input__field" required>
                                <option value="">{{ __('finanzas.combustible.campo_base_placeholder') }}</option>
                                @foreach ($basesDisponibles as $id => $nombre)
                                    <option value="{{ $id }}" @selected((string) $baseId === (string) $id)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($errors->has('base_id'))
                            <p class="ag-input__error" role="alert">{{ $errors->first('base_id') }}</p>
                        @endif
                    </div>

                    <div class="ag-input">
                        <label for="destino" class="ag-input__label">
                            {{ __('finanzas.combustible.campo_destino') }}
                            <span class="ag-input__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ag-input__control {{ $errors->has('destino') ? 'ag-input__control--error' : '' }}">
                            <select name="destino" id="destino" class="ag-input__field" required>
                                <option value="">{{ __('finanzas.combustible.campo_destino_placeholder') }}</option>
                                <option value="generador" @selected($destino === 'generador')>{{ __('finanzas.combustible.destino.generador') }}</option>
                                <option value="vehiculo" @selected($destino === 'vehiculo')>{{ __('finanzas.combustible.destino.vehiculo') }}</option>
                            </select>
                        </div>
                        @if ($errors->has('destino'))
                            <p class="ag-input__error" role="alert">{{ $errors->first('destino') }}</p>
                        @endif
                    </div>

                    <x-atoms.input
                        type="number"
                        name="litros"
                        label="{{ __('finanzas.combustible.campo_litros') }}"
                        value="{{ $litros }}"
                        min="0.01"
                        step="0.01"
                        required
                        error="{{ $errors->first('litros') }}"
                    />

                    <x-atoms.input
                        type="number"
                        name="monto"
                        label="{{ __('finanzas.combustible.campo_monto') }}"
                        value="{{ $monto }}"
                        min="0.01"
                        step="0.01"
                        required
                        error="{{ $errors->first('monto') }}"
                    />

                    <div class="ag-form-section__field--full">
                        <x-atoms.input
                            type="text"
                            name="descripcion"
                            label="{{ __('finanzas.combustible.campo_descripcion') }}"
                            value="{{ $descripcion }}"
                            error="{{ $errors->first('descripcion') }}"
                        />
                    </div>
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('finanzas.combustible.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.combustible.index') }}" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </form>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
