{{--
    Page: reportes-comerciales/index (GET /panel/reportes/comercial, panel.reportes.comercial.index)
    Informe de avance de contratos (HU-52, tarea 75, espec §9.1): agrupado por
    cultivo y por cliente, con filtros opcionales en un offcanvas y chips de
    filtros aplicados en la pantalla principal. Reemplaza a HU-32 (tarea 46)
    en la misma ruta y permiso (`comercial.reporte.ver`, exclusivo del dueño).

    Entrada obligatoria: al menos un cliente y un cultivo — sin ellos, ni se
    habilita la pantalla de filtros ni la generación; el selector que falta
    muestra error bajo su propio campo. El marcador `consultado` (hidden en el
    formulario de entrada) distingue "primera visita" (sin error, solo estado
    vacío) de "intento sin completar entrada" (con error).

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
    - campaniasDisponibles (Collection<stdClass{id,codigo,cliente_id,razon_social}>)
    - estadosDisponibles (list<EstadoContrato>)
    - saldosDisponibles (list<SaldoContrato>)

    Gateada por `comercial.reporte.ver`, verificado server-side en el
    controlador.

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

            {{-- Pantalla de entrada: checkbox-group de cliente y cultivo --}}
            @if (!$consultado || !empty($erroresEntrada))
                <form method="GET" action="{{ route('panel.reportes.comercial.index') }}" class="ag-reportes-comerciales__entrada">
                    <input type="hidden" name="consultado" value="1">

                    <div class="ag-reportes-comerciales__selectores">
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
                            :options="$cultivosDisponibles->mapWithKeys(fn($c) => [$c->id => $c->nombre])"
                            :value="$cultivoIds"
                            :error="$erroresEntrada['cultivo_ids'] ?? null"
                        />
                    </div>

                    <div class="ag-reportes-comerciales__acciones-entrada">
                        <x-atoms.button
                            type="button"
                            variant="outline"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#filtros-offcanvas"
                            icon="filter_alt"
                        >
                            {{ __('comercial.reportes_comerciales.entrada.filtros') }}
                        </x-atoms.button>

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

                {{-- Estado vacío en pantalla de entrada (sin consulta o con error) --}}
                @if (!$consultado)
                    <x-molecules.alert-strip
                        variant="info"
                        icon="insert_chart"
                        class="ag-reportes-comerciales__estado-vacio"
                    >
                        {{ __('comercial.reportes_comerciales.estado.primera_visita') }}
                    </x-molecules.alert-strip>
                @endif
            @else
                {{-- Pantalla de resultados --}}

                {{-- Chips de filtros aplicados (carrusel horizontal) --}}
                <div class="ag-reportes-comerciales__chips">
                    {{-- Chip: Cliente (N) --}}
                    <a href="{{ route('panel.reportes.comercial.index', array_merge(request()->except('cliente_ids'), ['consultado' => 1])) }}" class="ag-reportes-comerciales__chip">
                        <x-atoms.badge>
                            {{ __('comercial.reportes_comerciales.chips.cliente', ['cantidad' => count($clienteIds)]) }}
                        </x-atoms.badge>
                        <x-atoms.icon name="close" size="sm" />
                    </a>

                    {{-- Chip: Cultivo (N) --}}
                    <a href="{{ route('panel.reportes.comercial.index', array_merge(request()->except('cultivo_ids'), ['consultado' => 1])) }}" class="ag-reportes-comerciales__chip">
                        <x-atoms.badge>
                            {{ __('comercial.reportes_comerciales.chips.cultivo', ['cantidad' => count($cultivoIds)]) }}
                        </x-atoms.badge>
                        <x-atoms.icon name="close" size="sm" />
                    </a>

                    {{-- Chip: Campaña (N) — solo si hay seleccionadas --}}
                    @if (!empty($campaniaIds))
                        <a href="{{ route('panel.reportes.comercial.index', array_merge(request()->except('campania_ids'), ['consultado' => 1])) }}" class="ag-reportes-comerciales__chip">
                            <x-atoms.badge>
                                {{ __('comercial.reportes_comerciales.chips.campania', ['cantidad' => count($campaniaIds)]) }}
                            </x-atoms.badge>
                            <x-atoms.icon name="close" size="sm" />
                        </a>
                    @endif

                    {{-- Chip: Rango de fechas — solo si alguna está presente --}}
                    @if ($fechaDesde !== null || $fechaHasta !== null)
                        @php
                            $textoFechas = match (true) {
                                $fechaDesde !== null && $fechaHasta !== null => __('comercial.reportes_comerciales.chips.rango_fechas', ['desde' => $fechaDesde, 'hasta' => $fechaHasta]),
                                $fechaDesde !== null => __('comercial.reportes_comerciales.chips.fecha_desde', ['fecha' => $fechaDesde]),
                                default => __('comercial.reportes_comerciales.chips.fecha_hasta', ['fecha' => $fechaHasta]),
                            };
                        @endphp
                        <a href="{{ route('panel.reportes.comercial.index', array_merge(request()->except(['fecha_desde', 'fecha_hasta']), ['consultado' => 1])) }}" class="ag-reportes-comerciales__chip">
                            <x-atoms.badge>
                                {{ $textoFechas }}
                            </x-atoms.badge>
                            <x-atoms.icon name="close" size="sm" />
                        </a>
                    @endif

                    {{-- Chip: Estado — solo si está elegido --}}
                    @if ($estado !== null)
                        <a href="{{ route('panel.reportes.comercial.index', array_merge(request()->except('estado'), ['consultado' => 1])) }}" class="ag-reportes-comerciales__chip">
                            <x-atoms.badge>
                                {{ __('comercial.reportes_comerciales.chips.estado', ['estado' => __("comercial.contrato.estado.{$estado->value}")]) }}
                            </x-atoms.badge>
                            <x-atoms.icon name="close" size="sm" />
                        </a>
                    @endif

                    {{-- Chip: Saldo — solo si está elegido --}}
                    @if ($saldo !== null)
                        <a href="{{ route('panel.reportes.comercial.index', array_merge(request()->except('saldo'), ['consultado' => 1])) }}" class="ag-reportes-comerciales__chip">
                            <x-atoms.badge>
                                {{ __('comercial.reportes_comerciales.chips.saldo', ['saldo' => __("comercial.saldo.{$saldo->value}")]) }}
                            </x-atoms.badge>
                            <x-atoms.icon name="close" size="sm" />
                        </a>
                    @endif

                    {{-- Chip: Incluir deshabilitados — solo si está activo --}}
                    @if ($incluirDeshabilitados)
                        <a href="{{ route('panel.reportes.comercial.index', array_merge(request()->except('incluir_deshabilitados'), ['consultado' => 1])) }}" class="ag-reportes-comerciales__chip">
                            <x-atoms.badge>
                                {{ __('comercial.reportes_comerciales.chips.incluir_deshabilitados') }}
                            </x-atoms.badge>
                            <x-atoms.icon name="close" size="sm" />
                        </a>
                    @endif

                    {{-- Botón "Filtros" para reabrir el offcanvas --}}
                    <button type="button" class="ag-reportes-comerciales__filtros-boton" data-bs-toggle="offcanvas" data-bs-target="#filtros-offcanvas">
                        <x-atoms.icon name="filter_alt" size="sm" />
                        <span>{{ __('comercial.reportes_comerciales.chips.filtros') }}</span>
                    </button>
                </div>

                {{-- Informe o estado vacío (sin resultados) --}}
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

                                    {{-- Tabla de contratos por cultivo --}}
                                    <table class="ag-reportes-comerciales__tabla">
                                        <thead>
                                            <tr>
                                                <th>{{ __('comercial.reportes_comerciales.tabla.contrato') }}</th>
                                                <th class="ag-reportes-comerciales__celda-numero">{{ __('comercial.reportes_comerciales.tabla.hectareas_contratadas') }}</th>
                                                <th class="ag-reportes-comerciales__celda-numero">{{ __('comercial.reportes_comerciales.tabla.hectareas_aplicadas') }}</th>
                                                <th class="ag-reportes-comerciales__celda-numero">{{ __('comercial.reportes_comerciales.tabla.hectareas_a_aplicar') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($grupoCultivo->contratos as $fila)
                                                <tr>
                                                    <td>{{ \Illuminate\Support\Str::limit(__('comercial.reportes_comerciales.tabla.contrato_valor', ['id' => $fila->contratoId]), 12) }}</td>
                                                    <td class="ag-reportes-comerciales__celda-numero">
                                                        {{ number_format((float) $fila->hectareasContratadas, 2, ',', '.') }}
                                                    </td>
                                                    <td class="ag-reportes-comerciales__celda-numero">
                                                        {{ number_format((float) $fila->hectareasAplicadas, 2, ',', '.') }}
                                                    </td>
                                                    <td class="ag-reportes-comerciales__celda-numero">
                                                        {{ number_format((float) $fila->hectareasAAplicar, 2, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                            {{-- Totalizador al pie --}}
                                            <tr class="ag-reportes-comerciales__fila-totales">
                                                <td><strong>{{ __('comercial.reportes_comerciales.tabla.total') }}</strong></td>
                                                <td class="ag-reportes-comerciales__celda-numero">
                                                    <strong>{{ number_format((float) $grupoCultivo->hectareasContratadas, 2, ',', '.') }}</strong>
                                                </td>
                                                <td class="ag-reportes-comerciales__celda-numero">
                                                    <strong>{{ number_format((float) $grupoCultivo->hectareasAplicadas, 2, ',', '.') }}</strong>
                                                </td>
                                                <td class="ag-reportes-comerciales__celda-numero">
                                                    <strong>{{ number_format((float) $grupoCultivo->hectareasAAplicar, 2, ',', '.') }}</strong>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
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

                                    {{-- Accordion de clientes dentro de cada cultivo --}}
                                    <div class="accordion ag-reportes-comerciales__accordion" id="grupo-clientes-{{ $grupoCultivo->cultivoId }}">
                                        @foreach ($grupoCultivo->clientes as $grupoCliente)
                                            <div class="accordion-item">
                                                <h3 class="accordion-header">
                                                    <button
                                                        type="button"
                                                        class="accordion-button collapsed"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#collapse-cliente-{{ $grupoCliente->clienteId }}"
                                                        aria-expanded="false"
                                                        aria-controls="collapse-cliente-{{ $grupoCliente->clienteId }}"
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
                                                    id="collapse-cliente-{{ $grupoCliente->clienteId }}"
                                                    class="accordion-collapse collapse"
                                                    data-bs-parent="#grupo-clientes-{{ $grupoCultivo->cultivoId }}"
                                                >
                                                    <div class="accordion-body">
                                                        <table class="ag-reportes-comerciales__tabla">
                                                            <thead>
                                                                <tr>
                                                                    <th>{{ __('comercial.reportes_comerciales.tabla.contrato') }}</th>
                                                                    <th class="ag-reportes-comerciales__celda-numero">{{ __('comercial.reportes_comerciales.tabla.hectareas_contratadas') }}</th>
                                                                    <th class="ag-reportes-comerciales__celda-numero">{{ __('comercial.reportes_comerciales.tabla.hectareas_aplicadas') }}</th>
                                                                    <th class="ag-reportes-comerciales__celda-numero">{{ __('comercial.reportes_comerciales.tabla.hectareas_a_aplicar') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($grupoCliente->contratos as $fila)
                                                                    <tr>
                                                                        <td>{{ \Illuminate\Support\Str::limit(__('comercial.reportes_comerciales.tabla.contrato_valor', ['id' => $fila->contratoId]), 12) }}</td>
                                                                        <td class="ag-reportes-comerciales__celda-numero">
                                                                            {{ number_format((float) $fila->hectareasContratadas, 2, ',', '.') }}
                                                                        </td>
                                                                        <td class="ag-reportes-comerciales__celda-numero">
                                                                            {{ number_format((float) $fila->hectareasAplicadas, 2, ',', '.') }}
                                                                        </td>
                                                                        <td class="ag-reportes-comerciales__celda-numero">
                                                                            {{ number_format((float) $fila->hectareasAAplicar, 2, ',', '.') }}
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                                {{-- Totalizador al pie --}}
                                                                <tr class="ag-reportes-comerciales__fila-totales">
                                                                    <td><strong>{{ __('comercial.reportes_comerciales.tabla.total') }}</strong></td>
                                                                    <td class="ag-reportes-comerciales__celda-numero">
                                                                        <strong>{{ number_format((float) $grupoCliente->hectareasContratadas, 2, ',', '.') }}</strong>
                                                                    </td>
                                                                    <td class="ag-reportes-comerciales__celda-numero">
                                                                        <strong>{{ number_format((float) $grupoCliente->hectareasAplicadas, 2, ',', '.') }}</strong>
                                                                    </td>
                                                                    <td class="ag-reportes-comerciales__celda-numero">
                                                                        <strong>{{ number_format((float) $grupoCliente->hectareasAAplicar, 2, ',', '.') }}</strong>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
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
                    {{-- Estado vacío: sin resultados coincidentes --}}
                    <x-molecules.alert-strip
                        variant="info"
                        icon="insert_chart"
                        class="ag-reportes-comerciales__estado-vacio"
                    >
                        {{ __('comercial.reportes_comerciales.estado.sin_resultados') }}
                    </x-molecules.alert-strip>
                @endif
            @endif

            {{-- Offcanvas de filtros avanzados --}}
            <div
                class="offcanvas offcanvas-end"
                id="filtros-offcanvas"
                tabindex="-1"
                aria-labelledby="filtros-offcanvas-label"
            >
                <div class="offcanvas-header">
                    <h2 class="offcanvas-title" id="filtros-offcanvas-label">
                        {{ __('comercial.reportes_comerciales.filtros.titulo') }}
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('ui.cerrar') }}"></button>
                </div>

                <div class="offcanvas-body">
                    <form method="GET" action="{{ route('panel.reportes.comercial.index') }}" class="ag-reportes-comerciales__form-filtros">
                        <input type="hidden" name="consultado" value="1">
                        <input type="hidden" name="tab" value="{{ $tabActiva }}">

                        {{-- Cliente (duplicado acá para que sea editable en la pantalla de filtros) --}}
                        <x-atoms.checkbox-group
                            name="cliente_ids"
                            id="filtros-clientes"
                            :label="__('comercial.reportes_comerciales.filtros.cliente')"
                            :options="$clientesDisponibles->mapWithKeys(fn($c) => [$c->id => $c->razon_social])"
                            :value="$clienteIds"
                        />

                        {{-- Cultivo (duplicado acá) --}}
                        <x-atoms.checkbox-group
                            name="cultivo_ids"
                            id="filtros-cultivos"
                            :label="__('comercial.reportes_comerciales.filtros.cultivo')"
                            :options="$cultivosDisponibles->mapWithKeys(fn($c) => [$c->id => $c->nombre])"
                            :value="$cultivoIds"
                        />

                        {{-- Campaña (solo si hay clientes elegidos) --}}
                        @if (!empty($clienteIds) && !$campaniasDisponibles->isEmpty())
                            <x-atoms.checkbox-group
                                name="campania_ids"
                                id="filtros-campanias"
                                :label="__('comercial.reportes_comerciales.filtros.campania')"
                                :options="$campaniasDisponibles->mapWithKeys(fn($c) => [$c->id => __('comercial.reportes_comerciales.filtros.campania_opcion', ['codigo' => $c->codigo, 'cliente' => $c->razon_social])])"
                                :value="$campaniaIds"
                            />
                        @elseif (empty($clienteIds))
                            <div class="ag-reportes-comerciales__filtro-deshabilitado">
                                <p>{{ __('comercial.reportes_comerciales.filtros.campania_sin_cliente') }}</p>
                            </div>
                        @endif

                        {{-- Rango de fechas --}}
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

                        {{-- Estado del contrato --}}
                        <x-atoms.select
                            name="estado"
                            id="filtros-estado"
                            :label="__('comercial.reportes_comerciales.filtros.estado')"
                            :options="collect($estadosDisponibles)->mapWithKeys(fn($e) => [$e->value => __('comercial.contrato.estado.' . $e->value)])"
                            :value="$estado?->value"
                            :placeholder="__('comercial.reportes_comerciales.filtros.seleccionar')"
                        />

                        {{-- Saldo del contrato --}}
                        <x-atoms.select
                            name="saldo"
                            id="filtros-saldo"
                            :label="__('comercial.reportes_comerciales.filtros.saldo')"
                            :options="collect($saldosDisponibles)->mapWithKeys(fn($s) => [$s->value => __('comercial.saldo.' . $s->value)])"
                            :value="$saldo?->value"
                            :placeholder="__('comercial.reportes_comerciales.filtros.seleccionar')"
                        />

                        {{-- Incluir deshabilitados --}}
                        <x-atoms.switch
                            name="incluir_deshabilitados"
                            id="filtros-incluir-deshabilitados"
                            :label="__('comercial.reportes_comerciales.filtros.incluir_deshabilitados')"
                            value="1"
                            :checked="$incluirDeshabilitados"
                        />

                        {{-- Botones de acción --}}
                        <div class="ag-reportes-comerciales__filtros-acciones-offcanvas">
                            <x-atoms.button type="submit" variant="primary" class="w-100">
                                {{ __('comercial.reportes_comerciales.filtros.aplicar') }}
                            </x-atoms.button>

                            <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="offcanvas">
                                {{ __('comercial.reportes_comerciales.filtros.cancelar') }}
                            </button>

                            <a href="{{ route('panel.reportes.comercial.index', ['cliente_ids' => $clienteIds, 'cultivo_ids' => $cultivoIds, 'consultado' => 1]) }}" class="btn btn-link w-100">
                                {{ __('comercial.reportes_comerciales.filtros.limpiar') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>

{{-- JS para validación cliente de entrada (deshabilitar botones mientras falten selectores) --}}
<script src="{{ asset('js/pages/reportes-comerciales.js') }}" defer></script>
