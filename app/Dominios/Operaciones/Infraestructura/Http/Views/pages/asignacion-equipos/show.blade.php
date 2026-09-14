{{--
    Page: asignacion-equipos/show (GET /panel/asignacion-equipos/{orden}, panel.asignacion-equipos.show)
    Ficha de reparto de una orden vigente (HU-70, tarea 85): resumen de
    hectáreas (lote / asignadas / restantes), equipos ya asignados y
    formulario para sumar uno nuevo. Arquetipo Detalle, §6 de
    docs/diseno/guia_pantalla_panel.md — mismo molde que
    equipos-trabajo/show.blade.php (resumen + lista + mini-formulario de alta).

    Datos esperados (ver AsignacionEquiposController::mostrar()): la cáscara
    de CascaraPanel, más:
    - $orden (OrdenAplicacion), $contratoLabel / $loteLabel (string).
    - $resumen (array{hectareas_lote: string, asignadas: string, restantes: string}).
    - $trabajosAsignados (Collection<int, Trabajo>): con `equipo_trabajo_id`
      no nulo, orden de alta.
    - $etiquetasEquipo (array<int, string>): código del equipo por id, ya
      resuelto por el controlador (ADR 0003 regla 3 — `Personal` se lee por
      `DB::table`, sin importar su modelo Eloquent).
    - $equiposDisponibles (Collection<int, string>): equipos vigentes HOY
      ({@see \App\Dominios\Personal\Contratos\LecturaEquipoTrabajo::vigentesAFecha()}),
      para el select del alta.

    El formulario de alta solo se ofrece si la orden sigue vigente Y hay
    algún equipo vigente hoy — si no, un aviso explica por qué no hay nada
    que asignar (presentación: el servidor revalida las tres guardas en
    `AsignarEquiposOrden` de cualquier forma).

    Gateada por `operaciones.orden.asignar_equipos`. Estilos en
    resources/css/pages/asignacion-equipos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $esVigente = $orden->estado->value === 'vigente';
@endphp
<x-templates.panel-shell :title="__('operaciones.asignacion_equipos.ficha_titulo', ['nro' => $orden->nro_aplicacion, 'lote' => $loteLabel])" :tema="$tema">
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
        :vista-actual="__('operaciones.asignacion_equipos.titulo')"
    >
        <div class="ag-asignacion-equipos-ficha">
            <x-atoms.button href="{{ route('panel.asignacion-equipos.index') }}" variant="text" size="sm" icon="arrow_back">
                {{ __('operaciones.asignacion_equipos.ficha_volver') }}
            </x-atoms.button>

            <x-organisms.page-header
                :title="__('operaciones.asignacion_equipos.ficha_titulo', ['nro' => $orden->nro_aplicacion, 'lote' => $loteLabel])"
                :subtitle="__('operaciones.asignacion_equipos.ficha_subtitulo', ['nro' => $orden->nro_aplicacion, 'contrato' => $contratoLabel])"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-asignacion-equipos-ficha__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('equipo_trabajo_id'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-asignacion-equipos-ficha__aviso">
                    {{ $errors->first('equipo_trabajo_id') }}
                </x-molecules.alert-strip>
            @endif

            @unless ($esVigente)
                <x-molecules.alert-strip variant="warning" icon="warning" class="ag-asignacion-equipos-ficha__aviso">
                    {{ __('operaciones.asignacion_equipos.orden_no_vigente') }}
                </x-molecules.alert-strip>
            @endunless

            <div class="ag-asignacion-equipos-ficha__resumen">
                <span class="ag-asignacion-equipos-ficha__campo">
                    <strong>{{ __('operaciones.asignacion_equipos.resumen_hectareas_lote') }}</strong>
                    {{ number_format((float) $resumen['hectareas_lote'], 2, ',', '.') }}
                </span>
                <span class="ag-asignacion-equipos-ficha__campo">
                    <strong>{{ __('operaciones.asignacion_equipos.resumen_asignadas') }}</strong>
                    {{ number_format((float) $resumen['asignadas'], 2, ',', '.') }}
                </span>
                <span class="ag-asignacion-equipos-ficha__campo">
                    <strong>{{ __('operaciones.asignacion_equipos.resumen_restantes') }}</strong>
                    {{ number_format((float) $resumen['restantes'], 2, ',', '.') }}
                </span>
            </div>

            <div class="ag-asignacion-equipos-ficha__seccion">
                <h2>{{ __('operaciones.asignacion_equipos.seccion_equipos') }}</h2>

                @if ($trabajosAsignados->isEmpty())
                    <x-molecules.alert-strip variant="info" icon="groups">
                        {{ __('operaciones.asignacion_equipos.equipos_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <div class="ag-asignacion-equipos-ficha__lista">
                        @foreach ($trabajosAsignados as $trabajo)
                            <div class="ag-asignacion-equipos-ficha__fila">
                                <span>{{ $etiquetasEquipo[$trabajo->equipo_trabajo_id] ?? "#{$trabajo->equipo_trabajo_id}" }}</span>
                                <span class="ag-asignacion-equipos-ficha__mono">{{ number_format((float) $trabajo->hectareas_declaradas, 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($esVigente)
                    @if ($equiposDisponibles->isEmpty())
                        <x-molecules.alert-strip variant="info" icon="groups">
                            {{ __('operaciones.asignacion_equipos.equipos_sin_vigentes') }}
                        </x-molecules.alert-strip>
                    @else
                        <form method="POST" action="{{ route('panel.asignacion-equipos.store', $orden) }}" class="ag-asignacion-equipos-ficha__alta">
                            @csrf

                            <x-atoms.select
                                id="alta-equipo-trabajo_id"
                                name="equipo_trabajo_id"
                                label="{{ __('operaciones.asignacion_equipos.campo_equipo') }}"
                                :options="$equiposDisponibles"
                                placeholder="{{ __('operaciones.asignacion_equipos.campo_equipo_placeholder') }}"
                                required
                            />

                            <x-atoms.input
                                type="number"
                                id="alta-equipo-hectareas"
                                name="hectareas"
                                label="{{ __('operaciones.asignacion_equipos.campo_hectareas') }}"
                                min="0.01"
                                step="0.01"
                                required
                            />

                            <x-atoms.button type="submit" variant="primary" icon="add">
                                {{ __('operaciones.asignacion_equipos.asignar_boton') }}
                            </x-atoms.button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
