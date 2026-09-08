{{--
    Page: rendiciones/create (GET /panel/rendiciones/crear, panel.rendiciones.create)
    Alta de una rendición (HU-34, tarea 48) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin partial `_formulario` compartido
    con una edición: no existe caso de uso de edición (invariante de esta
    tarea, ver `Aplicacion/CrearRendicion`) — este archivo ES el formulario
    completo.

    Datos esperados (ver RendicionesController::create()): la cáscara de
    CascaraPanel, más:
    - $basesDisponibles (Collection<int, string>): id => nombre.
    - $personasDisponibles (Collection<int, string>): id => nombre.

    Estilos en resources/css/pages/rendiciones.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $baseId = old('base_id', '');
    $jefeId = old('jefe_campo_id', '');
    $fecha = old('fecha', now()->toDateString());
    $descripcion = old('descripcion', '');
@endphp

<x-templates.panel-shell :title="__('finanzas.rendiciones.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('finanzas.rendiciones.titulo_crear')"
    >
        <div class="ag-rendiciones-form-page">
            <form
                method="POST"
                action="{{ route('panel.rendiciones.store') }}"
                class="ag-rendiciones-form"
                novalidate
            >
                @csrf

                <x-organisms.page-header
                    :title="__('finanzas.rendiciones.titulo_crear')"
                    :subtitle="__('finanzas.rendiciones.subtitulo_form')"
                >
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.rendiciones.index') }}" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.page-header>

                <x-molecules.form-section
                    :title="__('finanzas.rendiciones.seccion_datos')"
                    :count="__('finanzas.rendiciones.campos_contador', ['cantidad' => 4])"
                >
                    <div class="ag-input">
                        <label for="base_id" class="ag-input__label">
                            {{ __('finanzas.rendiciones.campo_base') }}
                            <span class="ag-input__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ag-input__control {{ $errors->has('base_id') ? 'ag-input__control--error' : '' }}">
                            <select name="base_id" id="base_id" class="ag-input__field" required>
                                <option value="">{{ __('finanzas.rendiciones.campo_base_placeholder') }}</option>
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
                        <label for="jefe_campo_id" class="ag-input__label">
                            {{ __('finanzas.rendiciones.campo_jefe_campo') }}
                            <span class="ag-input__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ag-input__control {{ $errors->has('jefe_campo_id') ? 'ag-input__control--error' : '' }}">
                            <select name="jefe_campo_id" id="jefe_campo_id" class="ag-input__field" required>
                                <option value="">{{ __('finanzas.rendiciones.campo_jefe_campo_placeholder') }}</option>
                                @foreach ($personasDisponibles as $id => $nombre)
                                    <option value="{{ $id }}" @selected((string) $jefeId === (string) $id)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($errors->has('jefe_campo_id'))
                            <p class="ag-input__error" role="alert">{{ $errors->first('jefe_campo_id') }}</p>
                        @endif
                    </div>

                    <x-atoms.input
                        type="date"
                        name="fecha"
                        label="{{ __('finanzas.rendiciones.campo_fecha') }}"
                        value="{{ $fecha }}"
                        required
                        error="{{ $errors->first('fecha') }}"
                    />

                    <div class="ag-form-section__field--full">
                        <x-atoms.input
                            type="textarea"
                            name="descripcion"
                            label="{{ __('finanzas.rendiciones.campo_descripcion') }}"
                            value="{{ $descripcion }}"
                            error="{{ $errors->first('descripcion') }}"
                        />
                    </div>
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('finanzas.rendiciones.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.rendiciones.index') }}" variant="outline">
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
