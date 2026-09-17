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
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
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
                        <x-atoms.button :href="route('panel.rendiciones.index')" variant="outline" icon="arrow_back">
                            {{ __('finanzas.rendiciones.volver') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.page-header>

                <x-molecules.form-section
                    :title="__('finanzas.rendiciones.seccion_datos')"
                    :count="__('finanzas.rendiciones.campos_contador', ['cantidad' => 4])"
                >
                    <x-atoms.select
                        name="base_id"
                        id="base_id"
                        :label="__('finanzas.rendiciones.campo_base')"
                        :options="$basesDisponibles"
                        :value="(string) $baseId"
                        :placeholder="__('finanzas.rendiciones.campo_base_placeholder')"
                        :error="$errors->first('base_id')"
                        required
                    />

                    <x-atoms.select
                        name="jefe_campo_id"
                        id="jefe_campo_id"
                        :label="__('finanzas.rendiciones.campo_jefe_campo')"
                        :options="$personasDisponibles"
                        :value="(string) $jefeId"
                        :placeholder="__('finanzas.rendiciones.campo_jefe_campo_placeholder')"
                        :error="$errors->first('jefe_campo_id')"
                        required
                    />

                    <x-atoms.date
                        name="fecha"
                        :label="__('finanzas.rendiciones.campo_fecha')"
                        :value="$fecha"
                        required
                        :error="$errors->first('fecha')"
                    />

                    <div class="ag-form-section__field--full">
                        <x-atoms.textarea
                            name="descripcion"
                            :label="__('finanzas.rendiciones.campo_descripcion')"
                            :value="$descripcion"
                            :error="$errors->first('descripcion')"
                        />
                    </div>
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('finanzas.rendiciones.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.rendiciones.index')" variant="outline">
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
