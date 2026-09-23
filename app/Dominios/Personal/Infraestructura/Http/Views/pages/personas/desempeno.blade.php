{{--
    Page: personas/desempeno (GET /panel/personas/{persona}/desempeno, panel.personas.desempenio)
    Ficha de desempeño de una persona (tarea 81, HU-58; homogeneizada al
    arquetipo Detalle en la tarea 125, §6.4 de docs/diseno/guia_pantalla_panel.md,
    mismo molde que `personal::pages.cuadrillas.show`): "¿qué hizo esta
    persona esta campaña?" — hechos verificables por SESIÓN, nunca por equipo
    de trabajo (ADR 0015 punto 3: la pertenencia a un equipo no es
    exclusiva). SIN puntaje, ranking ni semáforo (regla de la tarea 81, sigue
    vigente) — la pantalla muestra hechos y sus fuentes, la decisión de a
    quién contratar es de una persona. Sin acciones en la cabecera: no existe
    ninguna que ofrecer acá.

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
    - $vinculos (list): aside "Relacionado" — ficha de la persona, cuadrillas
      que integra hoy y, solo si es la propia persona del usuario
      autenticado, sus devengos. Sin timeline: no hay bitácora antes/después
      todavía (invariante 9, pendiente) que reconstruir para esta ficha.

    Gateada por `personal.persona.desempenio` — permiso PROPIO, separado de
    `personal.persona.ver`: es información sensible sobre una persona, no la
    ve cualquiera con acceso al listado.

    Estado vacío: dos casos distintos, NO la misma pieza.
    - $sinDatosEnRango (la persona no tiene ninguna sesión/rechazo en TODO
      el rango de fechas — clientesDisponibles sale sin aplicar el filtro de
      cliente/campaña, ver ObtenerDesempenioPersona::opcionesDeFiltro):
      reemplaza el KPI y las tres secciones por un bloque único e ilustrado
      (ícono grande + título + detalle), mismo criterio que
      `seguridad::pages.dashboard._sin-secciones`. El aside "Relacionado"
      sigue mostrándose: no depende de que haya sesiones.
    - Una sección puntual sin resultados PARA EL FILTRO elegido (p. ej. hay
      sesiones pero ninguna del cliente filtrado): `molecules/empty-state`
      dentro de su propia `form-section`, mismo criterio que
      `operaciones::pages.trabajos.show`.

    Estilos en resources/css/pages/personas.css y
    resources/css/pages/detalle.css (arquetipo Detalle) — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $opcionesCliente = collect($resultado->clientesDisponibles)->mapWithKeys(fn ($nombre, $id) => [$id => $nombre]);
    $opcionesCampania = collect($resultado->campaniasDisponibles)->mapWithKeys(fn ($codigo, $id) => [$id => $codigo]);
    $sinDatosEnRango = $opcionesCliente->isEmpty();
    $filtrosActivos = collect(['cliente_id', 'campania_id'])->filter(fn ($campo) => $filtros[$campo] !== null)->count();
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
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="__('personal.desempenio.titulo', ['nombre' => $persona->nombre])"
                :subtitle="__('personal.roles.'.$persona->rol->value)"
            >
                <x-slot:actions>
                    <x-molecules.boton-volver
                        :href="route('panel.personas.edit', $persona)"
                        :label="__('personal.desempenio.volver')"
                    />
                </x-slot:actions>
            </x-organisms.page-header>

            <div class="ag-table-toolbar">
                <x-organisms.filter-panel
                    :action="route('panel.personas.desempenio', $persona)"
                    :active-count="$filtrosActivos"
                    data-ag-desempenio-filtros
                >
                    <x-atoms.date name="desde" :label="__('personal.desempenio.filtro_desde')" :value="$filtros['desde']" required />
                    <x-atoms.date name="hasta" :label="__('personal.desempenio.filtro_hasta')" :value="$filtros['hasta']" required />

                    <x-atoms.select
                        name="cliente_id"
                        :label="__('personal.desempenio.filtro_cliente')"
                        :options="$opcionesCliente"
                        :value="$filtros['cliente_id']"
                        :placeholder="__('personal.desempenio.filtro_cliente_placeholder')"
                        data-ag-desempenio-cliente
                    />

                    <x-atoms.select
                        name="campania_id"
                        :label="__('personal.desempenio.filtro_campania')"
                        :options="$opcionesCampania"
                        :value="$filtros['campania_id']"
                        :placeholder="__('personal.desempenio.filtro_campania_placeholder')"
                        data-ag-desempenio-campania
                        data-mapa-cliente-campania="{{ json_encode($resultado->clientePorCampania) }}"
                    />
                </x-organisms.filter-panel>
            </div>

            @unless ($sinDatosEnRango)
                <div class="ag-detalle__kpis">
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
                </div>
            @endunless

            <x-molecules.form-layout>
                @if ($sinDatosEnRango)
                    {{--
                        Estado vacío ilustrado (etapa 3, tarea 81): la persona
                        no tiene NINGUNA sesión ni rechazo en todo el rango de
                        fechas — clientesDisponibles/campaniasDisponibles
                        salen de las mismas filas (ver ObtenerDesempenioPersona::
                        opcionesDeFiltro) sin aplicar el filtro de cliente/
                        campaña, así que si está vacío las tres secciones de
                        abajo también lo estarían. Un único bloque en vez de
                        tres empty-state repitiendo "no hay nada".
                    --}}
                    <x-molecules.empty-state
                        icon="flight_takeoff"
                        :title="__('personal.desempenio.vacio_titulo')"
                        :detail="__('personal.desempenio.vacio_detalle')"
                    />
                @else
                    <x-molecules.form-section accent="info" :title="__('personal.desempenio.seccion_sesiones')" :count="(string) count($resultado->sesiones)">
                        <div class="ag-form-section__field--full">
                            @if (count($resultado->sesiones) === 0)
                                <x-molecules.empty-state
                                    icon="search_off"
                                    :title="__('personal.desempenio.sesiones_vacio_titulo')"
                                    :detail="__('personal.desempenio.sesiones_vacio')"
                                />
                            @else
                                <x-molecules.index-table columns="6rem 1fr 1fr 1fr 1.4fr 6rem 7rem">
                                    <x-slot:head>
                                        <span role="columnheader">{{ __('personal.desempenio.col_fecha') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_rol') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_cliente') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_campania') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_lote') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_hectareas') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_estado') }}</span>
                                    </x-slot:head>

                                    @foreach ($resultado->sesiones as $sesion)
                                        <div class="ag-index-table__row" role="row">
                                            <span role="cell" class="ag-persona-desempeno__mono">{{ \Illuminate\Support\Carbon::parse($sesion->fecha)->format('d/m/Y') }}</span>
                                            <span role="cell">{{ __('personal.rol_equipo.'.$sesion->rol) }}</span>
                                            <span role="cell">{{ $sesion->clienteNombre }}</span>
                                            <span role="cell">{{ $sesion->campaniaCodigo ?? __('personal.desempenio.sin_campania') }}</span>
                                            <span role="cell">{{ $sesion->loteCodigo }} — {{ $sesion->propiedadNombre }}</span>
                                            <span role="cell" class="ag-persona-desempeno__mono">{{ number_format((float) $sesion->hectareasDeclaradas, 2, ',', '.') }}</span>
                                            <span role="cell">
                                                <x-atoms.badge :variant="$sesion->estado === 'validado' ? 'success' : 'warning'">
                                                    {{ __('personal.desempenio.estado_sesion.'.$sesion->estado) }}
                                                </x-atoms.badge>
                                            </span>
                                        </div>
                                    @endforeach
                                </x-molecules.index-table>
                            @endif
                        </div>
                    </x-molecules.form-section>

                    <x-molecules.form-section accent="alert" :title="__('personal.desempenio.seccion_rechazos')" :count="(string) count($resultado->rechazos)">
                        <div class="ag-form-section__field--full">
                            @if (count($resultado->rechazos) === 0)
                                <x-molecules.empty-state
                                    icon="check_circle"
                                    :title="__('personal.desempenio.rechazos_vacio_titulo')"
                                    :detail="__('personal.desempenio.rechazos_vacio')"
                                />
                            @else
                                <x-molecules.index-table columns="6rem 1fr 1fr 1fr 1.2fr 1.6fr 1fr">
                                    <x-slot:head>
                                        <span role="columnheader">{{ __('personal.desempenio.col_fecha') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_rol') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_cliente') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_campania') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_lote') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_motivo') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_rechazado_por') }}</span>
                                    </x-slot:head>

                                    @foreach ($resultado->rechazos as $rechazo)
                                        <div class="ag-index-table__row" role="row">
                                            <span role="cell" class="ag-persona-desempeno__mono">{{ \Illuminate\Support\Carbon::parse($rechazo->fecha)->format('d/m/Y') }}</span>
                                            <span role="cell">{{ __('personal.rol_equipo.'.$rechazo->rol) }}</span>
                                            <span role="cell">{{ $rechazo->clienteNombre }}</span>
                                            <span role="cell">{{ $rechazo->campaniaCodigo ?? __('personal.desempenio.sin_campania') }}</span>
                                            <span role="cell">{{ $rechazo->loteCodigo }} — {{ $rechazo->propiedadNombre }}</span>
                                            <span role="cell">{{ $rechazo->motivo }}</span>
                                            <span role="cell">{{ $rechazo->rechazadoPorNombre }}</span>
                                        </div>
                                    @endforeach
                                </x-molecules.index-table>
                            @endif
                        </div>
                    </x-molecules.form-section>

                    <x-molecules.form-section accent="distintivo-2" :title="__('personal.desempenio.seccion_incidencias')" :count="(string) count($resultado->incidencias)">
                        <div class="ag-form-section__field--full">
                            @if (count($resultado->incidencias) === 0)
                                <x-molecules.empty-state
                                    icon="report"
                                    :title="__('personal.desempenio.incidencias_vacio_titulo')"
                                    :detail="__('personal.desempenio.incidencias_vacio')"
                                />
                            @else
                                <x-molecules.index-table columns="6rem 1fr 2fr">
                                    <x-slot:head>
                                        <span role="columnheader">{{ __('personal.desempenio.col_fecha') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_tipo') }}</span>
                                        <span role="columnheader">{{ __('personal.desempenio.col_descripcion') }}</span>
                                    </x-slot:head>

                                    @foreach ($resultado->incidencias as $incidencia)
                                        <div class="ag-index-table__row" role="row">
                                            <span role="cell" class="ag-persona-desempeno__mono">{{ \Illuminate\Support\Carbon::parse($incidencia->fecha)->format('d/m/Y') }}</span>
                                            <span role="cell">{{ __('personal.desempenio.incidencia_tipo.'.$incidencia->tipo) }}</span>
                                            <span role="cell">{{ $incidencia->descripcion ?? __('personal.desempenio.sin_descripcion') }}</span>
                                        </div>
                                    @endforeach
                                </x-molecules.index-table>
                            @endif
                        </div>
                    </x-molecules.form-section>
                @endif

                <x-slot:aside>
                    <x-molecules.form-section accent="primary-2" :title="__('personal.desempenio.aside_relacionado_titulo')">
                        <div class="ag-form-section__field--full ag-detalle__vinculos">
                            @foreach ($vinculos as $vinculo)
                                <x-molecules.link-row
                                    :href="$vinculo['href']"
                                    :icon="$vinculo['icon']"
                                    :title="$vinculo['title']"
                                    :meta="$vinculo['meta']"
                                    :tone="$vinculo['tone']"
                                />
                            @endforeach
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
