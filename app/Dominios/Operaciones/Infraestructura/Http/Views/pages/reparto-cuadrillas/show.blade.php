{{--
    Page: reparto-cuadrillas/show (GET /panel/reparto-cuadrillas/{orden}, panel.reparto-cuadrillas.show)
    Ficha de reparto de una orden vigente (HU-70, tarea 85; rediseñada por
    HU-92, tarea 107 para N lotes/N equipos; homogeneizada a arquetipo Detalle
    en la tarea 124, §6.4 de docs/diseno/guia_pantalla_panel.md): resumen por
    lote (solicitadas / asignadas / restantes), equipos ya asignados y un
    ÚNICO formulario que confirma en bloque el reparto completo — con 1
    equipo, viene pre-cargado con todos los lotes de la orden y sus hectáreas
    restantes; con 2+, el jefe de campo agrega equipos y, en cada uno, elige
    qué lotes le tocan (selección múltiple) y sus hectáreas.

    A diferencia de las otras fichas del arquetipo, ESTA trae un formulario
    real adentro — el caso de uso detrás (`asignar()` → `CrearOrdenTrabajo`)
    no se toca. Si la orden no está vigente, o no hay ningún equipo vigente
    hoy, el formulario se reemplaza por un `empty-state` que dice por qué
    (guía §6.3.5: nada se esconde) — el servidor revalida las guardas en
    `AsignarEquiposOrden` de cualquier forma.

    Datos esperados (ver RepartoCuadrillasController::mostrar()): la cáscara
    de CascaraPanel, más:
    - $orden (OrdenAplicacion), $contratoLabel (string).
    - $resumenTotal (array{hectareas_lote: string, asignadas: string, restantes: string}):
      suma de todos los lotes de la orden.
    - $porcentajeAsignado (int, 0-100).
    - $resumenPorLote (list<array{lote_id: int, hectareas_solicitadas: string, asignadas: string, restantes: string}>).
    - $trabajosAsignados (Collection<int, Trabajo>): con `equipo_trabajo_id`
      no nulo, orden de alta. $equiposAsignadosCount (int): equipos DISTINTOS.
    - $etiquetasEquipo / $etiquetasLote (array<int, string>): ya resueltos
      por el controlador (ADR 0003 regla 3 — `Personal`/`Comercial` se leen
      por `DB::table`, sin importar sus modelos Eloquent). `$etiquetasLote`
      son SOLO los lotes de esta orden.
    - $equiposDisponibles (Collection<int, string>): equipos vigentes HOY.
    - $equiposIniciales (list<array{equipo_trabajo_id?: int, lotes: list<array{lote_id?: int, hectareas?: string}>}>):
      una fila con todos los lotes de restantes > 0 pre-cargados (o `old('equipos')`
      tras un error de validación).
    - $vinculos (list): orden, contrato, Órdenes de Trabajo ya creadas — sin
      "Actividad": esta ficha es una acción sobre la orden, no una entidad
      con su propia línea de tiempo.

    Gateada por `operaciones.orden.asignar_equipos`. Estilos en
    resources/css/pages/reparto-cuadrillas.css y
    resources/css/pages/detalle.css (arquetipo Detalle) — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $esVigente = $orden->estado->value === 'vigente';
    $hayEquiposVigentes = $equiposDisponibles->isNotEmpty();
    $variantePorEstado = \App\Dominios\Operaciones\Infraestructura\Http\PasosDeOrden::TONO_POR_ESTADO;
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
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="__('operaciones.asignacion_equipos.ficha_titulo', ['nro' => $orden->nro_aplicacion])"
                :subtitle="__('operaciones.asignacion_equipos.ficha_subtitulo', ['nro' => $orden->nro_aplicacion, 'contrato' => $contratoLabel])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$variantePorEstado[$orden->estado->value]">
                        {{ __('operaciones.estado.'.$orden->estado->value) }}
                    </x-atoms.badge>
                </x-slot:chip>

                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.ordenes.show', $orden)" :label="__('operaciones.asignacion_equipos.ficha_volver_a_orden')" />
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('equipos'))
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first('equipos') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('operaciones.asignacion_equipos.resumen_hectareas_lote')"
                    icon="landscape"
                    :value="number_format((float) $resumenTotal['hectareas_lote'], 2, ',', '.')"
                    value-suffix="ha"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.asignacion_equipos.resumen_asignadas')"
                    icon="task_alt"
                    :value="number_format((float) $resumenTotal['asignadas'], 2, ',', '.')"
                    value-suffix="ha"
                    :state="$porcentajeAsignado >= 100 ? 'success' : null"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.asignacion_equipos.resumen_restantes')"
                    icon="hourglass_empty"
                    :value="number_format((float) $resumenTotal['restantes'], 2, ',', '.')"
                    value-suffix="ha"
                    :state="(float) $resumenTotal['restantes'] > 0 ? 'warning' : null"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.asignacion_equipos.kpi_equipos_asignados')"
                    icon="groups"
                    :value="$equiposAsignadosCount"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('operaciones.asignacion_equipos.seccion_lotes')" :count="(string) count($resumenPorLote)">
                    <div class="ag-form-section__field--full">
                        <x-molecules.index-table columns="2fr 1fr 1fr 1fr">
                            <x-slot:head>
                                <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_lote') }}</span>
                                <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_hectareas_lote') }}</span>
                                <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_asignadas') }}</span>
                                <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_restantes') }}</span>
                            </x-slot:head>

                            @foreach ($resumenPorLote as $fila)
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell">{{ $etiquetasLote[$fila['lote_id']] ?? "#{$fila['lote_id']}" }}</span>
                                    <span role="cell" class="ag-reparto-cuadrillas__mono">{{ number_format((float) $fila['hectareas_solicitadas'], 2, ',', '.') }}</span>
                                    <span role="cell" class="ag-reparto-cuadrillas__mono">{{ number_format((float) $fila['asignadas'], 2, ',', '.') }}</span>
                                    <span role="cell" class="ag-reparto-cuadrillas__mono">{{ number_format((float) $fila['restantes'], 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('operaciones.asignacion_equipos.seccion_equipos')" :count="(string) $trabajosAsignados->count()">
                    @if ($trabajosAsignados->isEmpty())
                        <div class="ag-form-section__field--full">
                            <x-molecules.empty-state
                                icon="groups"
                                :title="__('operaciones.asignacion_equipos.equipos_vacio_titulo')"
                                :detail="__('operaciones.asignacion_equipos.equipos_vacio')"
                            />
                        </div>
                    @else
                        <div class="ag-form-section__field--full">
                            <x-molecules.index-table columns="1fr 1fr 1fr var(--ag-row-actions-width)">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('operaciones.asignacion_equipos.campo_equipo') }}</span>
                                    <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_lote') }}</span>
                                    <span role="columnheader">{{ __('operaciones.asignacion_equipos.col_hectareas_asignadas') }}</span>
                                    <span role="columnheader">{{ __('ui.tabla.col_acciones') }}</span>
                                </x-slot:head>

                                @foreach ($trabajosAsignados as $trabajo)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $etiquetasEquipo[$trabajo->equipo_trabajo_id] ?? "#{$trabajo->equipo_trabajo_id}" }}</span>
                                        <span role="cell">{{ $etiquetasLote[$trabajo->lote_id] ?? "#{$trabajo->lote_id}" }}</span>
                                        <span role="cell" class="ag-reparto-cuadrillas__mono">{{ number_format((float) $trabajo->hectareas_declaradas, 2, ',', '.') }}</span>
                                        <span role="cell" class="ag-index-table__acciones">
                                            <x-organisms.row-actions>
                                                <x-atoms.button :href="route('panel.trabajos.detalle', $trabajo)" variant="info-outline" size="sm" icon="visibility">
                                                    {{ __('operaciones.asignacion_equipos.ver_accion') }}
                                                </x-atoms.button>
                                            </x-organisms.row-actions>
                                        </span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        </div>
                    @endif
                </x-molecules.form-section>

                @if (! $esVigente)
                    <x-molecules.form-section accent="warning" :title="__('operaciones.asignacion_equipos.asignar_accion')">
                        <div class="ag-form-section__field--full">
                            <x-molecules.empty-state
                                icon="lock"
                                :title="__('operaciones.asignacion_equipos.formulario_no_vigente_titulo')"
                                :detail="__('operaciones.asignacion_equipos.orden_no_vigente')"
                            />
                        </div>
                    </x-molecules.form-section>
                @elseif (! $hayEquiposVigentes)
                    <x-molecules.form-section accent="warning" :title="__('operaciones.asignacion_equipos.asignar_accion')">
                        <div class="ag-form-section__field--full">
                            <x-molecules.empty-state
                                icon="groups"
                                :title="__('operaciones.asignacion_equipos.formulario_sin_equipos_titulo')"
                                :detail="__('operaciones.asignacion_equipos.equipos_sin_vigentes')"
                            />
                        </div>
                    </x-molecules.form-section>
                @else
                    <form
                        method="POST"
                        action="{{ route('panel.reparto-cuadrillas.store', $orden) }}"
                        id="reparto-cuadrillas-form"
                        data-ag-reparto-cuadrillas-form
                    >
                        @csrf

                        <x-molecules.form-section
                            accent="success"
                            :title="__('operaciones.asignacion_equipos.seccion_condiciones_vuelo')"
                            :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => ($orden->categoriaInsumo?->tipo_insumo?->value === 'liquido' ? 9 : 7)])"
                        >
                            <p class="ag-form-section__field--full ag-reparto-cuadrillas-ficha__ayuda">
                                {{ __('operaciones.asignacion_equipos.seccion_condiciones_vuelo_ayuda') }}
                            </p>

                            <x-atoms.input
                                type="number"
                                name="parametros[humedad_min_pct]"
                                id="parametros-humedad-min"
                                :label="__('operaciones.asignacion_equipos.campo_humedad_min_pct')"
                                :value="old('parametros.humedad_min_pct')"
                                min="0"
                                max="100"
                                step="0.01"
                                :error="$errors->first('parametros.humedad_min_pct')"
                            />

                            <x-atoms.input
                                type="number"
                                name="parametros[humedad_max_pct]"
                                id="parametros-humedad-max"
                                :label="__('operaciones.asignacion_equipos.campo_humedad_max_pct')"
                                :value="old('parametros.humedad_max_pct')"
                                min="0"
                                max="100"
                                step="0.01"
                                :error="$errors->first('parametros.humedad_max_pct')"
                            />

                            <x-atoms.input
                                type="number"
                                name="parametros[viento_max_kmh]"
                                id="parametros-viento-max"
                                :label="__('operaciones.asignacion_equipos.campo_viento_max_kmh')"
                                :value="old('parametros.viento_max_kmh')"
                                min="0.01"
                                step="0.01"
                                :error="$errors->first('parametros.viento_max_kmh')"
                            />

                            <x-atoms.input
                                type="number"
                                name="parametros[temperatura_max_c]"
                                id="parametros-temperatura-max"
                                :label="__('operaciones.asignacion_equipos.campo_temperatura_max_c')"
                                :value="old('parametros.temperatura_max_c')"
                                step="0.01"
                                :error="$errors->first('parametros.temperatura_max_c')"
                            />

                            <x-atoms.input
                                type="number"
                                name="parametros[altura_vuelo_m]"
                                id="parametros-altura-vuelo"
                                :label="__('operaciones.asignacion_equipos.campo_altura_vuelo_m')"
                                :value="old('parametros.altura_vuelo_m')"
                                min="0.01"
                                step="0.01"
                                :error="$errors->first('parametros.altura_vuelo_m')"
                            />

                            <x-atoms.input
                                type="number"
                                name="parametros[velocidad_vuelo_kmh]"
                                id="parametros-velocidad-vuelo"
                                :label="__('operaciones.asignacion_equipos.campo_velocidad_vuelo_kmh')"
                                :value="old('parametros.velocidad_vuelo_kmh')"
                                min="0.01"
                                step="0.01"
                                :error="$errors->first('parametros.velocidad_vuelo_kmh')"
                            />

                            <x-atoms.input
                                type="number"
                                name="parametros[ancho_pasada_m]"
                                id="parametros-ancho-pasada"
                                :label="__('operaciones.asignacion_equipos.campo_ancho_pasada_m')"
                                :value="old('parametros.ancho_pasada_m')"
                                min="0.01"
                                step="0.01"
                                :error="$errors->first('parametros.ancho_pasada_m')"
                            />

                            @if ($orden->categoriaInsumo?->tipo_insumo?->value === 'liquido')
                                <x-atoms.input
                                    type="number"
                                    name="parametros[ph_agua]"
                                    id="parametros-ph-agua"
                                    :label="__('operaciones.asignacion_equipos.campo_ph_agua')"
                                    :value="old('parametros.ph_agua')"
                                    min="0"
                                    max="14"
                                    step="0.01"
                                    :error="$errors->first('parametros.ph_agua')"
                                />

                                <x-atoms.input
                                    type="number"
                                    name="parametros[ph_calda]"
                                    id="parametros-ph-calda"
                                    :label="__('operaciones.asignacion_equipos.campo_ph_calda')"
                                    :value="old('parametros.ph_calda')"
                                    min="0"
                                    max="14"
                                    step="0.01"
                                    :error="$errors->first('parametros.ph_calda')"
                                />
                            @endif
                        </x-molecules.form-section>

                        <x-molecules.form-section accent="distintivo-1" :title="__('operaciones.asignacion_equipos.asignar_accion')">
                            <div class="ag-form-section__field--full" data-ag-equipos-lista>
                                @foreach ($equiposIniciales as $indiceEquipo => $equipo)
                                    @include('operaciones::pages.reparto-cuadrillas._equipo-bloque', [
                                        'indiceEquipo' => $indiceEquipo,
                                        'lotesFila' => $equipo['lotes'] ?? [[]],
                                        'mostrarQuitarEquipo' => count($equiposIniciales) > 1,
                                    ])
                                @endforeach
                            </div>

                            <div class="ag-form-section__field--full">
                                <x-atoms.button type="button" variant="outline" icon="add" data-ag-equipos-agregar>
                                    {{ __('operaciones.asignacion_equipos.equipo_agregar') }}
                                </x-atoms.button>
                            </div>

                            {{-- Plantilla clonable del NIVEL EXTERNO (equipos):
                                 `reparto-cuadrillas-form.js` reemplaza
                                 `__INDICE_EQUIPO__` al clonar, y arranca sin
                                 lotes pre-cargados (el jefe de campo elige a
                                 mano en un reparto de 2+ equipos). --}}
                            <template data-ag-equipo-template>
                                @include('operaciones::pages.reparto-cuadrillas._equipo-bloque', [
                                    'indiceEquipo' => '__INDICE_EQUIPO__',
                                    'lotesFila' => [[]],
                                    'mostrarQuitarEquipo' => true,
                                ])
                            </template>
                        </x-molecules.form-section>

                        <x-organisms.form-actions-bar>
                            <x-slot:actions>
                                <x-atoms.button type="submit" variant="primary" icon="check">
                                    {{ __('operaciones.asignacion_equipos.asignar_boton') }}
                                </x-atoms.button>
                            </x-slot:actions>
                        </x-organisms.form-actions-bar>
                    </form>
                @endif

                <x-slot:aside>
                    <x-molecules.progress-meter
                        :title="__('operaciones.asignacion_equipos.avance_titulo')"
                        :percent="$porcentajeAsignado"
                        :summary-label="__('operaciones.asignacion_equipos.avance_resumen', ['asignadas' => number_format((float) $resumenTotal['asignadas'], 2, ',', '.'), 'solicitadas' => number_format((float) $resumenTotal['hectareas_lote'], 2, ',', '.')])"
                    />

                    @if (count($vinculos))
                        <x-molecules.form-section accent="alert" :title="__('operaciones.ordenes.seccion_vinculos')">
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
                    @endif
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
