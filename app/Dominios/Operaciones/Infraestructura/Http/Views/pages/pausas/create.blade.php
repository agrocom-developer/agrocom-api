{{--
    Page: pausas/create (GET /panel/pausas/crear, panel.pausas.create)
    Alta de una pausa con causa atribuible (HU-44, tarea 58) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Sin partial
    `_formulario` compartido con una edición: no existe caso de uso de
    edición — este archivo ES el formulario completo (mismo criterio que
    `gastos/create.blade.php`).

    Homogeneizado con el patrón de Estadías (tarea 113): `form-layout` sin
    aside (no hay ficha de edición, así que no hay resumen relacionado),
    fecha con `atoms/date` y el rango horario con `atoms/time-range`. Tras
    guardar vuelve al LISTADO con su aviso (no hay `edit()` al que volver,
    §6.3.2); el aviso de éxito se pinta igual acá por si el formulario
    vuelve a mostrarse con uno.

    Datos esperados (ver PausasController::create()): la cáscara de
    CascaraPanel, más:
    - $sesionesDisponibles (Collection<int, string>): id => etiqueta, para
      el selector de sesión (últimas 100).
    - $opcionesCausa (array<string, string>): valor => etiqueta de cada causa.

    Estilos en resources/css/pages/pausas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $sesionId = old('sesion_id', '');
    $causa = old('causa', '');
    $fecha = old('fecha', '');
    $horaInicio = old('hora_inicio', '');
    $horaFin = old('hora_fin', '');
@endphp

<x-templates.panel-shell :title="__('operaciones.pausas.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('operaciones.pausas.titulo_crear')"
    >
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
                    <x-molecules.boton-volver
                        :href="route('panel.pausas.index')"
                        :label="__('operaciones.pausas.volver')"
                    />
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.form-layout>
                <x-molecules.form-section
                    :title="__('operaciones.pausas.seccion_datos')"
                    :count="__('operaciones.pausas.campos_contador', ['cantidad' => 4])"
                >
                    <x-atoms.select
                        name="sesion_id"
                        id="sesion_id"
                        :label="__('operaciones.pausas.campo_sesion')"
                        :options="$sesionesDisponibles"
                        :value="$sesionId"
                        :placeholder="__('operaciones.pausas.campo_sesion_placeholder')"
                        required
                        :error="$errors->first('sesion_id')"
                    />

                    <x-atoms.select
                        name="causa"
                        id="causa"
                        :label="__('operaciones.pausas.campo_causa')"
                        :options="$opcionesCausa"
                        :value="$causa"
                        :placeholder="__('operaciones.pausas.campo_causa_placeholder')"
                        required
                        :error="$errors->first('causa')"
                    />

                    <x-atoms.date
                        name="fecha"
                        id="fecha"
                        :label="__('operaciones.pausas.campo_fecha')"
                        :value="$fecha"
                        :placeholder="__('operaciones.pausas.campo_fecha_placeholder')"
                        required
                        :error="$errors->first('fecha')"
                    />

                    <x-atoms.time-range
                        name-start="hora_inicio"
                        name-end="hora_fin"
                        id="horario"
                        :label="__('operaciones.pausas.campo_horario')"
                        :value-start="$horaInicio"
                        :value-end="$horaFin"
                        :help="__('operaciones.pausas.campo_horario_ayuda')"
                        :error="$errors->first('hora_inicio') ?: $errors->first('hora_fin')"
                        required
                    />
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('operaciones.pausas.estado_form')">
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.pausas.index')" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary">
                            {{ __('ui.action.save') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </x-molecules.form-layout>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
