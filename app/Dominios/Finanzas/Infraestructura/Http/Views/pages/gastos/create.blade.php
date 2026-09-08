{{--
    Page: gastos/create (GET /panel/gastos/crear, panel.gastos.create)
    Alta de un gasto (HU-33, tarea 47) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin partial `_formulario` compartido
    con una edición: no existe caso de uso de edición (invariante de esta
    tarea, ver `Aplicacion/CrearGasto`) — este archivo ES el formulario
    completo.

    Datos esperados (ver GastosController::create()): la cáscara de
    CascaraPanel, más:
    - $rubrosConSubrubros (Collection<Rubro> con `subrubros` cargado): arma
      el <select> de rubro y la lista completa de subrubros con
      `data-rubro-id`, que `resources/js/pages/gastos-form.js` filtra en
      cliente según el rubro elegido.
    - $basesDisponibles / $trabajosDisponibles (Collection<int, string>):
      id => etiqueta, para los <select> opcionales de imputación.

    `enctype="multipart/form-data"`: primera subida de archivo humana desde
    el panel (a diferencia de `ope_evidencias`, que sube la app de campo) —
    ver docblock de `Aplicacion/CrearGasto`.

    Tras un error de validación, `old()` pisa los valores vacíos (el archivo
    NO se puede repoblar por HTML — el encargado tiene que volver a
    adjuntarlo, comportamiento estándar de `<input type="file">`).

    Estilos en resources/css/pages/gastos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $fecha = old('fecha', now()->toDateString());
    $rubroId = old('rubro_id', '');
    $subrubroId = old('subrubro_id', '');
    $cantidad = old('cantidad', '');
    $precioUnitario = old('precio_unitario', '');
    $baseId = old('base_id', '');
    $trabajoId = old('trabajo_id', '');
@endphp

<x-templates.panel-shell :title="__('finanzas.gastos.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('finanzas.gastos.titulo_crear')"
    >
        <div class="ag-gastos-form-page">
            <form
                method="POST"
                action="{{ route('panel.gastos.store') }}"
                enctype="multipart/form-data"
                class="ag-gastos-form"
                novalidate
                data-ag-gastos-form
            >
                @csrf

                <x-organisms.page-header
                    :title="__('finanzas.gastos.titulo_crear')"
                    :subtitle="__('finanzas.gastos.subtitulo_form')"
                >
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.gastos.index') }}" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.page-header>

                <x-molecules.form-section
                    :title="__('finanzas.gastos.seccion_datos')"
                    :count="__('finanzas.gastos.campos_contador', ['cantidad' => 8])"
                >
                    <x-atoms.input
                        type="date"
                        name="fecha"
                        label="{{ __('finanzas.gastos.campo_fecha') }}"
                        value="{{ $fecha }}"
                        required
                        error="{{ $errors->first('fecha') }}"
                    />

                    <div class="ag-input">
                        <label for="rubro_id" class="ag-input__label">
                            {{ __('finanzas.gastos.campo_rubro') }}
                            <span class="ag-input__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ag-input__control {{ $errors->has('rubro_id') ? 'ag-input__control--error' : '' }}">
                            <select name="rubro_id" id="rubro_id" class="ag-input__field" required data-ag-gasto-rubro>
                                <option value="">{{ __('finanzas.gastos.campo_rubro_placeholder') }}</option>
                                @foreach ($rubrosConSubrubros as $rubro)
                                    <option value="{{ $rubro->id }}" @selected((string) $rubroId === (string) $rubro->id)>{{ $rubro->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($errors->has('rubro_id'))
                            <p class="ag-input__error" role="alert">{{ $errors->first('rubro_id') }}</p>
                        @endif
                    </div>

                    <div class="ag-input">
                        <label for="subrubro_id" class="ag-input__label">{{ __('finanzas.gastos.campo_subrubro') }}</label>
                        <div class="ag-input__control {{ $errors->has('subrubro_id') ? 'ag-input__control--error' : '' }}">
                            <select name="subrubro_id" id="subrubro_id" class="ag-input__field" data-ag-gasto-subrubro>
                                <option value="">{{ __('finanzas.gastos.campo_subrubro_placeholder') }}</option>
                                @foreach ($rubrosConSubrubros as $rubro)
                                    @foreach ($rubro->subrubros as $subrubro)
                                        <option
                                            value="{{ $subrubro->id }}"
                                            data-rubro-id="{{ $rubro->id }}"
                                            @selected((string) $subrubroId === (string) $subrubro->id)
                                        >{{ $subrubro->nombre }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        @if ($errors->has('subrubro_id'))
                            <p class="ag-input__error" role="alert">{{ $errors->first('subrubro_id') }}</p>
                        @endif
                    </div>

                    <x-atoms.input
                        type="number"
                        name="cantidad"
                        label="{{ __('finanzas.gastos.campo_cantidad') }}"
                        value="{{ $cantidad }}"
                        min="0.01"
                        step="0.01"
                        required
                        error="{{ $errors->first('cantidad') }}"
                    />

                    <x-atoms.input
                        type="number"
                        name="precio_unitario"
                        label="{{ __('finanzas.gastos.campo_precio_unitario') }}"
                        value="{{ $precioUnitario }}"
                        min="0.01"
                        step="0.01"
                        required
                        error="{{ $errors->first('precio_unitario') }}"
                    />

                    <div class="ag-input">
                        <label for="base_id" class="ag-input__label">{{ __('finanzas.gastos.campo_base') }}</label>
                        <div class="ag-input__control {{ $errors->has('base_id') ? 'ag-input__control--error' : '' }}">
                            <select name="base_id" id="base_id" class="ag-input__field">
                                <option value="">{{ __('finanzas.gastos.campo_base_placeholder') }}</option>
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
                        <label for="trabajo_id" class="ag-input__label">{{ __('finanzas.gastos.campo_trabajo') }}</label>
                        <div class="ag-input__control {{ $errors->has('trabajo_id') ? 'ag-input__control--error' : '' }}">
                            <select name="trabajo_id" id="trabajo_id" class="ag-input__field">
                                <option value="">{{ __('finanzas.gastos.campo_trabajo_placeholder') }}</option>
                                @foreach ($trabajosDisponibles as $id => $etiqueta)
                                    <option value="{{ $id }}" @selected((string) $trabajoId === (string) $id)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($errors->has('trabajo_id'))
                            <p class="ag-input__error" role="alert">{{ $errors->first('trabajo_id') }}</p>
                        @endif
                    </div>

                    <div class="ag-form-section__field--full">
                        <x-atoms.input
                            type="file"
                            name="comprobante"
                            label="{{ __('finanzas.gastos.campo_comprobante') }}"
                            accept="image/jpeg,image/png,application/pdf"
                            help="{{ __('finanzas.gastos.campo_comprobante_ayuda') }}"
                            error="{{ $errors->first('comprobante') }}"
                        />
                    </div>
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('finanzas.gastos.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.gastos.index') }}" variant="outline">
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
