{{--
    Page: ordenes-trabajo/create (GET/POST /panel/trabajos/crear, panel.trabajos.create/store)
    Alta de una Orden de Trabajo (reforma 18/9/2026): formulario que agrupa
    equipos y lotes con parámetros compartidos de clima/vuelo. Arquitectura del
    formulario repetible N equipos × N lotes adaptada de
    `asignacion-equipos/show.blade.php`, con cambios principales:
    - Parámetros de vuelo (8 campos) AFUERA de cada equipo (compartidos).
    - Ph y calda también compartidos (solo si la orden es de insumo líquido).
    - Cada fila de lote incluye turno + horas de inicio/fin.

    Datos esperados (ver OrdenesTrabajoController::create()): la cáscara de
    CascaraPanel, más:
    - $ordenesDisponibles (Collection<int, string>): id → label de orden vigente.
    - $datosOrden (array<int, array{es_liquido: bool, lotes: list<...>}>):
      por cada orden, lotes con hectáreas restantes.
    - $ordenPreseleccionadaId (?int): si se llega con ?orden_id=X en la URL.
    - $equiposDisponibles (Collection<int, string>): equipos vigentes hoy.

    Gateada por `operaciones.trabajo.crear`. Estilos en
    resources/css/pages/ordenes-trabajo.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $ordenPreseleccionadaId = $ordenPreseleccionadaId ?? null;
    $ordenesDisponibles = $ordenesDisponibles ?? collect();
    $datosOrden = $datosOrden ?? [];
    $equiposDisponibles = $equiposDisponibles ?? collect();

    // Datos del formulario: orden elegida, parámetros, equipos con lotes.
    $ordenId = old('orden_id', $ordenPreseleccionadaId ?? '');
    $parametrosAntiguos = old('parametros', []);
    $equiposAntiguos = old('equipos', [['lotes' => [[]]]]);

    // Determina si la orden elegida es de insumo líquido
    $esLiquido = false;
    if ($ordenId && isset($datosOrden[$ordenId])) {
        $esLiquido = $datosOrden[$ordenId]['es_liquido'] ?? false;
    }
@endphp
<x-templates.panel-shell :title="__('operaciones.ordenes_trabajo.crear_titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes_trabajo.titulo')"
    >
        <form
            method="POST"
            action="{{ route('panel.trabajos.store') }}"
            class="ag-ordenes-trabajo-form"
            data-ag-ordenes-trabajo-form
            novalidate
        >
            @csrf

            <x-organisms.page-header
                :title="__('operaciones.ordenes_trabajo.crear_titulo')"
                :subtitle="__('operaciones.ordenes_trabajo.crear_subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button :href="route('panel.trabajos.index')" variant="outline" icon="arrow_back">
                        {{ __('operaciones.ordenes_trabajo.volver') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.crear_titulo')"
                :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => 1])"
            >
                <x-atoms.select
                    name="orden_id"
                    id="orden_id"
                    :label="__('operaciones.ordenes_trabajo.campo_orden')"
                    :options="$ordenesDisponibles"
                    :value="$ordenId"
                    :placeholder="__('operaciones.ordenes_trabajo.campo_orden_placeholder')"
                    required
                    :error="$errors->first('orden_id')"
                    data-ag-orden-selector
                />
            </x-molecules.form-section>

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.seccion_parametros')"
                :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => $esLiquido ? 10 : 8])"
            >
                <div class="ag-form-section__field--full">
                    <small style="color: var(--ag-color-text-muted)">
                        {{ __('operaciones.ordenes_trabajo.seccion_parametros_ayuda') }}
                    </small>
                </div>

                <x-atoms.input
                    type="number"
                    name="parametros[humedad_min_pct]"
                    id="parametros-humedad-min"
                    :label="__('operaciones.asignacion_equipos.campo_humedad_min_pct')"
                    :value="$parametrosAntiguos['humedad_min_pct'] ?? ''"
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
                    :value="$parametrosAntiguos['humedad_max_pct'] ?? ''"
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
                    :value="$parametrosAntiguos['viento_max_kmh'] ?? ''"
                    min="0.01"
                    step="0.01"
                    :error="$errors->first('parametros.viento_max_kmh')"
                />

                <x-atoms.input
                    type="number"
                    name="parametros[temperatura_max_c]"
                    id="parametros-temperatura-max"
                    :label="__('operaciones.asignacion_equipos.campo_temperatura_max_c')"
                    :value="$parametrosAntiguos['temperatura_max_c'] ?? ''"
                    step="0.01"
                    :error="$errors->first('parametros.temperatura_max_c')"
                />

                <x-atoms.input
                    type="number"
                    name="parametros[velocidad_max_kmh]"
                    id="parametros-velocidad-max"
                    :label="__('operaciones.asignacion_equipos.campo_velocidad_max_kmh')"
                    :value="$parametrosAntiguos['velocidad_max_kmh'] ?? ''"
                    min="0.01"
                    step="0.01"
                    :error="$errors->first('parametros.velocidad_max_kmh')"
                />

                <x-atoms.input
                    type="number"
                    name="parametros[altura_vuelo_m]"
                    id="parametros-altura-vuelo"
                    :label="__('operaciones.asignacion_equipos.campo_altura_vuelo_m')"
                    :value="$parametrosAntiguos['altura_vuelo_m'] ?? ''"
                    min="0.01"
                    step="0.01"
                    :error="$errors->first('parametros.altura_vuelo_m')"
                />

                <x-atoms.input
                    type="number"
                    name="parametros[velocidad_vuelo_kmh]"
                    id="parametros-velocidad-vuelo"
                    :label="__('operaciones.asignacion_equipos.campo_velocidad_vuelo_kmh')"
                    :value="$parametrosAntiguos['velocidad_vuelo_kmh'] ?? ''"
                    min="0.01"
                    step="0.01"
                    :error="$errors->first('parametros.velocidad_vuelo_kmh')"
                />

                <x-atoms.input
                    type="number"
                    name="parametros[ancho_pasada_m]"
                    id="parametros-ancho-pasada"
                    :label="__('operaciones.asignacion_equipos.campo_ancho_pasada_m')"
                    :value="$parametrosAntiguos['ancho_pasada_m'] ?? ''"
                    min="0.01"
                    step="0.01"
                    :error="$errors->first('parametros.ancho_pasada_m')"
                />

                @if ($esLiquido)
                    <x-atoms.input
                        type="number"
                        name="parametros[ph_agua]"
                        id="parametros-ph-agua"
                        :label="__('operaciones.asignacion_equipos.campo_ph_agua')"
                        :value="$parametrosAntiguos['ph_agua'] ?? ''"
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
                        :value="$parametrosAntiguos['ph_calda'] ?? ''"
                        min="0"
                        max="14"
                        step="0.01"
                        :error="$errors->first('parametros.ph_calda')"
                    />
                @endif
            </x-molecules.form-section>

            @if ($esLiquido)
                <x-molecules.form-section
                    :title="__('operaciones.asignacion_equipos.seccion_calda')"
                    :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => collect($parametrosAntiguos['calda'] ?? [])->count() ?: 1])"
                >
                    <div data-ag-calda-lista>
                        @foreach ($parametrosAntiguos['calda'] ?? [[]] as $indiceCalda => $calda)
                            <div class="ag-form-section__body ag-ordenes-trabajo-form__calda-fila" data-ag-calda-fila>
                                <x-atoms.input
                                    type="text"
                                    name="parametros[calda][{{ $indiceCalda }}][producto]"
                                    id="calda-{{ $indiceCalda }}-producto"
                                    :label="__('operaciones.asignacion_equipos.campo_calda_producto')"
                                    :value="$calda['producto'] ?? ''"
                                    maxlength="120"
                                    :error="$errors->first('parametros.calda.'.$indiceCalda.'.producto')"
                                />

                                <x-atoms.input
                                    type="number"
                                    name="parametros[calda][{{ $indiceCalda }}][cantidad]"
                                    id="calda-{{ $indiceCalda }}-cantidad"
                                    :label="__('operaciones.asignacion_equipos.campo_calda_cantidad')"
                                    :value="$calda['cantidad'] ?? ''"
                                    min="0.01"
                                    step="0.01"
                                    :error="$errors->first('parametros.calda.'.$indiceCalda.'.cantidad')"
                                />

                                <x-atoms.select
                                    name="parametros[calda][{{ $indiceCalda }}][unidad]"
                                    id="calda-{{ $indiceCalda }}-unidad"
                                    :label="__('operaciones.asignacion_equipos.campo_calda_unidad')"
                                    :options="[
                                        'l' => __('operaciones.asignacion_equipos.unidad_l'),
                                        'ml' => __('operaciones.asignacion_equipos.unidad_ml'),
                                        'kg' => __('operaciones.asignacion_equipos.unidad_kg'),
                                        'g' => __('operaciones.asignacion_equipos.unidad_g'),
                                    ]"
                                    :value="$calda['unidad'] ?? ''"
                                    :error="$errors->first('parametros.calda.'.$indiceCalda.'.unidad')"
                                />

                                <div class="ag-form-section__field--full">
                                    <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-calda-quitar>
                                        {{ __('operaciones.asignacion_equipos.calda_quitar') }}
                                    </x-atoms.button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <x-atoms.button type="button" variant="outline" size="sm" icon="add" data-ag-calda-agregar>
                        {{ __('operaciones.asignacion_equipos.calda_agregar') }}
                    </x-atoms.button>

                    <template data-ag-calda-template>
                        <div class="ag-form-section__body ag-ordenes-trabajo-form__calda-fila" data-ag-calda-fila>
                            <x-atoms.input
                                type="text"
                                name="parametros[calda][__INDICE_CALDA__][producto]"
                                id="calda-__INDICE_CALDA__-producto"
                                :label="__('operaciones.asignacion_equipos.campo_calda_producto')"
                                maxlength="120"
                            />

                            <x-atoms.input
                                type="number"
                                name="parametros[calda][__INDICE_CALDA__][cantidad]"
                                id="calda-__INDICE_CALDA__-cantidad"
                                :label="__('operaciones.asignacion_equipos.campo_calda_cantidad')"
                                min="0.01"
                                step="0.01"
                            />

                            <x-atoms.select
                                name="parametros[calda][__INDICE_CALDA__][unidad]"
                                id="calda-__INDICE_CALDA__-unidad"
                                :label="__('operaciones.asignacion_equipos.campo_calda_unidad')"
                                :options="[
                                    'l' => __('operaciones.asignacion_equipos.unidad_l'),
                                    'ml' => __('operaciones.asignacion_equipos.unidad_ml'),
                                    'kg' => __('operaciones.asignacion_equipos.unidad_kg'),
                                    'g' => __('operaciones.asignacion_equipos.unidad_g'),
                                ]"
                            />

                            <div class="ag-form-section__field--full">
                                <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-calda-quitar>
                                    {{ __('operaciones.asignacion_equipos.calda_quitar') }}
                                </x-atoms.button>
                            </div>
                        </div>
                    </template>
                </x-molecules.form-section>
            @endif

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.seccion_equipos')"
                :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => count($equiposAntiguos)])"
            >
                <div data-ag-equipos-lista>
                    @foreach ($equiposAntiguos as $indiceEquipo => $equipo)
                        @include('operaciones::pages.ordenes-trabajo._equipo-bloque', [
                            'indiceEquipo' => $indiceEquipo,
                            'equipo' => $equipo,
                            'mostrarQuitarEquipo' => count($equiposAntiguos) > 1,
                            'datosOrden' => $datosOrden,
                            'ordenId' => $ordenId,
                            'equiposDisponibles' => $equiposDisponibles,
                        ])
                    @endforeach
                </div>

                <x-atoms.button type="button" variant="outline" icon="add" data-ag-equipos-agregar>
                    {{ __('operaciones.ordenes_trabajo.equipo_agregar') }}
                </x-atoms.button>

                <template data-ag-equipo-template>
                    @include('operaciones::pages.ordenes-trabajo._equipo-bloque', [
                        'indiceEquipo' => '__INDICE_EQUIPO__',
                        'equipo' => ['lotes' => [[]]],
                        'mostrarQuitarEquipo' => true,
                        'datosOrden' => $datosOrden,
                        'ordenId' => $ordenId,
                        'equiposDisponibles' => $equiposDisponibles,
                    ])
                </template>
            </x-molecules.form-section>

            <x-organisms.form-actions-bar>
                <x-atoms.button :href="route('panel.trabajos.index')" variant="outline" icon="arrow_back">
                    {{ __('operaciones.ordenes_trabajo.volver') }}
                </x-atoms.button>
                <x-atoms.button type="submit" variant="primary" icon="check">
                    {{ __('operaciones.ordenes_trabajo.guardar') }}
                </x-atoms.button>
            </x-organisms.form-actions-bar>
        </form>

        @vite('resources/js/pages/ordenes-trabajo-form.js')
    </x-templates.panel-layout>
</x-templates.panel-shell>
