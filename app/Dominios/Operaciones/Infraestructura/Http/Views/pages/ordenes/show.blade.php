{{--
    Page: ordenes/show (GET /panel/ordenes/{orden}, panel.ordenes.show)
    Detalle de solo lectura de una orden de aplicación (homogeneización
    17/9/2026): primera pantalla del arquetipo Detalle del panel — no existía
    ninguno todavía (§6.4 de docs/diseno/guia_pantalla_panel.md documenta el
    patrón que arma esta vista, para que la próxima pantalla "ver detalle" de
    Operaciones lo copie).

    Por qué hace falta: mientras la orden es `emitida`, `edit()` cumple este
    rol; apenas se activa, el link "Editar" se oculta (`Aplicacion/ActualizarOrden`
    exige `emitida`) y hasta ahora no quedaba ningún lugar del panel para
    volver a ver sus datos completos.

    Datos esperados (ver OrdenesController::show()): la cáscara de
    CascaraPanel, más $orden (con `categoriaInsumo` cargada),
    $contratoLabel, $contactoLabel (nullable), $resumenContrato (array
    nullable, ver `resumenContrato()` — reforma 18/9/2026: cliente, logo,
    propiedad(es), aplicaciones pactadas, hectáreas contratadas, fecha de
    inicio/fin del contrato; mismos campos que "Datos del contrato" de
    create()/edit()), $aplicacionesPrevistas (nullable, espejo de
    `$resumenContrato['aplicaciones_previstas']` para el KPI de arriba),
    $lotes (list, ver detalleLotesOrden()),
    $hectareasSolicitadas/$hectareasAsignadas (string, ya formateadas),
    $porcentajeAsignado (int), $equiposAsignados (int), $actividad (list,
    ver actividadOrden()), $puedeEditar/$puedeActivar/$puedeEliminar (bool).

    Gateada por `operaciones.orden.ver` — mismo permiso que `index()`/`edit()`,
    sin permiso nuevo (mismo criterio que las 5 pantallas `.show` ya
    homogeneizadas del panel: Trabajos, Devengos, Planillas, Rendiciones,
    EquiposTrabajo).

    Estilos en resources/css/pages/ordenes.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $estadoValor = $orden->estado->value;
    $variantePorEstado = [
        'emitida' => 'neutral',
        'vigente' => 'success',
        'consumida' => 'info',
        'vencida' => 'danger',
    ];
    $tipoInsumo = $orden->categoriaInsumo?->tipo_insumo?->value;
    $dosisTexto = $orden->kilos_por_vuelo !== null
        ? __('operaciones.ordenes.dosis_kilos_por_vuelo', ['cantidad' => number_format((float) $orden->kilos_por_vuelo, 2, ',', '.')])
        : ($orden->litros_ha !== null
            ? __('operaciones.ordenes.dosis_litros_ha', ['cantidad' => number_format((float) $orden->litros_ha, 2, ',', '.')])
            : '—');
    $formIdActivar = "orden-activar-{$orden->id}";
    $formIdEliminar = "orden-eliminar-{$orden->id}";
    $modalIdActivar = "orden-activar-modal-{$orden->id}";
    $modalIdEliminar = "orden-eliminar-modal-{$orden->id}";

    // Estados de color de la tira de KPI (17/9/2026, pedido explícito: se
    // veía "plana" sin ningún `state` — ver docblock de `molecules/stat-card`).
    // Dos colores FIJOS: hectáreas=info (azul), categoría de insumo=
    // distintivo-1 (violeta, 18/9/2026 — antes quedaba sin `state` a
    // propósito porque con success fijo colisionaba con "aplicaciones"
    // cada vez que esa completaba su meta, dos verdes por casualidad, no
    // por relación entre los datos; "distintivo" es justo el tono
    // categórico sin carga de bueno/malo que resuelve eso, ver
    // primitives/base.css).
    // "Aplicaciones"/"equipos" son 100% dinámicos: arrancan neutros (sin
    // `state`) y solo se colorean cuando hay algo real que mostrar —
    // "success" cuando la meta se cumple de verdad (ahí SÍ pueden coincidir
    // los dos en verde a la vez: es información real, "las dos metas se
    // cumplieron", no una casualidad decorativa), "warning" en equipos solo
    // con la orden YA `vigente` — antes de activarla, no tener equipos
    // asignados es el estado normal (`AsignarEquiposOrden` ni lo permite
    // todavía), no algo que reclame atención.
    $aplicacionesCompletas = $aplicacionesPrevistas !== null && $orden->nro_aplicacion >= $aplicacionesPrevistas;
    $equiposCompletos = $equiposAsignados >= $orden->cantidad_equipos_necesarios;
    $aplicacionesEstado = $aplicacionesCompletas ? 'success' : null;
    $equiposEstado = $equiposCompletos ? 'success' : ($estadoValor === 'vigente' ? 'warning' : null);
@endphp
<x-templates.panel-shell :title="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])"
    >
        <div class="ag-ordenes-detalle">
            <x-organisms.page-header
                :title="__('operaciones.ordenes.detalle_titulo', ['id' => $orden->id])"
                :subtitle="__('operaciones.ordenes.detalle_subtitulo', ['cliente' => $contratoLabel, 'fecha' => $orden->fecha_emision->format('d/m/Y')])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                        {{ __('operaciones.estado.'.$estadoValor) }}
                    </x-atoms.badge>
                </x-slot:chip>

                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.ordenes.index')" :label="__('operaciones.ordenes.volver')" />

                    @if ($puedeEditar && $estadoValor === 'emitida')
                        <x-atoms.button :href="route('panel.ordenes.edit', $orden)" variant="primary" icon="edit">
                            {{ __('operaciones.ordenes.editar') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeActivar && $estadoValor === 'emitida')
                        <form id="{{ $formIdActivar }}" method="POST" action="{{ route('panel.ordenes.activar', $orden) }}">
                            @csrf
                        </form>

                        <x-molecules.confirm-modal
                            :id="$modalIdActivar"
                            :form-id="$formIdActivar"
                            :title="__('operaciones.ordenes.confirmar_activar_titulo')"
                            :message="__('operaciones.ordenes.confirmar_activar')"
                            :confirm-label="__('operaciones.ordenes.activar_accion')"
                            tone="success"
                        />

                        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdActivar }}" variant="success-outline" icon="check_circle">
                            {{ __('operaciones.ordenes.activar_accion') }}
                        </x-atoms.button>
                    @endif

                    @if ($puedeEliminar && $estadoValor !== 'vigente')
                        <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.ordenes.destroy', $orden) }}">
                            @csrf
                            @method('DELETE')
                        </form>

                        <x-molecules.confirm-modal
                            :id="$modalIdEliminar"
                            :form-id="$formIdEliminar"
                            :title="__('operaciones.ordenes.confirmar_eliminar_titulo')"
                            :message="__('operaciones.ordenes.confirmar_baja')"
                            :confirm-label="__('operaciones.ordenes.eliminar_accion')"
                            tone="danger"
                        />

                        <x-atoms.button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalIdEliminar }}" variant="danger-outline" icon="delete">
                            {{ __('operaciones.ordenes.eliminar_accion') }}
                        </x-atoms.button>
                    @endif
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-ordenes-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.aside_hectareas_solicitadas')"
                    icon="landscape"
                    :value="$hectareasSolicitadas"
                    value-suffix="ha"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.campo_categoria_insumo')"
                    icon="science"
                    :value="$dosisTexto"
                    state="distintivo-1"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.kpi_aplicaciones')"
                    icon="repeat"
                    :value="$orden->nro_aplicacion"
                    :value-suffix="$aplicacionesPrevistas !== null ? __('operaciones.ordenes.kpi_aplicaciones_sufijo', ['total' => $aplicacionesPrevistas]) : null"
                    :state="$aplicacionesEstado"
                />
                <x-molecules.stat-card
                    :label="__('operaciones.ordenes.kpi_equipos_necesarios')"
                    icon="groups"
                    :value="$orden->cantidad_equipos_necesarios"
                    :foot="__('operaciones.ordenes.kpi_equipos_asignados_pie', ['asignados' => $equiposAsignados, 'necesarios' => $orden->cantidad_equipos_necesarios])"
                    :foot-tone="$equiposCompletos ? 'success' : ($estadoValor === 'vigente' ? 'warning' : 'muted')"
                    :state="$equiposEstado"
                />
            </div>

            @php
                $datosContratoOrden = [
                    ['label' => __('operaciones.ordenes.campo_contrato'), 'value' => $contratoLabel],
                    ['label' => __('operaciones.ordenes.campo_contacto'), 'value' => $contactoLabel ?? __('operaciones.ordenes.campo_contacto_placeholder')],
                ];
                $datosOrden = [
                    ['label' => __('operaciones.ordenes.campo_tipo_insumo'), 'value' => $tipoInsumo !== null ? __('operaciones.tipo_insumo.'.$tipoInsumo) : '—'],
                    ['label' => __('operaciones.ordenes.campo_categoria_insumo'), 'value' => $orden->categoriaInsumo?->nombre ?? '—'],
                    ['label' => __('operaciones.ordenes.campo_tipo_aplicacion'), 'value' => __('operaciones.tipo_aplicacion.'.$orden->tipo_aplicacion->value)],
                    ['label' => __('operaciones.ordenes.campo_fecha_emision'), 'value' => $orden->fecha_emision->format('d/m/Y')],
                ];
            @endphp

            {{-- `accent` de cada form-section (18/9/2026, pedido explícito
                del usuario, varias vueltas hasta el orden definitivo;
                reajustado el mismo día al sumar "Datos del contrato" y sacar
                Límites climáticos/Parámetros de vuelo, que liberaron dos
                tonos). Motivo: con 7 `section-head` en esta pantalla (6
                `form-section` + `progress-meter`, "Avance de asignación",
                más abajo) y solo 6 tonos sin carga de bueno/malo, alguno se
                repetía sin importar el orden ("no se repita como se ve").
                Mapeo vigente, sin repetir ninguno: `primary-2`→Datos del
                contrato, `success`→Datos de la orden, `info`→Lotes,
                `distintivo-2`→Actividad, `alert`→Vínculos ("Relacionado"),
                `distintivo-1`→Avance de asignación (coincide con su propio
                porcentaje, ver `progress-meter.blade.php`). `warning` y
                `danger` quedan sin usar acá.
                Nota: `success` en "Datos de la orden" comparte tono con
                el KPI "Aplicaciones" de esta misma pantalla cuando
                completa su meta — decisión explícita del usuario, no un
                descuido (se le consultó puntualmente antes de fijarlo). --}}
            <x-molecules.form-layout>
                {{--
                    "Datos del contrato" (reforma 18/9/2026): mismos campos
                    que la sección homónima de create()/edit() — contrato,
                    contacto, y el resumen con logo/propiedad(es)/aplicaciones
                    pactadas/hectáreas contratadas/fechas. `$resumenContrato`
                    puede ser `null` (contrato borrado lógicamente después de
                    emitida la orden) — ahí se omite el resumen, quedan solo
                    contrato/contacto.
                --}}
                <x-molecules.form-section accent="primary-2" :title="__('operaciones.ordenes.seccion_datos_contrato')">
                    @foreach ($datosContratoOrden as $campo)
                        <div class="ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ $campo['label'] }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $campo['value'] }}</p>
                        </div>
                    @endforeach

                    @if ($resumenContrato)
                        <div class="ag-form-section__field--full ag-ordenes-form__resumen-contrato">
                            <img
                                class="ag-ordenes-form__logo"
                                src="{{ $resumenContrato['logo_url'] ?? asset('images/logo-placeholder.png') }}"
                                alt=""
                            >
                            <div class="ag-form-section__body ag-ordenes-form__resumen-datos">
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_cliente') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['cliente'] }}</p>
                                </div>
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_propiedades') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['propiedades'] === [] ? '—' : implode(', ', $resumenContrato['propiedades']) }}</p>
                                </div>
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_aplicaciones') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['aplicaciones_previstas'] }}</p>
                                </div>
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_hectareas') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['hectareas_contratadas'] }} ha</p>
                                </div>
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_fecha_inicio') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['fecha_inicio'] }}</p>
                                </div>
                                <div class="ag-ordenes-detalle__campo">
                                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_fecha_fin') }}</p>
                                    <p class="ag-ordenes-detalle__campo-valor">{{ $resumenContrato['fecha_fin'] ?? __('operaciones.ordenes.valor_sin_definir') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-molecules.form-section accent="success" :title="__('operaciones.ordenes.seccion_datos')" :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 4])">
                    @foreach ($datosOrden as $campo)
                        <div class="ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ $campo['label'] }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $campo['value'] }}</p>
                        </div>
                    @endforeach

                    @if ($orden->observaciones)
                        <div class="ag-form-section__field--full ag-ordenes-detalle__campo">
                            <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_observaciones') }}</p>
                            <p class="ag-ordenes-detalle__campo-valor">{{ $orden->observaciones }}</p>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-molecules.form-section accent="info" :title="__('operaciones.ordenes.seccion_lotes')" :count="__('operaciones.ordenes.lotes_contador', ['cantidad' => count($lotes), 'hectareas' => $hectareasSolicitadas])">
                    <div class="ag-form-section__field--full">
                        <x-molecules.index-table columns="1fr 8rem 9rem">
                            <x-slot:head>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_lote') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_hectareas_lote') }}</span>
                                <span role="columnheader">{{ __('operaciones.ordenes.col_estado') }}</span>
                            </x-slot:head>

                            @foreach ($lotes as $lote)
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell">{{ $lote['label'] }}</span>
                                    <span role="cell" class="ag-ordenes__mono">{{ number_format((float) $lote['hectareas_solicitadas'], 2, ',', '.') }}</span>
                                    <span role="cell">
                                        <x-atoms.badge :variant="$lote['estado'] === 'asignado' ? 'success' : 'neutral'">
                                            {{ __('operaciones.ordenes.lote_estado_'.$lote['estado']) }}
                                        </x-atoms.badge>
                                    </span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-2" :title="__('operaciones.ordenes.seccion_actividad')">
                    <div class="ag-form-section__field--full">
                        <x-molecules.timeline :items="$actividad" />
                    </div>
                </x-molecules.form-section>

                @if (count($vinculos))
                    <x-molecules.form-section accent="alert" :title="__('operaciones.ordenes.seccion_vinculos')">
                        <div class="ag-form-section__field--full ag-ordenes-detalle__vinculos">
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

                <x-slot:aside>
                    {{--
                        Límites climáticos y parámetros de vuelo (5+3 campos)
                        se mostraban acá hasta la reforma del 18/9/2026: ya no
                        son datos de la orden, se movieron a `Trabajo` (cargados
                        por equipo en Asignación de equipos) — ver docblock de
                        `OrdenAplicacion` y de `AsignarEquipoOrdenRequest`.
                    --}}
                    <x-molecules.progress-meter
                        :title="__('operaciones.ordenes.avance_titulo')"
                        :percent="$porcentajeAsignado"
                        :summary-label="__('operaciones.ordenes.avance_resumen', ['asignadas' => $hectareasAsignadas, 'solicitadas' => $hectareasSolicitadas])"
                    />
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
