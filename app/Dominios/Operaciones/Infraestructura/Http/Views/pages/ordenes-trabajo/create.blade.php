{{--
    Page: ordenes-trabajo/create (GET/POST /panel/trabajos/crear, panel.trabajos.create/store)
    Alta de una Orden de Trabajo (reforma 18/9/2026; reordenada el 19/9/2026 a
    pedido del dueño). Todo lo que sigue a la orden de aplicación DEPENDE de
    ella — cuántos equipos lleva, qué lotes, si es de insumo líquido —, así que
    el formulario se dibuja en el servidor para la orden elegida: al cambiarla,
    `ordenes-trabajo-form.js` recarga esta misma pantalla con `?orden_id=`
    (conservando lo ya cargado en calda, clima y vuelo).

    Sin orden elegida NADA se oculta (criterio del dueño, 19/9/2026: ningún
    formulario del panel esconde secciones por falta de un dato previo —
    simplemente no deja guardar). Clima y vuelo no dependen de la orden y se
    pueden cargar antes; «Calda» y «Equipos» sí dependen, y muestran el
    componente de vacío, igual que la sección de lotes del formulario de
    contratos; y confirmar sin orden responde "Elige la orden de aplicación".

    Orden de las secciones: orden de aplicación → calda → límites climáticos →
    parámetros de vuelo → equipos.

    - Calda (`_calda.blade.php`): casillas simples de lo que lleva, más Ph y
      litros por hectárea (insumo líquido) o kilos por hectárea (sólido).
    - Límites climáticos y parámetros de vuelo: dos secciones separadas,
      compartidas por toda la tanda.
    - Equipos: tantos bloques como `cantidad_equipos_necesarios` de la orden.
      Acá no se agregan ni se quitan equipos — esa cantidad se decidió al
      emitir la orden. El primero es obligatorio; los demás pueden quedar en
      blanco si la tanda sale con menos (`CrearOrdenTrabajoRequest` los
      descarta). Con un solo equipo, ese equipo ejecuta el total: sus lotes
      llegan precargados con lo que queda por repartir.

    Datos esperados (ver OrdenesTrabajoController::create()): la cáscara de
    CascaraPanel, más:
    - $ordenesDisponibles (Collection<int, string>): id → label de orden vigente.
    - $datosOrden (array<int, array{es_liquido, cantidad_equipos,
      restantes_total, lotes}>): por cada orden, sus lotes con hectáreas
      restantes.
    - $ordenPreseleccionadaId (?int): si se llega con ?orden_id=X en la URL.
    - $equiposDisponibles (Collection<int, string>): escuadras vigentes hoy.
    - $puedeCrearEscuadra (bool): permiso `personal.equipo_trabajo.crear` del
      rol activo, para ofrecer o no el acceso rápido «Crear escuadra».

    Gateada por `operaciones.trabajo.crear`. Estilos en
    resources/css/pages/ordenes-trabajo.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $ordenPreseleccionadaId = $ordenPreseleccionadaId ?? null;
    $ordenesDisponibles = $ordenesDisponibles ?? collect();
    $datosOrden = $datosOrden ?? [];
    $equiposDisponibles = $equiposDisponibles ?? collect();
    $puedeCrearEscuadra = $puedeCrearEscuadra ?? false;

    $ordenId = old('orden_id', $ordenPreseleccionadaId ?? '');
    $ordenElegida = $ordenId !== '' && isset($datosOrden[$ordenId]) ? $datosOrden[$ordenId] : null;
    $parametrosAntiguos = old('parametros', []);

    $esLiquido = $ordenElegida['es_liquido'] ?? false;
    $cantidadEquipos = $ordenElegida['cantidad_equipos'] ?? 0;

    // Un solo equipo ejecuta el total de la orden: sus lotes llegan cargados
    // con lo que queda por repartir. Con dos o más, el reparto lo arma quien
    // carga el formulario.
    $lotesPendientes = collect($ordenElegida['lotes'] ?? [])
        ->where('pendiente', true)
        ->map(fn (array $lote): array => ['lote_id' => $lote['lote_id'], 'hectareas' => $lote['restantes']])
        ->values()
        ->all();
    $equiposPorDefecto = $cantidadEquipos === 1 && $lotesPendientes !== []
        ? [['lotes' => $lotesPendientes]]
        : [];
    $equiposAntiguos = old('equipos', $equiposPorDefecto);

    // Memento de navegación: el acceso rápido «Crear escuadra» le dice a
    // `RecordarOrigenNavegacion` que se vuelve a ESTA pantalla, con la orden
    // ya elegida.
    $urlActual = route('panel.trabajos.create', array_filter(['orden_id' => $ordenId]));
    $urlCrearEscuadra = route('panel.equipos-trabajo.create', [
        'volver_a' => $urlActual,
        'volver_texto' => __('operaciones.ordenes_trabajo.crear_titulo'),
    ]);
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
            data-ag-url-crear="{{ route('panel.trabajos.create') }}"
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
                :title="__('operaciones.ordenes_trabajo.seccion_orden')"
                :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => 1])"
            >
                <x-atoms.select
                    name="orden_id"
                    id="orden_id"
                    :label="__('operaciones.ordenes_trabajo.campo_orden')"
                    :options="$ordenesDisponibles"
                    :value="$ordenId"
                    :placeholder="__('operaciones.ordenes_trabajo.campo_orden_placeholder')"
                    :help="__('operaciones.ordenes_trabajo.campo_orden_ayuda')"
                    required
                    :error="$errors->first('orden_id')"
                    data-ag-orden-selector
                />
            </x-molecules.form-section>

            @include('operaciones::pages.ordenes-trabajo._calda', [
                'esLiquido' => $ordenElegida === null ? null : $esLiquido,
                'parametrosAntiguos' => $parametrosAntiguos,
                'litrosHaOrden' => $ordenElegida['litros_ha'] ?? null,
            ])

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.seccion_clima')"
                :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => 4])"
            >
                <p class="ag-form-section__field--full ag-ordenes-trabajo-form__ayuda">
                    {{ __('operaciones.ordenes_trabajo.seccion_parametros_ayuda') }}
                </p>

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
            </x-molecules.form-section>

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.seccion_vuelo')"
                :count="__('operaciones.ordenes_trabajo.campos_contador', ['cantidad' => 4])"
            >
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
            </x-molecules.form-section>

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.seccion_equipos')"
                :count="trans_choice('operaciones.ordenes_trabajo.equipos_contador', $cantidadEquipos, ['cantidad' => $cantidadEquipos])"
            >
                @if ($ordenElegida === null)
                    <div class="ag-form-section__field--full">
                        <x-molecules.empty-state
                            icon="groups"
                            :title="__('operaciones.ordenes_trabajo.equipos_vacio_titulo')"
                            :detail="__('operaciones.ordenes_trabajo.equipos_vacio_detalle')"
                        />
                    </div>
                @else
                    <p class="ag-form-section__field--full ag-ordenes-trabajo-form__ayuda">
                        {{ trans_choice('operaciones.ordenes_trabajo.seccion_equipos_ayuda', $cantidadEquipos) }}
                    </p>

                    @if ($errors->has('equipos'))
                        <x-molecules.alert-strip variant="danger" icon="error" class="ag-form-section__field--full">
                            {{ $errors->first('equipos') }}
                        </x-molecules.alert-strip>
                    @endif

                    @if ($equiposDisponibles->isEmpty())
                        <x-molecules.alert-strip variant="warning" icon="groups" class="ag-form-section__field--full">
                            {{ __('operaciones.ordenes_trabajo.equipo_sin_opciones') }}
                            @if ($puedeCrearEscuadra)
                                <a href="{{ $urlCrearEscuadra }}" data-ag-link-accent>{{ __('operaciones.ordenes_trabajo.escuadra_crear') }}</a>
                            @endif
                        </x-molecules.alert-strip>
                    @endif

                    @if ($cantidadEquipos > 1)
                        {{-- Reparto de hectáreas (pedido del dueño, 19/9/2026): en vez de
                             cargar a mano lotes y hectáreas de cada equipo, se elige un
                             criterio y el formulario los completa — queda solo elegir la
                             escuadra. Es una ayuda de carga: lo repartido se puede ajustar
                             y el servidor valida igual. No viaja en el POST que se guarda. --}}
                        <x-atoms.radio-group
                            name="reparto_modo"
                            id="reparto-modo"
                            class="ag-form-section__field--full"
                            :label="__('operaciones.ordenes_trabajo.reparto_modo')"
                            :options="[
                                'manual' => __('operaciones.ordenes_trabajo.reparto_modo_manual'),
                                'parejo' => __('operaciones.ordenes_trabajo.reparto_modo_parejo'),
                                'dificultad' => __('operaciones.ordenes_trabajo.reparto_modo_dificultad'),
                            ]"
                            :value="old('reparto_modo', 'manual')"
                            :help="__('operaciones.ordenes_trabajo.reparto_modo_ayuda')"
                            data-ag-reparto-modo
                        />
                    @endif

                    <div
                        class="ag-form-section__field--full ag-ordenes-trabajo-form__equipos"
                        data-ag-equipos-lista
                        data-ag-lotes="{{ json_encode(array_values(array_filter($ordenElegida['lotes'], fn (array $lote): bool => $lote['pendiente']))) }}"
                    >
                        @for ($indiceEquipo = 0; $indiceEquipo < $cantidadEquipos; $indiceEquipo++)
                            @include('operaciones::pages.ordenes-trabajo._equipo-bloque', [
                                'indiceEquipo' => $indiceEquipo,
                                'equipo' => $equiposAntiguos[$indiceEquipo] ?? ['lotes' => [[]]],
                                'obligatorio' => $indiceEquipo === 0,
                                'lotesOrden' => $ordenElegida['lotes'],
                                'equiposDisponibles' => $equiposDisponibles,
                                'urlCrearEscuadra' => $urlCrearEscuadra,
                                'puedeCrearEscuadra' => $puedeCrearEscuadra,
                            ])
                        @endfor
                    </div>

                    <p
                        class="ag-form-section__field--full ag-ordenes-trabajo-form__reparto"
                        data-ag-reparto
                        data-ag-reparto-total="{{ $ordenElegida['restantes_total'] }}"
                        data-ag-reparto-plantilla="{{ __('operaciones.ordenes_trabajo.reparto_resumen') }}"
                        aria-live="polite"
                    ></p>
                @endif
            </x-molecules.form-section>

            {{-- Los botones van en el slot `actions`: el organismo no pinta el slot
                 por defecto (así venía desde la reforma del 18/9 y «Confirmar» no
                 se dibujaba nunca). Sin orden elegida igual se ofrece: confirmar
                 responde con el error del campo, como cualquier otro obligatorio. --}}
            <x-organisms.form-actions-bar :status="__('operaciones.ordenes_trabajo.estado_form')">
                <x-slot:actions>
                    <x-atoms.button :href="route('panel.trabajos.index')" variant="outline">
                        {{ __('ui.action.cancel') }}
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary" icon="check">
                        {{ __('operaciones.ordenes_trabajo.guardar') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
