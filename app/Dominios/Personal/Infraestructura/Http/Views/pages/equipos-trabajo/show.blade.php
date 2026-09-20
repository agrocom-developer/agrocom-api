{{--
    Page: equipos-trabajo/show (GET /panel/equipos-trabajo/{equipoTrabajo}, panel.equipos-trabajo.show)
    Ficha de un equipo de trabajo (tarea 72, HU-49, ADR 0015 punto 3):
    integrantes y recursos VIGENTES A UNA FECHA elegida (selector, default
    hoy) — tiene que poder responder "quiénes lo integraban el 14 de marzo",
    no solo "quiénes lo integran hoy". Arquetipo Detalle, §6 de
    docs/diseno/guia_pantalla_panel.md.

    Datos esperados (ver EquiposTrabajoController::show()): la cáscara de
    CascaraPanel, más:
    - $equipo (EquipoTrabajo), $nombreBase (string).
    - $fecha (string, ISO): la fecha consultada.
    - $integrantes (list<DatosIntegranteEquipo>), $recursos (list<DatosRecursoEquipo>):
      vigentes a `$fecha` (contrato `LecturaEquipoTrabajo`).
    - $etiquetasRecurso (array<string, string>): clave "{tipo}:{id}" =>
      identificador, ya resuelto por el controlador (`DB::table`, ADR 0003
      regla 3 — un recurso cruza a `Operaciones`/`Mantenimiento`).
    - $personasDisponibles, $dronesDisponibles, $vehiculosDisponibles,
      $generadoresDisponibles (Collection<int, string>): opciones de los
      selects de alta.
    - $roles (list<RolEquipo>), $tiposRecurso (list<RecursoTipoEquipo>).
    - $puedeEditar (bool): gatea los formularios de alta/finalizar
      (presentación, no autorización — el servidor revalida en el
      controlador).

    Cada formulario que muta (asignar/finalizar integrante o recurso) lleva
    un campo oculto `fecha` con la fecha consultada: así, tras el redirect,
    la ficha vuelve a mostrarse en la MISMA fecha que se estaba mirando, no
    salta a "hoy" — ver `EquiposTrabajoController::volverAFicha()`.

    El aviso de solapamiento (persona/recurso ya vigente en OTRO equipo, ADR
    0015 punto 3: se guarda igual) llega como `session('aviso')`, distinto de
    `session('estado')` (éxito simple) — variantes `warning` e `info`
    respectivamente.

    Gateada por `personal.equipo_trabajo.ver`. Estilos en
    resources/css/pages/equipos-trabajo.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $opcionesPorTipo = [
        'dron' => $dronesDisponibles,
        'vehiculo' => $vehiculosDisponibles,
        'generador' => $generadoresDisponibles,
    ];
    $opcionesRol = collect($roles)->mapWithKeys(
        fn ($opcion) => [$opcion->value => __('personal.rol_equipo.'.$opcion->value)]
    );
@endphp
<x-templates.panel-shell :title="__('personal.equipos_trabajo.ficha_titulo', ['codigo' => $equipo->codigo])" :tema="$tema">
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
        :vista-actual="__('personal.equipos_trabajo.titulo')"
    >
        <div class="ag-equipos-trabajo-ficha">
            @if (session('navegacion_pila', []) !== [])
                {{-- Memento de navegación: la ficha es donde se arma la escuadra
                     recién creada (piloto, ayudante, dron); desde acá se vuelve
                     al alta de Orden de Trabajo que la pidió. --}}
                <div>
                    <x-molecules.boton-volver
                        :href="route('panel.equipos-trabajo.index')"
                        :label="__('personal.equipos_trabajo.ficha_volver')"
                    />
                </div>
            @else
                <x-atoms.button :href="route('panel.equipos-trabajo.index')" variant="text" size="sm" icon="arrow_back">
                    {{ __('personal.equipos_trabajo.ficha_volver') }}
                </x-atoms.button>
            @endif

            <x-organisms.page-header
                :title="__('personal.equipos_trabajo.ficha_titulo', ['codigo' => $equipo->codigo])"
                :subtitle="__('personal.equipos_trabajo.ficha_subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-equipos-trabajo-ficha__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if (session('aviso'))
                <x-molecules.alert-strip variant="warning" icon="warning" class="ag-equipos-trabajo-ficha__aviso">
                    {{ session('aviso') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('persona_id') || $errors->has('recurso_id') || $errors->has('hasta'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-equipos-trabajo-ficha__aviso">
                    {{ $errors->first('persona_id') ?: $errors->first('recurso_id') ?: $errors->first('hasta') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-equipos-trabajo-ficha__resumen">
                <span class="ag-equipos-trabajo-ficha__campo">
                    <strong>{{ __('personal.equipos_trabajo.ficha_campo_base') }}</strong>
                    {{ $nombreBase }}
                </span>
                <span class="ag-equipos-trabajo-ficha__campo">
                    <strong>{{ __('personal.equipos_trabajo.ficha_campo_vigencia') }}</strong>
                    {{ $equipo->desde->format('d/m/Y') }} – {{ $equipo->hasta?->format('d/m/Y') ?? __('personal.equipos_trabajo.vigente') }}
                </span>
                <span class="ag-equipos-trabajo-ficha__campo">
                    <strong>{{ __('personal.equipos_trabajo.col_estado') }}</strong>
                    {{ __('personal.estado.'.$equipo->estado->value) }}
                </span>
            </div>

            <form method="GET" action="{{ route('panel.equipos-trabajo.show', $equipo) }}" class="ag-equipos-trabajo-ficha__selector-fecha">
                <x-atoms.date
                    name="fecha"
                    :label="__('personal.equipos_trabajo.ficha_selector_fecha')"
                    :value="$fecha"
                    required
                />
                <x-atoms.button type="submit" variant="outline" icon="search">
                    {{ __('personal.equipos_trabajo.ficha_consultar') }}
                </x-atoms.button>
            </form>

            {{-- Integrantes --}}
            <div class="ag-equipos-trabajo-ficha__seccion">
                <h2>{{ __('personal.equipos_trabajo.ficha_seccion_integrantes') }}</h2>

                @if (count($integrantes) === 0)
                    <x-molecules.alert-strip variant="info" icon="groups">
                        {{ __('personal.equipos_trabajo.ficha_integrantes_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <div class="ag-equipos-trabajo-ficha__lista">
                        @foreach ($integrantes as $integrante)
                            <div class="ag-equipos-trabajo-ficha__fila">
                                <span>{{ $integrante->nombrePersona }}</span>
                                <span>{{ __('personal.rol_equipo.'.$integrante->rolEquipo) }}</span>
                                <span>
                                    {{ \Illuminate\Support\Carbon::parse($integrante->desde)->format('d/m/Y') }}
                                    –
                                    {{ $integrante->hasta ? \Illuminate\Support\Carbon::parse($integrante->hasta)->format('d/m/Y') : __('personal.equipos_trabajo.vigente') }}
                                </span>
                                @if ($puedeEditar)
                                    <form
                                        method="POST"
                                        action="{{ route('panel.equipos-trabajo.integrantes.destroy', [$equipo, $integrante->id]) }}"
                                        class="ag-equipos-trabajo-ficha__finalizar"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="fecha" value="{{ $fecha }}">
                                        <x-atoms.date id="hasta-integrante-{{ $integrante->id }}" name="hasta" :value="now()->toDateString()" required />
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm">
                                            {{ __('personal.equipos_trabajo.ficha_finalizar') }}
                                        </x-atoms.button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($puedeEditar)
                    <form method="POST" action="{{ route('panel.equipos-trabajo.integrantes.store', $equipo) }}" class="ag-equipos-trabajo-ficha__alta">
                        @csrf
                        <input type="hidden" name="fecha" value="{{ $fecha }}">

                        <x-atoms.select
                            id="alta-integrante-persona_id"
                            name="persona_id"
                            :label="__('personal.equipos_trabajo.ficha_campo_persona')"
                            :options="$personasDisponibles"
                            :placeholder="__('personal.equipos_trabajo.ficha_campo_persona_placeholder')"
                            required
                        />

                        <x-atoms.select
                            id="alta-integrante-rol_equipo"
                            name="rol_equipo"
                            :label="__('personal.equipos_trabajo.ficha_campo_rol')"
                            :options="$opcionesRol"
                            required
                        />

                        <x-atoms.date id="alta-integrante-desde" name="desde" :label="__('personal.equipos_trabajo.campo_desde')" :value="$fecha" required />
                        <x-atoms.date id="alta-integrante-hasta" name="hasta" :label="__('personal.equipos_trabajo.campo_hasta')" />

                        <x-atoms.button type="submit" variant="primary" icon="add">
                            {{ __('personal.equipos_trabajo.ficha_asignar_integrante') }}
                        </x-atoms.button>
                    </form>
                @endif
            </div>

            {{-- Recursos --}}
            <div class="ag-equipos-trabajo-ficha__seccion">
                <h2>{{ __('personal.equipos_trabajo.ficha_seccion_recursos') }}</h2>

                @if (count($recursos) === 0)
                    <x-molecules.alert-strip variant="info" icon="precision_manufacturing">
                        {{ __('personal.equipos_trabajo.ficha_recursos_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <div class="ag-equipos-trabajo-ficha__lista">
                        @foreach ($recursos as $recurso)
                            <div class="ag-equipos-trabajo-ficha__fila">
                                <span>
                                    {{ __('personal.recurso_tipo.'.$recurso->recursoTipo) }}
                                    —
                                    {{ $etiquetasRecurso["{$recurso->recursoTipo}:{$recurso->recursoId}"] ?? "#{$recurso->recursoId}" }}
                                </span>
                                <span></span>
                                <span>
                                    {{ \Illuminate\Support\Carbon::parse($recurso->desde)->format('d/m/Y') }}
                                    –
                                    {{ $recurso->hasta ? \Illuminate\Support\Carbon::parse($recurso->hasta)->format('d/m/Y') : __('personal.equipos_trabajo.vigente') }}
                                </span>
                                @if ($puedeEditar)
                                    <form
                                        method="POST"
                                        action="{{ route('panel.equipos-trabajo.recursos.destroy', [$equipo, $recurso->id]) }}"
                                        class="ag-equipos-trabajo-ficha__finalizar"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="fecha" value="{{ $fecha }}">
                                        <x-atoms.date id="hasta-recurso-{{ $recurso->id }}" name="hasta" :value="now()->toDateString()" required />
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm">
                                            {{ __('personal.equipos_trabajo.ficha_finalizar') }}
                                        </x-atoms.button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($puedeEditar)
                    @foreach ($tiposRecurso as $tipo)
                        <form method="POST" action="{{ route('panel.equipos-trabajo.recursos.store', $equipo) }}" class="ag-equipos-trabajo-ficha__alta">
                            @csrf
                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                            <input type="hidden" name="recurso_tipo" value="{{ $tipo->value }}">

                            <x-atoms.select
                                id="alta-recurso-{{ $tipo->value }}-recurso_id"
                                name="recurso_id"
                                :label="__('personal.recurso_tipo.'.$tipo->value)"
                                :options="$opcionesPorTipo[$tipo->value]"
                                :placeholder="__('personal.equipos_trabajo.ficha_campo_recurso_placeholder')"
                                required
                            />

                            <x-atoms.date id="alta-recurso-{{ $tipo->value }}-desde" name="desde" :label="__('personal.equipos_trabajo.campo_desde')" :value="$fecha" required />
                            <x-atoms.date id="alta-recurso-{{ $tipo->value }}-hasta" name="hasta" :label="__('personal.equipos_trabajo.campo_hasta')" />

                            <x-atoms.button type="submit" variant="outline" icon="add">
                                {{ __('personal.equipos_trabajo.ficha_asignar_recurso') }}
                            </x-atoms.button>
                        </form>
                    @endforeach
                @endif
            </div>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
