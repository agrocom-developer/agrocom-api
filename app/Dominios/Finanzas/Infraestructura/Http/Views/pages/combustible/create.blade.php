{{--
    Page: combustible/create (GET /panel/combustible/crear, panel.combustible.create)
    Alta de una carga de combustible (HU-35, tarea 49; reescrita por la tarea
    73, HU-50) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Sin partial `_formulario` compartido
    con una edición: no existe caso de uso de edición (invariante de esta
    tarea, ver `Aplicacion/CrearCombustible`) — este archivo ES el
    formulario completo.

    Datos esperados (ver CombustibleController::create()): la cáscara de
    CascaraPanel, más:
    - $basesDisponibles / $equiposDisponibles / $campaniasDisponibles
      (Collection<int, string>): id => etiqueta, para los <select> de base,
      equipo y campaña (esta última opcional, ya filtrada a no `cerrada`).
    - $equipoTrabajoIdSeleccionado (int|null) / $fechaSeleccionada (string):
      lo que trae la query string — recargar la página al cambiar equipo o
      fecha es lo que puebla $recursosDisponibles con los recursos de ESE
      equipo en ESA fecha (`resources/js/pages/combustible-form.js`), nunca
      el catálogo entero.
    - $recursosDisponibles (Collection<string, string>): clave compuesta
      `"{tipo}:{id}"` => etiqueta (ver docblock de `CrearCombustibleRequest`
      sobre por qué es compuesta) — vacía si todavía no se eligió equipo.

    Tras un error de validación, `old()` pisa los valores vacíos; la
    redirección de vuelta (`CombustibleController::store()`) preserva
    `equipo_trabajo_id`/`fecha` en la query para que el `<select>` de
    recurso siga poblado con las mismas opciones.

    Estilos en resources/css/pages/combustible.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $fecha = old('fecha', $fechaSeleccionada);
    $baseId = old('base_id', '');
    $equipoTrabajoId = old('equipo_trabajo_id', (string) ($equipoTrabajoIdSeleccionado ?? ''));
    $campaniaId = old('campania_id', '');
    $recurso = old('recurso', '');
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
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('finanzas.combustible.titulo_crear')"
    >
        <div class="ag-combustible-form-page">
            <form
                method="POST"
                action="{{ route('panel.combustible.store') }}"
                class="ag-combustible-form"
                novalidate
                data-ag-combustible-form
                data-ag-combustible-create-url="{{ route('panel.combustible.create') }}"
            >
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
                    :count="__('finanzas.combustible.campos_contador', ['cantidad' => 8])"
                >
                    <x-atoms.date
                        name="fecha"
                        id="fecha"
                        label="{{ __('finanzas.combustible.campo_fecha') }}"
                        value="{{ $fecha }}"
                        required
                        error="{{ $errors->first('fecha') }}"
                        data-ag-combustible-fecha
                    />

                    <x-atoms.select
                        name="base_id"
                        id="base_id"
                        label="{{ __('finanzas.combustible.campo_base') }}"
                        :options="$basesDisponibles"
                        :value="(string) $baseId"
                        placeholder="{{ __('finanzas.combustible.campo_base_placeholder') }}"
                        :error="$errors->first('base_id')"
                        required
                    />

                    <x-atoms.select
                        name="equipo_trabajo_id"
                        id="equipo_trabajo_id"
                        label="{{ __('finanzas.combustible.campo_equipo') }}"
                        :options="$equiposDisponibles"
                        :value="(string) $equipoTrabajoId"
                        placeholder="{{ __('finanzas.combustible.campo_equipo_placeholder') }}"
                        :error="$errors->first('equipo_trabajo_id')"
                        required
                        data-ag-combustible-equipo
                    />

                    <x-atoms.select
                        name="campania_id"
                        label="{{ __('finanzas.combustible.campo_campania') }}"
                        placeholder="{{ __('finanzas.combustible.campo_campania_placeholder') }}"
                        :options="$campaniasDisponibles"
                        value="{{ $campaniaId }}"
                        help="{{ __('finanzas.combustible.campo_campania_ayuda') }}"
                        error="{{ $errors->first('campania_id') }}"
                    />

                    <x-atoms.select
                        name="recurso"
                        id="recurso"
                        label="{{ __('finanzas.combustible.campo_recurso') }}"
                        :options="$recursosDisponibles"
                        :value="(string) $recurso"
                        placeholder="{{ __('finanzas.combustible.campo_recurso_placeholder') }}"
                        help="{{ __('finanzas.combustible.campo_recurso_ayuda') }}"
                        :error="$errors->first('recurso')"
                        :disabled="$recursosDisponibles->isEmpty()"
                        required
                    />

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
