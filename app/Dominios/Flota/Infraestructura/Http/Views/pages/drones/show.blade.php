{{--
    Page: drones/show (GET /panel/drones-flota/{dron}, panel.drones-flota.show)
    Ficha de revisión de un dron del marketplace — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y las acciones de
    aprobación que el rol activo puede hacer, KPIs (capacidad, ancho de pasada,
    ciclos de batería), los datos con su dueño y operador, los documentos con su
    revisión, los equipos donde trabaja y el historial.

    Datos esperados (ver DronesController::show()): la cáscara de CascaraPanel,
    más $dron, $dueno / $operador (?DatosProveedor), $documentos
    (list<DatosDocumento>), $documentosFaltantes, $equipos (Collection<Equipo>),
    $pilotos (array<int, string>), $acciones, los permisos `$puede*` y
    $lineaDeTiempo.

    Gateada por `flota.dron.ver`, verificado server-side en el controlador;
    cada acción exige además su propio permiso.
--}}
@php
    $nombreDron = trim("{$dron->marca} {$dron->modelo}");
@endphp
<x-templates.panel-shell :title="$nombreDron" :tema="$tema">
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
        :vista-actual="$nombreDron"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="$nombreDron"
                :subtitle="__('flota.drones.detalle_subtitulo', ['serie' => $dron->numero_serie])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$dron->estado->tono()">{{ __('aprobacion.estado.'.$dron->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.drones-flota.index')" :label="__('flota.drones.volver')" />
                    @include('proveedores::pages._acciones-aprobacion', ['acciones' => $acciones, 'modalBase' => 'dron'])
                </x-slot:actions>
            </x-organisms.page-header>

            @include('proveedores::pages._modales-aprobacion', [
                'acciones' => $acciones,
                'estado' => $dron->estado,
                'nombre' => $nombreDron,
                'urlResolver' => route('panel.drones-flota.resolver', $dron),
                'modalBase' => 'dron',
            ])

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first() }}
                </x-molecules.alert-strip>
            @endif

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($dron->motivo_estado && in_array($dron->estado->value, ['rechazado', 'suspendido', 'baja'], true))
                <x-molecules.alert-strip variant="danger" icon="info">
                    {{ __('proveedores.proveedores.motivo_estado', ['estado' => mb_strtolower(__('aprobacion.estado.'.$dron->estado->value)), 'motivo' => $dron->motivo_estado]) }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('proveedores.proveedores.kpi_estado')"
                    icon="verified_user"
                    :value="__('aprobacion.estado.'.$dron->estado->value)"
                    :state="$dron->estado->value === 'aprobado' ? 'success' : ($dron->estado->value === 'pendiente' ? 'warning' : 'danger')"
                />
                <x-molecules.stat-card
                    :label="__('flota.drones.kpi_capacidad')"
                    icon="water_drop"
                    :value="$dron->capacidad_tanque_l"
                    value-suffix="L"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('flota.drones.kpi_ancho_pasada')"
                    icon="width"
                    :value="$dron->ancho_pasada_m"
                    value-suffix="m"
                    state="distintivo-1"
                />
                <x-molecules.stat-card
                    :label="__('flota.drones.kpi_ciclos')"
                    icon="battery_charging_full"
                    :value="(string) $dron->ciclos_bateria"
                    :foot="__('flota.drones.kpi_ciclos_pie')"
                    state="distintivo-2"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('flota.drones.seccion_datos')" :count="__('proveedores.campos_contador', ['cantidad' => 7])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('flota.drones.campo_marca') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $dron->marca }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('flota.drones.campo_modelo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $dron->modelo }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('flota.drones.campo_serie') }}</p>
                        <p class="ag-detalle__campo-valor ag-proveedores__cifra">{{ $dron->numero_serie }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('flota.drones.campo_origen') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('flota.origen.'.$dron->origen->value) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('flota.drones.campo_dueno') }}</p>
                        <p class="ag-detalle__campo-valor">
                            @if ($dueno && $puedeVerProveedor)
                                <a href="{{ route('panel.proveedores.show', $dueno->id) }}">{{ $dueno->razonSocial }}</a>
                            @else
                                {{ $dueno->razonSocial ?? __('proveedores.sin_dato') }}
                            @endif
                        </p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('flota.drones.campo_operador') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $operador->razonSocial ?? __('proveedores.sin_dato') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('flota.drones.campo_aprobado') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $dron->aprobado_en?->format('d/m/Y H:i') ?? __('proveedores.sin_dato') }}</p>
                    </div>
                </x-molecules.form-section>

                @include('proveedores::pages._documentos-sujeto', [
                    'documentos' => $documentos,
                    'documentosFaltantes' => $documentosFaltantes,
                    'puedeVerDocumentos' => $puedeVerDocumentos,
                    'puedeRevisarDocumentos' => $puedeRevisarDocumentos,
                ])

                <x-molecules.form-section accent="distintivo-1" :title="__('flota.drones.seccion_equipos')" :count="(string) $equipos->count()">
                    @if ($equipos->isEmpty())
                        <div class="ag-form-section__field--full">
                            <x-molecules.empty-state icon="groups" :title="__('flota.drones.equipos_vacio_titulo')" :detail="__('flota.drones.equipos_vacio_detalle')" />
                        </div>
                    @else
                        <div class="ag-form-section__field--full">
                            <x-molecules.index-table columns="minmax(0, 1.4fr) minmax(0, 1.2fr)">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('flota.equipos.col_piloto') }}</span>
                                    <span role="columnheader">{{ __('flota.equipos.col_vigencia') }}</span>
                                </x-slot:head>
                                @foreach ($equipos as $equipo)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $pilotos[$equipo->piloto_id] ?? "#{$equipo->piloto_id}" }}</span>
                                        <span role="cell">
                                            {{ $equipo->vigente_desde->format('d/m/Y') }}
                                            →
                                            {{ $equipo->vigente_hasta?->format('d/m/Y') ?? __('flota.equipos.sin_fin') }}
                                        </span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.form-section accent="info" :title="__('proveedores.historial.titulo')" :count="(string) count($lineaDeTiempo)">
                        <div class="ag-form-section__field--full">
                            @if ($lineaDeTiempo === [])
                                <p class="ag-detalle__campo-valor">{{ __('proveedores.historial.vacio') }}</p>
                            @else
                                <x-molecules.timeline :items="$lineaDeTiempo" />
                            @endif
                        </div>
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
