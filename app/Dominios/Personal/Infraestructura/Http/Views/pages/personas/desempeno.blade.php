{{--
    Page: personas/desempeno (GET /panel/personas/{persona}/desempeno, panel.personas.desempenio)
    Ficha de desempeño de una persona (tarea 81, HU-58): "¿qué hizo esta
    persona esta campaña?" — hechos verificables por SESIÓN, nunca por equipo
    de trabajo (ADR 0015 punto 3: la pertenencia a un equipo no es
    exclusiva). SIN puntaje, ranking ni semáforo (ver "Qué NO hacer" del
    prompt de la tarea) — la pantalla muestra hechos y sus fuentes, la
    decisión de a quién contratar es de una persona. Arquetipo Detalle, mismo
    molde de filtros por GET que `equipos-trabajo/show.blade.php`.

    Datos esperados (ver PersonasController::desempenio()): la cáscara de
    CascaraPanel, más:
    - $persona (PerPersona).
    - $resultado (App\Dominios\Personal\Aplicacion\ResultadoDesempenioPersona):
      sesiones/rechazos/incidencias ya filtrados por cliente/campaña, los
      cuatro totales, y las opciones de los selects de cliente/campaña
      (->clientesDisponibles, ->campaniasDisponibles, ->clientePorCampania —
      este último alimenta el filtro dependiente de
      `resources/js/pages/personas-desempeno.js`, mismo criterio que
      `contratos-form.js`).
    - $filtros (array{desde,hasta,cliente_id,campania_id}): valores
      aplicados, para dejar el formulario con el estado tras el submit.

    Gateada por `personal.persona.desempenio` — permiso PROPIO, separado de
    `personal.persona.ver`: es información sensible sobre una persona, no la
    ve cualquiera con acceso al listado.

    Estado vacío: dos casos distintos, NO la misma pieza.
    - $sinDatosEnRango (la persona no tiene ninguna sesión/rechazo en TODO
      el rango de fechas — clientesDisponibles sale sin aplicar el filtro de
      cliente/campaña, ver ObtenerDesempenioPersona::opcionesDeFiltro):
      reemplaza totales + las tres secciones por un bloque único e ilustrado
      (ícono grande + título + detalle, `.ag-persona-desempeno__vacio`),
      mismo criterio que `seguridad::pages.dashboard._sin-secciones`.
    - Una sección puntual sin resultados PARA EL FILTRO elegido (p. ej. hay
      sesiones pero ninguna del cliente filtrado): sigue siendo
      `molecules/alert-strip`, mismo criterio que
      `comercial::pages.reportes-comerciales.index` ("sin resultados
      coincidentes").

    Estilos en resources/css/pages/personas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $opcionesCliente = collect($resultado->clientesDisponibles)->mapWithKeys(fn ($nombre, $id) => [$id => $nombre]);
    $opcionesCampania = collect($resultado->campaniasDisponibles)->mapWithKeys(fn ($codigo, $id) => [$id => $codigo]);
    $sinDatosEnRango = $opcionesCliente->isEmpty();
@endphp
<x-templates.panel-shell :title="__('personal.desempenio.titulo', ['nombre' => $persona->nombre])" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('personal.personas.titulo')"
    >
        <div class="ag-persona-desempeno">
            <x-atoms.button href="{{ route('panel.personas.index') }}" variant="text" size="sm" icon="arrow_back">
                {{ __('personal.desempenio.volver') }}
            </x-atoms.button>

            <x-organisms.page-header
                :title="__('personal.desempenio.titulo', ['nombre' => $persona->nombre])"
                :subtitle="__('personal.desempenio.subtitulo')"
            />

            <form
                method="GET"
                action="{{ route('panel.personas.desempenio', $persona) }}"
                class="ag-filtros ag-persona-desempeno__filtros"
                data-ag-desempenio-filtros
            >
                <x-atoms.date name="desde" label="{{ __('personal.desempenio.filtro_desde') }}" :value="$filtros['desde']" required />
                <x-atoms.date name="hasta" label="{{ __('personal.desempenio.filtro_hasta') }}" :value="$filtros['hasta']" required />

                <x-atoms.select
                    name="cliente_id"
                    label="{{ __('personal.desempenio.filtro_cliente') }}"
                    :options="$opcionesCliente"
                    :value="$filtros['cliente_id']"
                    placeholder="{{ __('personal.desempenio.filtro_cliente_placeholder') }}"
                    data-ag-desempenio-cliente
                />

                <x-atoms.select
                    name="campania_id"
                    label="{{ __('personal.desempenio.filtro_campania') }}"
                    :options="$opcionesCampania"
                    :value="$filtros['campania_id']"
                    placeholder="{{ __('personal.desempenio.filtro_campania_placeholder') }}"
                    data-ag-desempenio-campania
                    data-mapa-cliente-campania="{{ json_encode($resultado->clientePorCampania) }}"
                />

                <div class="ag-filtros__acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('personal.desempenio.filtrar') }}
                    </x-atoms.button>

                    <x-atoms.button href="{{ route('panel.personas.desempenio', $persona) }}" variant="text" size="md">
                        {{ __('personal.desempenio.limpiar_filtro') }}
                    </x-atoms.button>
                </div>
            </form>

            @if ($sinDatosEnRango)
                {{--
                    Estado vacío ilustrado (etapa 3, tarea 81): la persona no
                    tiene NINGUNA sesión ni rechazo en todo el rango de
                    fechas — clientesDisponibles/campaniasDisponibles salen
                    de las mismas filas (ver ObtenerDesempenioPersona::
                    opcionesDeFiltro) sin aplicar el filtro de cliente/
                    campaña, así que si está vacío las tres secciones de
                    abajo también lo estarían. Un único bloque en vez de tres
                    alert-strip repitiendo "no hay nada" — mismo criterio que
                    `seguridad::pages.dashboard._sin-secciones` (tarjeta +
                    ícono grande + título + detalle), único precedente real
                    de "estado vacío" prominente en el panel.
                --}}
                <div class="ag-persona-desempeno__vacio">
                    <x-atoms.icon name="flight_takeoff" size="lg" />
                    <h2 class="ag-persona-desempeno__vacio-titulo">{{ __('personal.desempenio.vacio_titulo') }}</h2>
                    <p class="ag-persona-desempeno__vacio-detalle">{{ __('personal.desempenio.vacio_detalle') }}</p>
                </div>
            @else
            <section class="ag-persona-desempeno__totales">
                <x-molecules.stat-card
                    :label="__('personal.desempenio.total_hectareas')"
                    icon="landscape"
                    :value="number_format((float) $resultado->hectareasAplicadas, 2, ',', '.')"
                    value-suffix="ha"
                />
                <x-molecules.stat-card
                    :label="__('personal.desempenio.total_sesiones_validadas')"
                    icon="task_alt"
                    :value="$resultado->totalSesionesValidadas"
                />
                <x-molecules.stat-card
                    :label="__('personal.desempenio.total_sesiones_rechazadas')"
                    icon="block"
                    :value="$resultado->totalSesionesRechazadas"
                />
                <x-molecules.stat-card
                    :label="__('personal.desempenio.total_incidencias')"
                    icon="report"
                    :value="$resultado->totalIncidencias"
                />
            </section>

            {{-- Sesiones --}}
            <div class="ag-persona-desempeno__seccion">
                <h2>{{ __('personal.desempenio.seccion_sesiones') }}</h2>

                @if (count($resultado->sesiones) === 0)
                    <x-molecules.alert-strip variant="info" icon="search_off">
                        {{ __('personal.desempenio.sesiones_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <div class="ag-persona-desempeno__tabla" role="table">
                        <div class="ag-persona-desempeno__head" role="row">
                            <span role="columnheader">{{ __('personal.desempenio.col_fecha') }}</span>
                            <span role="columnheader">{{ __('personal.desempenio.col_rol') }}</span>
                            <span role="columnheader">{{ __('personal.desempenio.col_cliente') }}</span>
                            <span role="columnheader">{{ __('personal.desempenio.col_campania') }}</span>
                            <span role="columnheader">{{ __('personal.desempenio.col_lote') }}</span>
                            <span role="columnheader">{{ __('personal.desempenio.col_hectareas') }}</span>
                            <span role="columnheader">{{ __('personal.desempenio.col_estado') }}</span>
                        </div>

                        @foreach ($resultado->sesiones as $sesion)
                            <div class="ag-persona-desempeno__fila" role="row">
                                <span role="cell">{{ \Illuminate\Support\Carbon::parse($sesion->fecha)->format('d/m/Y') }}</span>
                                <span role="cell">{{ __('personal.rol_equipo.'.$sesion->rol) }}</span>
                                <span role="cell">{{ $sesion->clienteNombre }}</span>
                                <span role="cell">{{ $sesion->campaniaCodigo ?? __('personal.desempenio.sin_campania') }}</span>
                                <span role="cell">{{ $sesion->loteCodigo }} — {{ $sesion->campoNombre }}</span>
                                <span role="cell">{{ number_format((float) $sesion->hectareasDeclaradas, 2, ',', '.') }}</span>
                                <span role="cell">
                                    <x-atoms.badge :variant="$sesion->estado === 'validado' ? 'success' : 'warning'">
                                        {{ __('personal.desempenio.estado_sesion.'.$sesion->estado) }}
                                    </x-atoms.badge>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Rechazos --}}
            <div class="ag-persona-desempeno__seccion">
                <h2>{{ __('personal.desempenio.seccion_rechazos') }}</h2>

                @if (count($resultado->rechazos) === 0)
                    <x-molecules.alert-strip variant="info" icon="check_circle">
                        {{ __('personal.desempenio.rechazos_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <div class="ag-persona-desempeno__lista">
                        @foreach ($resultado->rechazos as $rechazo)
                            <div class="ag-persona-desempeno__rechazo">
                                <div class="ag-persona-desempeno__rechazo-cabecera">
                                    <span>{{ \Illuminate\Support\Carbon::parse($rechazo->fecha)->format('d/m/Y') }}</span>
                                    <span>{{ __('personal.rol_equipo.'.$rechazo->rol) }}</span>
                                    <span>{{ $rechazo->clienteNombre }}</span>
                                    <span>{{ $rechazo->campaniaCodigo ?? __('personal.desempenio.sin_campania') }}</span>
                                    <span>{{ $rechazo->loteCodigo }} — {{ $rechazo->campoNombre }}</span>
                                    <span>{{ number_format((float) $rechazo->hectareasDeclaradas, 2, ',', '.') }} ha</span>
                                </div>
                                <p class="ag-persona-desempeno__rechazo-motivo">
                                    <strong>{{ __('personal.desempenio.col_motivo') }}:</strong> {{ $rechazo->motivo }}
                                </p>
                                <p class="ag-persona-desempeno__rechazo-por">
                                    <strong>{{ __('personal.desempenio.col_rechazado_por') }}:</strong> {{ $rechazo->rechazadoPorNombre }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Incidencias --}}
            <div class="ag-persona-desempeno__seccion">
                <h2>{{ __('personal.desempenio.seccion_incidencias') }}</h2>

                @if (count($resultado->incidencias) === 0)
                    <x-molecules.alert-strip variant="info" icon="report">
                        {{ __('personal.desempenio.incidencias_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <div class="ag-persona-desempeno__tabla" role="table">
                        <div class="ag-persona-desempeno__head ag-persona-desempeno__head--incidencias" role="row">
                            <span role="columnheader">{{ __('personal.desempenio.col_fecha') }}</span>
                            <span role="columnheader">{{ __('personal.desempenio.col_tipo') }}</span>
                            <span role="columnheader">{{ __('personal.desempenio.col_descripcion') }}</span>
                        </div>

                        @foreach ($resultado->incidencias as $incidencia)
                            <div class="ag-persona-desempeno__fila ag-persona-desempeno__fila--incidencias" role="row">
                                <span role="cell">{{ \Illuminate\Support\Carbon::parse($incidencia->fecha)->format('d/m/Y') }}</span>
                                <span role="cell">{{ __('personal.desempenio.incidencia_tipo.'.$incidencia->tipo) }}</span>
                                <span role="cell">{{ $incidencia->descripcion ?? __('personal.desempenio.sin_descripcion') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
