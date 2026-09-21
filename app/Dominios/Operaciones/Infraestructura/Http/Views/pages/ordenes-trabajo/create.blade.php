{{--
    Page: ordenes-trabajo/create (GET/POST /panel/trabajos/crear, panel.trabajos.create/store)
    Alta de una Orden de Trabajo (reforma 18/9/2026; reordenada el 19/9/2026 y
    el 21/9/2026 a pedido del dueño).

    El formulario se lee en DOS PARTES, cada una con su encabezado, porque es lo
    que el dueño confundía (21/9/2026):
    1. Indicaciones de la aplicación — calda, límites climáticos y parámetros
       de vuelo. Se cargan UNA vez y valen para todos los trabajos de la orden.
    2. Trabajos de los equipos — el reparto de lotes: cada lote que le toca a
       un equipo nace como un `Trabajo`, que es donde ese equipo registra sus
       vuelos, equipamiento, hectáreas aplicadas, fotos y emergencias.

    Calda, equipos y lotes DEPENDEN de la orden de aplicación elegida. Los datos
    de todas las órdenes disponibles viajan en `data-ag-ordenes`, y al elegir
    otra `ordenes-trabajo-form.js` rearma esas secciones en el lugar, sin otro
    request. El servidor igual dibuja el estado inicial completo (orden que
    llega por `?orden_id=` —desde la orden de aplicación— o por un error de
    validación), con el mismo HTML que usa el JS (`<template>` de equipo).

    Sin orden elegida NADA se oculta (criterio del dueño, 19/9/2026: ningún
    formulario del panel esconde secciones por falta de un dato previo —
    simplemente no deja guardar). Clima y vuelo no dependen de la orden y se
    pueden cargar antes; «Calda» y el reparto sí dependen, y muestran el
    componente de vacío; confirmar sin orden responde "Elige la orden de
    aplicación".

    - Orden de aplicación: el select (media columna) y, debajo, el cuadro «Datos
      del contrato» —número de contrato, cliente, propiedades, aplicación,
      insumo y hectáreas por repartir—, el mismo del formulario de la orden.
    - Calda (`_calda.blade.php`): casillas simples de lo que lleva, más Ph y
      litros por hectárea (insumo líquido) o kilos por hectárea (sólido).
    - Reparto: tantos bloques de equipo como `cantidad_equipos_necesarios` de
      la orden, en dos columnas. Acá no se agregan ni se quitan equipos — esa
      cantidad se decidió al emitir la orden. El primero es obligatorio; los
      demás pueden quedar en blanco (`CrearOrdenTrabajoRequest` los descarta).
      De cada equipo se carga la cuadrilla, el turno, el horario (un solo campo
      de rango, sin obligatoriedad) y las HECTÁREAS que se le asignan. Los
      campos de hectáreas están enlazados: entre todos los equipos con cuadrilla
      suman lo que a la orden le queda por repartir, así que subir uno baja a
      los demás. Los LOTES NO SE TIPEAN (pedido del dueño, 21/9/2026): salen de
      esas hectáreas y del criterio —parejo o por dificultad— y cada bloque
      dice cuáles le tocaron. Mover un lote a otro equipo es después,
      desde la ficha (editar el trabajo).

    Datos esperados (ver OrdenesTrabajoController::create()): la cáscara de
    CascaraPanel, más:
    - $ordenesDisponibles (Collection<int, string>): id → label de cada orden
      vigente con hectáreas por repartir.
    - $datosOrden (array<int, array{label, es_liquido, cantidad_equipos,
      restantes_total, litros_ha, lotes}>): por cada orden, sus lotes
      pendientes con las hectáreas que les quedan.
    - $ordenPreseleccionadaId (?int): si se llega con ?orden_id=X en la URL.
    - $ordenSinPendiente (bool): esa orden ya no admite otra Orden de Trabajo.
    - $equiposDisponibles (Collection<int, string>): cuadrillas vigentes hoy.
    - $puedeCrearCuadrilla (bool): permiso `personal.equipo_trabajo.crear` del
      rol activo, para ofrecer o no el acceso rápido «Crear cuadrilla».

    Gateada por `operaciones.trabajo.crear`. Estilos en
    resources/css/pages/ordenes-trabajo.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $ordenPreseleccionadaId = $ordenPreseleccionadaId ?? null;
    $ordenesDisponibles = $ordenesDisponibles ?? collect();
    $datosOrden = $datosOrden ?? [];
    $equiposDisponibles = $equiposDisponibles ?? collect();
    $puedeCrearCuadrilla = $puedeCrearCuadrilla ?? false;
    $ordenSinPendiente = $ordenSinPendiente ?? false;

    $ordenId = old('orden_id', $ordenPreseleccionadaId ?? '');
    $ordenElegida = $ordenId !== '' && isset($datosOrden[$ordenId]) ? $datosOrden[$ordenId] : null;
    $parametrosAntiguos = old('parametros', []);

    $esLiquido = $ordenElegida['es_liquido'] ?? false;
    $cantidadEquipos = $ordenElegida['cantidad_equipos'] ?? 0;

    $equiposAntiguos = old('equipos', []);

    // Memento de navegación: el acceso rápido «Crear cuadrilla» le dice a
    // `RecordarOrigenNavegacion` que se vuelve a ESTA pantalla, con la orden
    // ya elegida.
    $urlActual = route('panel.trabajos.create', array_filter(['orden_id' => $ordenId]));
    $urlCrearCuadrilla = route('panel.cuadrillas.create', [
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
            data-ag-borrador="propio"
            data-ag-url-crear="{{ route('panel.trabajos.create') }}"
            data-ag-url-cuadrilla="{{ route('panel.cuadrillas.create') }}"
            data-ag-volver-texto="{{ __('operaciones.ordenes_trabajo.crear_titulo') }}"
            data-ag-ordenes="{{ json_encode($datosOrden) }}"
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

            @if ($ordenSinPendiente)
                <x-molecules.alert-strip variant="warning" icon="info">
                    {{ __('operaciones.ordenes_trabajo.orden_sin_pendiente') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.form-layout>
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

                {{-- Datos del contrato: el mismo cuadro del formulario de la orden de
                     aplicación (pedido del dueño, 21/9/2026). Lo pinta el servidor para
                     la orden que llega elegida y el JS lo repinta al elegir otra. --}}
                <div class="ag-form-section__field--full ag-ordenes-form__resumen-contrato" data-ag-resumen-contrato @if ($ordenElegida === null) hidden @endif>
                    <img
                        class="ag-ordenes-form__logo"
                        data-ag-cliente-logo
                        data-logo-placeholder="{{ asset('images/logo-placeholder.png') }}"
                        src="{{ $ordenElegida['logo_url'] ?? asset('images/logo-placeholder.png') }}"
                        alt=""
                    >
                    <div class="ag-form-section__body ag-ordenes-form__resumen-datos">
                        @foreach ([
                            'contrato' => __('operaciones.ordenes.campo_contrato'),
                            'cliente' => __('operaciones.ordenes.campo_contrato_cliente'),
                            'propiedades' => __('operaciones.ordenes.campo_contrato_propiedades'),
                            'aplicacion' => __('operaciones.ordenes.kpi_aplicaciones'),
                            'insumo' => __('operaciones.ordenes.campo_categoria_insumo'),
                            'por_repartir' => __('operaciones.ordenes_trabajo.campo_por_repartir'),
                        ] as $dato => $etiqueta)
                            <div class="ag-ordenes-detalle__campo">
                                <p class="ag-ordenes-detalle__campo-label">{{ $etiqueta }}</p>
                                <p class="ag-ordenes-detalle__campo-valor" data-ag-dato-orden="{{ $dato }}">{{ $ordenElegida[$dato] ?? '—' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-molecules.form-section>

            <div class="ag-ordenes-trabajo-form__parte">
                <span class="ag-ordenes-trabajo-form__parte-numero" aria-hidden="true">1</span>
                <div>
                    <h2 class="ag-ordenes-trabajo-form__parte-titulo">{{ __('operaciones.ordenes_trabajo.parte_indicaciones_titulo') }}</h2>
                    <p class="ag-ordenes-trabajo-form__parte-detalle">{{ __('operaciones.ordenes_trabajo.parte_indicaciones_detalle') }}</p>
                </div>
            </div>

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

            <div class="ag-ordenes-trabajo-form__parte">
                <span class="ag-ordenes-trabajo-form__parte-numero" aria-hidden="true">2</span>
                <div>
                    <h2 class="ag-ordenes-trabajo-form__parte-titulo">{{ __('operaciones.ordenes_trabajo.parte_trabajos_titulo') }}</h2>
                    <p class="ag-ordenes-trabajo-form__parte-detalle">{{ __('operaciones.ordenes_trabajo.parte_trabajos_detalle') }}</p>
                </div>
            </div>

            <x-molecules.form-section
                :title="__('operaciones.ordenes_trabajo.seccion_equipos')"
                :count="trans_choice('operaciones.ordenes_trabajo.equipos_contador', $cantidadEquipos, ['cantidad' => $cantidadEquipos])"
                data-ag-equipos
                :data-ag-contador-ninguno="trans_choice('operaciones.ordenes_trabajo.equipos_contador', 0)"
                :data-ag-contador-uno="trans_choice('operaciones.ordenes_trabajo.equipos_contador', 1, ['cantidad' => 1])"
                :data-ag-contador-varios="trans_choice('operaciones.ordenes_trabajo.equipos_contador', 2, ['cantidad' => ':cantidad'])"
            >
                <div class="ag-form-section__field--full" data-ag-equipos-vacio @if ($ordenElegida !== null) hidden @endif>
                    <x-molecules.empty-state
                        icon="groups"
                        :title="__('operaciones.ordenes_trabajo.equipos_vacio_titulo')"
                        :detail="__('operaciones.ordenes_trabajo.equipos_vacio_detalle')"
                    />
                </div>

                <div
                    class="ag-form-section__field--full ag-ordenes-trabajo-form__reparto-cuerpo"
                    data-ag-equipos-contenido
                    @if ($ordenElegida === null) hidden @endif
                >
                    <p
                        class="ag-ordenes-trabajo-form__ayuda"
                        data-ag-equipos-ayuda
                        data-ag-ayuda-uno="{{ trans_choice('operaciones.ordenes_trabajo.seccion_equipos_ayuda', 1) }}"
                        data-ag-ayuda-varios="{{ trans_choice('operaciones.ordenes_trabajo.seccion_equipos_ayuda', 2) }}"
                    >{{ trans_choice('operaciones.ordenes_trabajo.seccion_equipos_ayuda', max(1, $cantidadEquipos)) }}</p>

                    @php
                        // Lo repartido viaja en campos ocultos: un error de un lote no
                        // tiene campo visible donde mostrarse, así que sale acá.
                        $errorReparto = $errors->first('equipos')
                            ?: $errors->first('equipos.*.lotes')
                            ?: $errors->first('equipos.*.lotes.*.lote_id')
                            ?: $errors->first('equipos.*.lotes.*.hectareas');
                    @endphp
                    @if ($errorReparto)
                        <x-molecules.alert-strip variant="danger" icon="error">
                            {{ $errorReparto }}
                        </x-molecules.alert-strip>
                    @endif

                    {{-- Criterio de reparto (pedido del dueño, 19/9 y 21/9/2026): los lotes
                         y las hectáreas de cada equipo no se cargan a mano — se elige un
                         criterio y el formulario los reparte; queda solo elegir la
                         cuadrilla. El servidor valida igual lo repartido. El criterio en
                         sí no se guarda. Con un solo equipo no hay nada que repartir: va
                         oculto. --}}
                    <div data-ag-reparto-modo-envoltorio @if ($cantidadEquipos < 2) hidden @endif>
                        <x-atoms.radio-group
                            name="reparto_modo"
                            id="reparto-modo"
                            :label="__('operaciones.ordenes_trabajo.reparto_modo')"
                            :options="[
                                'parejo' => __('operaciones.ordenes_trabajo.reparto_modo_parejo'),
                                'dificultad' => __('operaciones.ordenes_trabajo.reparto_modo_dificultad'),
                            ]"
                            :value="old('reparto_modo', 'parejo')"
                            :help="__('operaciones.ordenes_trabajo.reparto_modo_ayuda')"
                            data-ag-reparto-modo
                        />
                    </div>

                    <div
                        class="ag-form-section__body ag-ordenes-trabajo-form__equipos"
                        data-ag-equipos-lista
                        data-ag-lotes-plantilla="{{ __('operaciones.ordenes_trabajo.equipo_lotes_texto') }}"
                        data-ag-lote-plantilla="{{ __('operaciones.ordenes_trabajo.equipo_lote_item') }}"
                    >
                        @for ($indiceEquipo = 0; $indiceEquipo < $cantidadEquipos; $indiceEquipo++)
                            @include('operaciones::pages.ordenes-trabajo._equipo-bloque', [
                                'indiceEquipo' => $indiceEquipo,
                                'numero' => $indiceEquipo + 1,
                                'equipo' => $equiposAntiguos[$indiceEquipo] ?? [],
                                'obligatorio' => $indiceEquipo === 0,
                                'equiposDisponibles' => $equiposDisponibles,
                                'urlCrearCuadrilla' => $urlCrearCuadrilla,
                                'puedeCrearCuadrilla' => $puedeCrearCuadrilla,
                            ])
                        @endfor
                    </div>

                    <p
                        class="ag-ordenes-trabajo-form__reparto"
                        data-ag-reparto
                        data-ag-reparto-plantilla="{{ __('operaciones.ordenes_trabajo.reparto_resumen') }}"
                        aria-live="polite"
                    ></p>
                </div>

                {{-- Moldes de un bloque de equipo —el primero, obligatorio, y los
                     demás— para armar los bloques al elegir otra orden. --}}
                @foreach (['obligatorio' => true, 'opcional' => false] as $claveMolde => $esObligatorio)
                    <template data-ag-equipo-template="{{ $claveMolde }}">
                        @include('operaciones::pages.ordenes-trabajo._equipo-bloque', [
                            'indiceEquipo' => '__INDICE_EQUIPO__',
                            'numero' => '__NUMERO_EQUIPO__',
                            'equipo' => [],
                            'obligatorio' => $esObligatorio,
                            'equiposDisponibles' => $equiposDisponibles,
                            'urlCrearCuadrilla' => $urlCrearCuadrilla,
                            'puedeCrearCuadrilla' => $puedeCrearCuadrilla,
                        ])
                    </template>
                @endforeach
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
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>
            </x-molecules.form-layout>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
