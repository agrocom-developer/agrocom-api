{{--
    Page: pausas/create (GET /panel/pausas/crear, panel.pausas.create)
    Alta de una pausa con causa atribuible (HU-44, tarea 58) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Sin partial
    `_formulario` compartido con una edición: no existe caso de uso de
    edición — este archivo ES el formulario completo (mismo criterio que
    `gastos/create.blade.php`).

    Datos esperados (ver PausasController::create()): la cáscara de
    CascaraPanel, más:
    - $sesionesDisponibles (Collection<int, string>): id => etiqueta, para
      el <select> de sesión (últimas 100).

    Estilos en resources/css/pages/pausas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $sesionId = old('sesion_id', '');
    $causa = old('causa', '');
    $inicio = old('inicio', '');
    $fin = old('fin', '');
@endphp

<x-templates.panel-shell :title="__('operaciones.pausas.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('operaciones.pausas.titulo_crear')"
    >
        <div class="ag-pausas-form-page">
            <form
                method="POST"
                action="{{ route('panel.pausas.store') }}"
                class="ag-pausas-form"
                novalidate
            >
                @csrf

                <x-organisms.page-header
                    :title="__('operaciones.pausas.titulo_crear')"
                    :subtitle="__('operaciones.pausas.subtitulo_form')"
                >
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.pausas.index') }}" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.page-header>

                @if ($errors->has('estado'))
                    <x-molecules.alert-strip variant="danger" icon="error" class="ag-pausas-form__aviso">
                        {{ $errors->first('estado') }}
                    </x-molecules.alert-strip>
                @endif

                <x-molecules.form-section
                    :title="__('operaciones.pausas.seccion_datos')"
                    :count="__('operaciones.pausas.campos_contador', ['cantidad' => 4])"
                >
                    <div class="ag-input">
                        <label for="sesion_id" class="ag-input__label">
                            {{ __('operaciones.pausas.campo_sesion') }}
                            <span class="ag-input__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ag-input__control {{ $errors->has('sesion_id') ? 'ag-input__control--error' : '' }}">
                            <select name="sesion_id" id="sesion_id" class="ag-input__field" required>
                                <option value="">{{ __('operaciones.pausas.campo_sesion_placeholder') }}</option>
                                @foreach ($sesionesDisponibles as $id => $etiqueta)
                                    <option value="{{ $id }}" @selected((string) $sesionId === (string) $id)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($errors->has('sesion_id'))
                            <p class="ag-input__error" role="alert">{{ $errors->first('sesion_id') }}</p>
                        @endif
                    </div>

                    <div class="ag-input">
                        <label for="causa" class="ag-input__label">
                            {{ __('operaciones.pausas.campo_causa') }}
                            <span class="ag-input__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ag-input__control {{ $errors->has('causa') ? 'ag-input__control--error' : '' }}">
                            <select name="causa" id="causa" class="ag-input__field" required>
                                <option value="">{{ __('operaciones.pausas.campo_causa_placeholder') }}</option>
                                @foreach (\App\Dominios\Operaciones\Dominio\CausaPausa::cases() as $opcion)
                                    <option value="{{ $opcion->value }}" @selected($causa === $opcion->value)>{{ __('operaciones.pausas.causa.'.$opcion->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($errors->has('causa'))
                            <p class="ag-input__error" role="alert">{{ $errors->first('causa') }}</p>
                        @endif
                    </div>

                    <x-atoms.input
                        type="datetime-local"
                        name="inicio"
                        label="{{ __('operaciones.pausas.campo_inicio') }}"
                        value="{{ $inicio }}"
                        required
                        error="{{ $errors->first('inicio') }}"
                    />

                    <x-atoms.input
                        type="datetime-local"
                        name="fin"
                        label="{{ __('operaciones.pausas.campo_fin') }}"
                        value="{{ $fin }}"
                        required
                        error="{{ $errors->first('fin') }}"
                    />
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('operaciones.pausas.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.pausas.index') }}" variant="outline">
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
