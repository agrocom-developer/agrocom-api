{{--
    Page: trabajos/edit (GET /panel/trabajos/detalle/{trabajo}/editar, panel.trabajos.detalle-editar)
    Edición de UN trabajo puntual (equipo×lote) — lote, equipo, hectáreas y
    turno+horas (HU-93, tarea 108; homogeneizada en la tarea 114). Arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md: pasos de estado bajo
    la cabecera, `form-layout` con aside de resumen relacionado y barra de
    acciones al pie.

    Datos esperados (ver TrabajosController::edit()): la cáscara de
    CascaraPanel, más:
    - $trabajo (Trabajo): el modelo a editar.
    - $lotesDisponibles (array<int, string>): SOLO los lotes de la orden de
      este trabajo (validado por ActualizarTrabajoRequest).
    - $equiposDisponibles (Collection<int, string>): equipos vigentes + el
      actual del trabajo si ya no es vigente.
    - $pasosEstado (list<array{...}>): los pasos de `molecules/step-arrow`
      (`PasosDeTrabajo`). Ninguno es accionable y ninguno abre un modal: el
      panel no cierra un trabajo — eso lo registra el piloto desde la app de
      campo (invariante 7; ver el docblock de `PasosDeTrabajo`). Por eso esta
      ficha NO tiene un partial `_cambio-estado`: no hay `<form>` que poner.
    - $ayudaEstado (string|null): el párrafo bajo los pasos.
    - $relacionado (array{aviso: ?array, tarjetas: list<array>}): el aside.
      Mientras el trabajo siga abierto trae solo el `aviso` (plan §3.5).

    `estado` NUNCA es un campo de este formulario (invariante 7): los pasos
    solo lo MUESTRAN.

    Gateada por `operaciones.trabajo.editar`, verificado server-side en el
    controlador. Tras guardar se queda en esta misma ficha con su aviso de
    éxito (§6.3.2). Estilos en resources/css/pages/trabajos.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $turnoActual = $trabajo->turno?->value;

    // `turno_hora_inicio`/`turno_hora_fin` vienen casteados a fecha
    // (`datetime:H:i` en `Trabajo`), y un `<input type="time">` solo acepta
    // «HH:MM»: sin recortarlo, el navegador descarta el valor y el campo se
    // dibuja vacío aunque el trabajo tenga su horario cargado — así, guardar
    // sin tocar nada rebotaba contra «Ingresa la hora de inicio y de fin».
    $horaTurno = function (mixed $valor): ?string {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('H:i');
        }

        return $valor === null || $valor === '' ? null : substr((string) $valor, 0, 5);
    };
@endphp
<x-templates.panel-shell :title="__('operaciones.trabajos.editar_titulo', ['id' => $trabajo->id])" :tema="$tema">
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
        :vista-actual="__('operaciones.trabajos.titulo')"
    >
        <form
            method="POST"
            action="{{ route('panel.trabajos.detalle-actualizar', $trabajo) }}"
            class="ag-trabajos-edit"
            novalidate
        >
            @csrf
            @method('PUT')

            <x-organisms.page-header
                :title="__('operaciones.trabajos.editar_titulo', ['id' => $trabajo->id])"
                :subtitle="__('operaciones.trabajos.editar_subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button :href="route('panel.trabajos.detalle', $trabajo)" variant="outline" icon="arrow_back">
                        {{ __('operaciones.trabajos.volver_al_detalle') }}
                    </x-atoms.button>
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

            {{-- Estado del trabajo como pasos: informativos, sin modal detrás. --}}
            <x-molecules.step-arrow
                :steps="$pasosEstado"
                :label="__('operaciones.trabajos.estado_pasos_aria')"
                :help="$ayudaEstado"
            />

            <x-molecules.form-layout>
                <x-slot:aside>
                    @if ($relacionado['aviso'] !== null)
                        <x-molecules.empty-state
                            icon="schedule"
                            :title="$relacionado['aviso']['titulo']"
                            :detail="$relacionado['aviso']['detalle']"
                        />
                    @endif

                    @foreach ($relacionado['tarjetas'] as $tarjeta)
                        <x-molecules.summary-card :title="$tarjeta['titulo']" :items="$tarjeta['items']">
                            @if ($tarjeta['acciones'] !== [])
                                <x-slot:action>
                                    @foreach ($tarjeta['acciones'] as $accion)
                                        <x-atoms.button :href="$accion['href']" variant="outline" :icon="$accion['icono']" block>
                                            {{ $accion['label'] }}
                                        </x-atoms.button>
                                    @endforeach
                                </x-slot:action>
                            @endif
                        </x-molecules.summary-card>
                    @endforeach
                </x-slot:aside>

                <x-molecules.form-section
                    :title="__('operaciones.trabajos.seccion_datos')"
                    :count="__('operaciones.trabajos.campos_contador', ['cantidad' => 6])"
                >
                    <x-atoms.select
                        name="lote_id"
                        id="lote_id"
                        :label="__('operaciones.trabajos.campo_lote')"
                        :options="$lotesDisponibles"
                        :value="old('lote_id', $trabajo->lote_id)"
                        :placeholder="__('operaciones.asignacion_equipos.campo_lote_placeholder')"
                        :help="__('operaciones.trabajos.campo_lote_ayuda')"
                        required
                        :error="$errors->first('lote_id')"
                    />

                    <x-atoms.select
                        name="equipo_trabajo_id"
                        id="equipo_trabajo_id"
                        :label="__('operaciones.trabajos.campo_equipo')"
                        :options="$equiposDisponibles"
                        :value="old('equipo_trabajo_id', $trabajo->equipo_trabajo_id)"
                        :placeholder="__('operaciones.trabajos.campo_equipo_sin_asignar')"
                        :help="__('operaciones.trabajos.campo_equipo_ayuda')"
                        :error="$errors->first('equipo_trabajo_id')"
                    />

                    <x-atoms.input
                        type="number"
                        name="hectareas_declaradas"
                        id="hectareas_declaradas"
                        :label="__('operaciones.trabajos.campo_hectareas')"
                        :value="old('hectareas_declaradas', $trabajo->hectareas_declaradas)"
                        min="0.01"
                        step="0.01"
                        :help="__('operaciones.trabajos.campo_hectareas_ayuda')"
                        required
                        :error="$errors->first('hectareas_declaradas')"
                    />

                    <x-atoms.select
                        name="turno"
                        id="turno"
                        :label="__('operaciones.trabajos.campo_turno')"
                        :options="[
                            'manana' => __('operaciones.asignacion_equipos.turno_manana'),
                            'noche' => __('operaciones.asignacion_equipos.turno_noche'),
                            'todo_el_dia' => __('operaciones.asignacion_equipos.turno_todo_el_dia'),
                        ]"
                        :value="old('turno', $turnoActual)"
                        :placeholder="__('operaciones.asignacion_equipos.campo_turno')"
                        :help="__('operaciones.trabajos.campo_turno_ayuda')"
                        :error="$errors->first('turno')"
                    />

                    <x-atoms.input
                        type="time"
                        name="turno_hora_inicio"
                        id="turno_hora_inicio"
                        :label="__('operaciones.asignacion_equipos.campo_turno_hora_inicio')"
                        :value="old('turno_hora_inicio', $horaTurno($trabajo->turno_hora_inicio))"
                        :error="$errors->first('turno_hora_inicio')"
                    />

                    <x-atoms.input
                        type="time"
                        name="turno_hora_fin"
                        id="turno_hora_fin"
                        :label="__('operaciones.asignacion_equipos.campo_turno_hora_fin')"
                        :value="old('turno_hora_fin', $horaTurno($trabajo->turno_hora_fin))"
                        :error="$errors->first('turno_hora_fin')"
                    />
                </x-molecules.form-section>

                {{-- Slot `actions`: el organismo no pinta el slot por defecto — con los
                     botones sueltos, «Guardar» no se dibujaba (corregido el 19/9/2026). --}}
                <x-organisms.form-actions-bar>
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.trabajos.detalle', $trabajo)" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary" icon="check">
                            {{ __('operaciones.trabajos.editar') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </x-molecules.form-layout>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
