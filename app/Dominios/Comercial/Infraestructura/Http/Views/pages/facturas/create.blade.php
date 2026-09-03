{{--
    Page: facturas/create (GET /panel/facturas/crear, panel.facturas.create)
    Emisión de una factura (HU-31, tarea 45) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Un solo campo: el acta a facturar. El
    monto no se pide ni se muestra acá — lo calcula el servidor al confirmar
    (hectáreas conformadas del acta × precio/ha del contrato).

    Datos esperados (ver FacturasController::create()): la cáscara de
    CascaraPanel, más:
    - $actasDisponibles (list<array{actaId: int, contratoId: int,
      hectareasConformadas: string, clienteNombre: string}>): actas firmadas
      sin factura, ya resueltas por `Aplicacion/ListarActasFacturables`.

    Tras un error de validación (incluido el rechazo por `ActaNoFacturable`,
    capturado en FacturasController::store()), `old()` pisa el valor vacío.

    Estilos en resources/css/pages/facturas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $actaId = old('acta_id', '');
@endphp

<x-templates.panel-shell :title="__('comercial.facturas.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('comercial.facturas.titulo_crear')"
    >
        <div class="ag-facturas-form-page">
            @if (count($actasDisponibles) === 0)
                <x-molecules.alert-strip variant="info" icon="receipt_long" class="ag-facturas-form-page__aviso">
                    {{ __('comercial.facturas.sin_actas_disponibles') }}
                </x-molecules.alert-strip>
            @else
                <form method="POST" action="{{ route('panel.facturas.store') }}" class="ag-facturas-form" novalidate>
                    @csrf

                    <x-organisms.page-header
                        :title="__('comercial.facturas.titulo_crear')"
                        :subtitle="__('comercial.facturas.subtitulo_form')"
                    >
                        <x-slot:actions>
                            <x-atoms.button href="{{ route('panel.facturas.index') }}" variant="outline">
                                {{ __('ui.action.cancel') }}
                            </x-atoms.button>
                            <x-atoms.button type="submit" variant="primary">
                                {{ __('ui.action.save') }}
                            </x-atoms.button>
                        </x-slot:actions>
                    </x-organisms.page-header>

                    <x-molecules.form-section
                        :title="__('comercial.facturas.seccion_datos')"
                        :count="__('comercial.facturas.campos_contador', ['cantidad' => 1])"
                    >
                        <div class="ag-form-section__field--full">
                            <div class="ag-input">
                                <label for="acta_id" class="ag-input__label">
                                    {{ __('comercial.facturas.campo_acta') }}
                                    <span class="ag-input__required" aria-hidden="true">*</span>
                                </label>
                                <div class="ag-input__control {{ $errors->has('acta_id') ? 'ag-input__control--error' : '' }}">
                                    <select name="acta_id" id="acta_id" class="ag-input__field" required>
                                        <option value="">{{ __('comercial.facturas.campo_acta_placeholder') }}</option>
                                        @foreach ($actasDisponibles as $acta)
                                            <option value="{{ $acta['actaId'] }}" @selected((string) $actaId === (string) $acta['actaId'])>
                                                {{ __('comercial.facturas.campo_acta_opcion', ['cliente' => $acta['clienteNombre'], 'id' => $acta['actaId'], 'hectareas' => number_format((float) $acta['hectareasConformadas'], 2, ',', '.')]) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($errors->has('acta_id'))
                                    <p class="ag-input__error" role="alert">{{ $errors->first('acta_id') }}</p>
                                @endif
                            </div>
                        </div>
                    </x-molecules.form-section>

                    <x-organisms.form-actions-bar :status="__('comercial.facturas.estado_form')">
                        <x-slot:actions>
                            <x-atoms.button href="{{ route('panel.facturas.index') }}" variant="outline">
                                {{ __('ui.action.cancel') }}
                            </x-atoms.button>
                            <x-atoms.button type="submit" variant="primary">
                                {{ __('ui.action.save') }}
                            </x-atoms.button>
                        </x-slot:actions>
                    </x-organisms.form-actions-bar>
                </form>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
