{{--
    Page: reportes-comerciales/index (GET /panel/reportes/comercial, panel.reportes.comercial.index)
    Informe de avance de contratos (HU-52, tarea 75, espec §9.1; homogeneizado
    en la tarea 120): agrupado por cultivo y por cliente. Reemplaza a HU-32
    (tarea 46) en la misma ruta y permiso (`comercial.reporte.ver`, exclusivo
    del dueño). Solo lectura y sin descarga: el informe no se exporta (ver
    ReportesComercialesController).

    Dos estados, según `consultado`:
    - Entrada: al menos un cliente y un cultivo, sin los cuales no se genera.
      El selector que falta muestra su error bajo su propio campo. El marcador
      `consultado` (hidden) distingue "primera visita" (sin error) de "intento
      sin completar la entrada" (con error).
    - Resultados: toolbar con `organisms/filter-panel` —los filtros opcionales—
      y, a la derecha, qué incluye el informe con «Cambiar selección» (vuelve a
      la entrada con todo premarcado); debajo, las pestañas «Por cultivo» /
      «Por cliente» con sus tablas (`molecules/index-table`). Un informe sin
      filas es un `empty-state` de filtro sin resultados, sin botón.

    Datos esperados (ver ReportesComercialesController::index()):
    - consultado (bool)
    - erroresEntrada (array{cliente_ids?: string, cultivo_ids?: string})
    - informe (?InformeAvanceContratos) — null si sin consulta o con error
    - tabActiva ('por_cultivo'|'por_cliente')
    - clienteIds, cultivoIds, campaniaIds (list<int>)
    - fechaDesde, fechaHasta (?string, 'Y-m-d')
    - estado (?EstadoContrato)
    - saldo (?SaldoContrato)
    - incluirDeshabilitados (bool)
    - clientesDisponibles (Collection<Cliente>)
    - cultivosDisponibles (Collection<Cultivo>)
    - campaniasDisponibles (Collection<stdClass{id,codigo}>): sin cliente_id/
      razon_social desde el 15/9/2026 (ADR 0015) — la campaña ya no es de un
      cliente, el código alcanza como etiqueta.
    - estadosDisponibles (list<EstadoContrato>)
    - saldosDisponibles (list<SaldoContrato>)

    Gateada por `comercial.reporte.ver`, verificado server-side en el
    controlador.

    El JS de la entrada (`resources/js/pages/reportes-comerciales.js`) entra
    por el bundle de `app.js`: acá no se enlaza aparte.

    Estilos en resources/css/pages/reportes-comerciales.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.reportes_comerciales.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.reportes_comerciales.titulo')"
    >
        <div class="ag-reportes-comerciales">
            {{-- Cabecera sin acciones (HU-52 no tiene botón primario sobre el pliegue) --}}
            <x-organisms.page-header
                :title="__('comercial.reportes_comerciales.titulo')"
                :subtitle="__('comercial.reportes_comerciales.subtitulo')"
            ></x-organisms.page-header>

            {{-- Sin clientes o sin cultivos no hay nada que elegir: la
                 pantalla de entrada quedaría con un checkbox-group vacío,
                 imposible de completar. --}}
            @if ($clientesDisponibles->isEmpty() || $cultivosDisponibles->isEmpty())
                <x-molecules.empty-state
                    icon="insert_chart"
                    :title="__('comercial.reportes_comerciales.sin_datos_titulo')"
                    :detail="__('comercial.reportes_comerciales.sin_datos_detalle')"
                />
            {{-- Entrada: checkbox-group de cliente y de cultivo --}}
            @elseif (!$consultado || !empty($erroresEntrada))
                <form method="GET" action="{{ route('panel.reportes.comercial.index') }}" class="ag-reportes-comerciales__entrada">
                    <input type="hidden" name="consultado" value="1">

                    <x-molecules.form-section
                        :title="__('comercial.reportes_comerciales.entrada.titulo')"
                        :count="__('comercial.reportes_comerciales.entrada.campos_contador', ['cantidad' => 2])"
                    >
                        <x-atoms.checkbox-group
                            name="cliente_ids"
                            id="entrada-clientes"
                            :label="__('comercial.reportes_comerciales.entrada.cliente')"
                            :options="$clientesDisponibles->mapWithKeys(fn($c) => [$c->id => $c->razon_social])"
                            :value="$clienteIds"
                            :error="$erroresEntrada['cliente_ids'] ?? null"
                        />

                        <x-atoms.checkbox-group
                            name="cultivo_ids"
                            id="entrada-cultivos"
                            :label="__('comercial.reportes_comerciales.entrada.cultivo')"
                            :options="$cultivosDisponibles->mapWithKeys(fn($c) => [$c->id => $c->nombre_comun])"
                            :value="$cultivoIds"
                            :error="$erroresEntrada['cultivo_ids'] ?? null"
                        />
                    </x-molecules.form-section>

                    <div class="ag-reportes-comerciales__acciones-entrada">
                        <x-atoms.button
                            type="submit"
                            variant="primary"
                            icon="check"
                            id="boton-generar"
                        >
                            {{ __('comercial.reportes_comerciales.entrada.generar') }}
                        </x-atoms.button>
                    </div>
                </form>
            @else
                {{-- Resultados --}}
                @php
                    // Cliente y cultivo son la entrada obligatoria: siempre hay, así que no
                    // cuentan como filtros activos. Cuenta lo opcional que se sumó encima.
                    $filtrosActivos = collect([
                        ! empty($campaniaIds),
                        $fechaDesde !== null,
                        $fechaHasta !== null,
                        $estado !== null,
                        $saldo !== null,
                        $incluirDeshabilitados,
                    ])->filter()->count();
                @endphp

                <div class="ag-table-toolbar">
                    {{-- Solo los filtros OPCIONALES: la selección de clientes y cultivos es la
                         entrada del informe, no un filtro, y en el panel ocuparía más alto que la
                         pantalla. Viaja oculta para no perderse al aplicar. «Limpiar» del organism
                         vuelve a esta misma URL: conserva esa selección y quita solo lo opcional. --}}
                    <x-organisms.filter-panel
                        :action="route('panel.reportes.comercial.index', ['consultado' => 1, 'cliente_ids' => $clienteIds, 'cultivo_ids' => $cultivoIds])"
                        :active-count="$filtrosActivos"
                    >
                        <input type="hidden" name="consultado" value="1">
                        <input type="hidden" name="tab" value="{{ $tabActiva }}">
                        @foreach ($clienteIds as $clienteId)
                            <input type="hidden" name="cliente_ids[]" value="{{ $clienteId }}">
                        @endforeach
                        @foreach ($cultivoIds as $cultivoId)
                            <input type="hidden" name="cultivo_ids[]" value="{{ $cultivoId }}">
                        @endforeach

                        @if (! $campaniasDisponibles->isEmpty())
                            <x-atoms.checkbox-group
                                class="ag-form-section__field--full"
                                name="campania_ids"
                                id="filtros-campanias"
                                :label="__('comercial.reportes_comerciales.filtros.campania')"
                                :options="$campaniasDisponibles->mapWithKeys(fn($c) => [$c->id => $c->codigo])"
                                :value="$campaniaIds"
                            />
                        @endif

                        <x-atoms.date
                            name="fecha_desde"
                            id="filtros-fecha-desde"
                            :label="__('comercial.reportes_comerciales.filtros.fecha_desde')"
                            :value="$fechaDesde"
                        />

                        <x-atoms.date
                            name="fecha_hasta"
                            id="filtros-fecha-hasta"
                            :label="__('comercial.reportes_comerciales.filtros.fecha_hasta')"
                            :value="$fechaHasta"
                        />

                        <x-atoms.select
                            name="estado"
                            id="filtros-estado"
                            :label="__('comercial.reportes_comerciales.filtros.estado')"
                            :options="collect($estadosDisponibles)->mapWithKeys(fn($e) => [$e->value => __('comercial.contrato.estado.' . $e->value)])"
                            :value="$estado?->value"
                            :placeholder="__('comercial.reportes_comerciales.filtros.seleccionar')"
                        />

                        <x-atoms.select
                            name="saldo"
                            id="filtros-saldo"
                            :label="__('comercial.reportes_comerciales.filtros.saldo')"
                            :options="collect($saldosDisponibles)->mapWithKeys(fn($s) => [$s->value => __('comercial.saldo.' . $s->value)])"
                            :value="$saldo?->value"
                            :placeholder="__('comercial.reportes_comerciales.filtros.seleccionar')"
                        />

                        <x-atoms.switch
                            class="ag-form-section__field--full"
                            name="incluir_deshabilitados"
                            id="filtros-incluir-deshabilitados"
                            :label="__('comercial.reportes_comerciales.filtros.incluir_deshabilitados')"
                            value="1"
                            :checked="$incluirDeshabilitados"
                        />
                    </x-organisms.filter-panel>

                    {{-- Qué incluye el informe y cómo cambiarlo: vuelve a la entrada con la
                         selección actual ya marcada (el controlador la lee aunque no haya
                         `consultado`). --}}
                    <div class="ag-reportes-comerciales__seleccion">
                        <span class="ag-reportes-comerciales__seleccion-resumen">
                            {{ trans_choice('comercial.reportes_comerciales.seleccion.clientes', count($clienteIds), ['cantidad' => count($clienteIds)]) }}
                            ·
                            {{ trans_choice('comercial.reportes_comerciales.seleccion.cultivos', count($cultivoIds), ['cantidad' => count($cultivoIds)]) }}
                        </span>
                        <x-atoms.button
                            :href="route('panel.reportes.comercial.index', ['cliente_ids' => $clienteIds, 'cultivo_ids' => $cultivoIds])"
                            variant="outline"
                            icon="tune"
                        >
                            {{ __('comercial.reportes_comerciales.seleccion.cambiar') }}
                        </x-atoms.button>
                    </div>
                </div>

                {{-- Informe o vacío de filtro sin resultados --}}
                @if ($informe !== null && !empty($informe->porCultivo))
                    {{--
                        molecules/tabs SOLO pinta el nav (recibe `items`, ver su
                        docblock) — el tab-content/tab-pane lo arma esta página,
                        como hermano suyo, nunca como hijo (el componente no
                        imprime $slot).
                    --}}
                    <x-molecules.tabs
                        :items="[
                            ['id' => 'tab-por-cultivo', 'label' => __('comercial.reportes_comerciales.tabs.por_cultivo'), 'active' => $tabActiva === 'por_cultivo'],
                            ['id' => 'tab-por-cliente', 'label' => __('comercial.reportes_comerciales.tabs.por_cliente'), 'active' => $tabActiva === 'por_cliente'],
                        ]"
                        class="ag-reportes-comerciales__tabs"
                    />

                    <div class="tab-content">
                        {{-- Pestaña: Por cultivo --}}
                        <div
                            id="tab-por-cultivo"
                            class="tab-pane fade {{ $tabActiva === 'por_cultivo' ? 'show active' : '' }}"
                            role="tabpanel"
                        >
                            @foreach ($informe->porCultivo as $grupoCultivo)
                                <div class="ag-reportes-comerciales__grupo-cultivo">
                                    <div class="ag-reportes-comerciales__encabezado-grupo">
                                        <h2 class="ag-reportes-comerciales__nombre-cultivo">
                                            {{ $grupoCultivo->cultivoNombre }}
                                        </h2>
                                        <x-molecules.tiered-progress-bar
                                            :percent="$grupoCultivo->porcentaje"
                                            :tramo="$grupoCultivo->tramo->value"
                                        />
                                    </div>

                                    @include('comercial::pages.reportes-comerciales._tabla-contratos', [
                                        'contratos' => $grupoCultivo->contratos,
                                        'totales' => $grupoCultivo,
                                    ])
                                </div>
                            @endforeach
                        </div>

                        {{-- Pestaña: Por cliente --}}
                        <div
                            id="tab-por-cliente"
                            class="tab-pane fade {{ $tabActiva === 'por_cliente' ? 'show active' : '' }}"
                            role="tabpanel"
                        >
                            @foreach ($informe->porCultivo as $grupoCultivo)
                                <div class="ag-reportes-comerciales__grupo-cultivo">
                                    <div class="ag-reportes-comerciales__encabezado-grupo">
                                        <h2 class="ag-reportes-comerciales__nombre-cultivo">
                                            {{ $grupoCultivo->cultivoNombre }}
                                        </h2>
                                        <x-molecules.tiered-progress-bar
                                            :percent="$grupoCultivo->porcentaje"
                                            :tramo="$grupoCultivo->tramo->value"
                                        />
                                    </div>

                                    {{-- Acordeón de clientes dentro de cada cultivo. Un mismo cliente
                                         puede estar en varios cultivos: el id del panel lleva también
                                         el del cultivo, o el segundo abriría el primero. --}}
                                    <div class="accordion ag-reportes-comerciales__accordion" id="grupo-clientes-{{ $grupoCultivo->cultivoId }}">
                                        @foreach ($grupoCultivo->clientes as $grupoCliente)
                                            @php $panelId = "collapse-{$grupoCultivo->cultivoId}-cliente-{$grupoCliente->clienteId}"; @endphp
                                            <div class="accordion-item">
                                                <h3 class="accordion-header">
                                                    <button
                                                        type="button"
                                                        class="accordion-button collapsed"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#{{ $panelId }}"
                                                        aria-expanded="false"
                                                        aria-controls="{{ $panelId }}"
                                                    >
                                                        <span>{{ $grupoCliente->clienteNombre }}</span>
                                                        <x-molecules.tiered-progress-bar
                                                            :percent="$grupoCliente->porcentaje"
                                                            :tramo="$grupoCliente->tramo->value"
                                                            class="ag-reportes-comerciales__barra-compacta"
                                                        />
                                                    </button>
                                                </h3>
                                                <div
                                                    id="{{ $panelId }}"
                                                    class="accordion-collapse collapse"
                                                    data-bs-parent="#grupo-clientes-{{ $grupoCultivo->cultivoId }}"
                                                >
                                                    <div class="accordion-body">
                                                        @include('comercial::pages.reportes-comerciales._tabla-contratos', [
                                                            'contratos' => $grupoCliente->contratos,
                                                            'totales' => $grupoCliente,
                                                        ])
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('comercial.reportes_comerciales.estado.sin_resultados_titulo')"
                        :detail="__('comercial.reportes_comerciales.estado.sin_resultados_detalle')"
                    />
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
