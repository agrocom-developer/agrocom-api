{{--
    Page: asignacion-equipos/show (GET /panel/asignacion-equipos/{orden}, panel.asignacion-equipos.show)
    Ficha de reparto de una orden vigente (HU-70, tarea 85; rediseñada por
    HU-92, tarea 107 para N lotes/N equipos): resumen por lote (solicitadas /
    asignadas / restantes), equipos ya asignados y un ÚNICO formulario que
    confirma en bloque el reparto completo — con 1 equipo, viene pre-cargado
    con todos los lotes de la orden y sus hectáreas restantes; con 2+, el
    jefe de campo agrega equipos y, en cada uno, elige qué lotes le tocan
    (selección múltiple) y sus hectáreas. Arquetipo Detalle, §6 de
    docs/diseno/guia_pantalla_panel.md.

    Datos esperados (ver AsignacionEquiposController::mostrar()): la cáscara
    de CascaraPanel, más:
    - $orden (OrdenAplicacion), $contratoLabel (string).
    - $resumenTotal (array{hectareas_lote: string, asignadas: string, restantes: string}):
      suma de todos los lotes de la orden.
    - $resumenPorLote (list<array{lote_id: int, hectareas_solicitadas: string, asignadas: string, restantes: string}>).
    - $trabajosAsignados (Collection<int, Trabajo>): con `equipo_trabajo_id`
      no nulo, orden de alta.
    - $etiquetasEquipo / $etiquetasLote (array<int, string>): ya resueltos
      por el controlador (ADR 0003 regla 3 — `Personal`/`Comercial` se leen
      por `DB::table`, sin importar sus modelos Eloquent). `$etiquetasLote`
      son SOLO los lotes de esta orden.
    - $equiposDisponibles (Collection<int, string>): equipos vigentes HOY.
    - $equiposIniciales (list<array{equipo_trabajo_id?: int, lotes: list<array{lote_id?: int, hectareas?: string}>}>):
      una fila con todos los lotes de restantes > 0 pre-cargados (o `old('equipos')`
      tras un error de validación).

    El formulario solo se ofrece si la orden sigue vigente Y hay algún equipo
    vigente hoy — si no, un aviso explica por qué no hay nada que asignar
    (presentación: el servidor revalida las guardas en `AsignarEquiposOrden`
    de cualquier forma).

    Gateada por `operaciones.orden.asignar_equipos`. Estilos en
    resources/css/pages/asignacion-equipos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $esVigente = $orden->estado->value === 'vigente';
@endphp
<x-templates.panel-shell :title="__('operaciones.asignacion_equipos.ficha_titulo', ['nro' => $orden->nro_aplicacion])" :tema="$tema">
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
                :title="__('operaciones.asignacion_equipos.ficha_titulo', ['nro' => $orden->nro_aplicacion])"
                :subtitle="__('operaciones.asignacion_equipos.ficha_subtitulo', ['nro' => $orden->nro_aplicacion, 'contrato' => $contratoLabel])"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-asignacion-equipos-ficha__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('equipos'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-asignacion-equipos-ficha__aviso">
                    {{ $errors->first('equipos') }}
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
                    {{ number_format((float) $resumenTotal['hectareas_lote'], 2, ',', '.') }}
                </span>
                <span class="ag-asignacion-equipos-ficha__campo">
                    <strong>{{ __('operaciones.asignacion_equipos.resumen_asignadas') }}</strong>
                    {{ number_format((float) $resumenTotal['asignadas'], 2, ',', '.') }}
                </span>
                <span class="ag-asignacion-equipos-ficha__campo">
                    <strong>{{ __('operaciones.asignacion_equipos.resumen_restantes') }}</strong>
                    {{ number_format((float) $resumenTotal['restantes'], 2, ',', '.') }}
                </span>
            </div>

            <div class="ag-asignacion-equipos-ficha__seccion">
                <h2>{{ __('operaciones.asignacion_equipos.seccion_lotes') }}</h2>

                <div class="ag-asignacion-equipos-ficha__lote-lista">
                    @foreach ($resumenPorLote as $fila)
                        <div class="ag-asignacion-equipos-ficha__lote-linea">
                            <span>{{ $etiquetasLote[$fila['lote_id']] ?? "#{$fila['lote_id']}" }}</span>
                            <span class="ag-asignacion-equipos-ficha__mono">{{ number_format((float) $fila['hectareas_solicitadas'], 2, ',', '.') }}</span>
                            <span class="ag-asignacion-equipos-ficha__mono">{{ number_format((float) $fila['asignadas'], 2, ',', '.') }}</span>
                            <span class="ag-asignacion-equipos-ficha__mono">{{ number_format((float) $fila['restantes'], 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
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
                                <span>{{ $etiquetasLote[$trabajo->lote_id] ?? "#{$trabajo->lote_id}" }}</span>
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
                        <form
                            method="POST"
                            action="{{ route('panel.asignacion-equipos.store', $orden) }}"
                            class="ag-asignacion-equipos-ficha__alta"
                            data-ag-asignacion-equipos-form
                        >
                            @csrf

                            <div data-ag-equipos-lista>
                                @foreach ($equiposIniciales as $indiceEquipo => $equipo)
                                    @include('operaciones::pages.asignacion-equipos._equipo-bloque', [
                                        'indiceEquipo' => $indiceEquipo,
                                        'lotesFila' => $equipo['lotes'] ?? [[]],
                                        'mostrarQuitarEquipo' => count($equiposIniciales) > 1,
                                    ])
                                @endforeach
                            </div>

                            <x-atoms.button type="button" variant="outline" icon="add" data-ag-equipos-agregar>
                                {{ __('operaciones.asignacion_equipos.equipo_agregar') }}
                            </x-atoms.button>

                            {{-- Plantilla clonable del NIVEL EXTERNO (equipos):
                                 `asignacion-equipos-form.js` reemplaza
                                 `__INDICE_EQUIPO__` al clonar, y arranca sin
                                 lotes pre-cargados (el jefe de campo elige a
                                 mano en un reparto de 2+ equipos). --}}
                            <template data-ag-equipo-template>
                                @include('operaciones::pages.asignacion-equipos._equipo-bloque', [
                                    'indiceEquipo' => '__INDICE_EQUIPO__',
                                    'lotesFila' => [[]],
                                    'mostrarQuitarEquipo' => true,
                                ])
                            </template>

                            <x-atoms.button type="submit" variant="primary" icon="check">
                                {{ __('operaciones.asignacion_equipos.asignar_boton') }}
                            </x-atoms.button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
