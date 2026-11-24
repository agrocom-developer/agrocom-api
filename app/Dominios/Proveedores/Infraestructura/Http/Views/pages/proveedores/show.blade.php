{{--
    Page: proveedores/show (GET /panel/proveedores/{proveedor}, panel.proveedores.show)
    Ficha de revisión de un proveedor — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y las acciones
    de aprobación que el rol activo puede hacer (aprobar, rechazar, suspender,
    rehabilitar, dar de baja — las dos que piden motivo lo piden en el modal),
    KPIs, los datos, los documentos con su revisión, el personal y el historial.

    Datos esperados (ver ProveedoresController::show()): la cáscara de
    CascaraPanel, más $proveedor, $titular (?DatosCuenta), $zona (?string),
    $pilotos / $auxiliares (Collection), $nombres (array<int, DatosCuenta>),
    $documentos (list<DatosDocumento>), $documentosFaltantes, $aptoParaOfertas,
    $acciones (list<AccionAprobacion>), los permisos `$puede*` y $lineaDeTiempo.

    Gateada por `proveedores.proveedor.ver`, verificado server-side en el
    controlador; cada acción exige además su propio permiso.
--}}
<x-templates.panel-shell :title="$proveedor->razon_social" :tema="$tema">
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
        :vista-actual="$proveedor->razon_social"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="$proveedor->razon_social"
                :subtitle="__('proveedores.proveedores.detalle_subtitulo', ['tipo' => __('proveedores.tipo.'.$proveedor->tipo->value)])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$proveedor->estado->tono()">{{ __('aprobacion.estado.'.$proveedor->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.proveedores.index')" :label="__('proveedores.proveedores.volver')" />
                    @include('proveedores::pages._acciones-aprobacion', ['acciones' => $acciones, 'modalBase' => 'proveedor'])
                </x-slot:actions>
            </x-organisms.page-header>

            @include('proveedores::pages._modales-aprobacion', [
                'acciones' => $acciones,
                'estado' => $proveedor->estado,
                'nombre' => $proveedor->razon_social,
                'urlResolver' => route('panel.proveedores.resolver', $proveedor),
                'modalBase' => 'proveedor',
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

            @if ($proveedor->motivo_estado && in_array($proveedor->estado->value, ['rechazado', 'suspendido', 'baja'], true))
                <x-molecules.alert-strip variant="danger" icon="info">
                    {{ __('proveedores.proveedores.motivo_estado', ['estado' => mb_strtolower(__('aprobacion.estado.'.$proveedor->estado->value)), 'motivo' => $proveedor->motivo_estado]) }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('proveedores.proveedores.kpi_estado')"
                    icon="verified_user"
                    :value="__('aprobacion.estado.'.$proveedor->estado->value)"
                    :state="$proveedor->estado->value === 'aprobado' ? 'success' : ($proveedor->estado->value === 'pendiente' ? 'warning' : 'danger')"
                />
                <x-molecules.stat-card
                    :label="__('proveedores.proveedores.kpi_ofertas')"
                    icon="notifications_active"
                    :value="$aptoParaOfertas ? __('proveedores.proveedores.apto_si') : __('proveedores.proveedores.apto_no')"
                    :foot="$aptoParaOfertas ? __('proveedores.proveedores.apto_si_pie') : __('proveedores.proveedores.apto_no_pie')"
                    :state="$aptoParaOfertas ? 'success' : null"
                />
                <x-molecules.stat-card
                    :label="__('proveedores.proveedores.kpi_personal')"
                    icon="groups"
                    :value="(string) ($pilotos->count() + $auxiliares->count())"
                    :foot="__('proveedores.proveedores.kpi_personal_pie', ['pilotos' => $pilotos->count(), 'auxiliares' => $auxiliares->count()])"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('proveedores.proveedores.kpi_calificacion')"
                    icon="star"
                    :value="$proveedor->calificaciones_conteo > 0 ? $proveedor->calificacion_promedio : '—'"
                    :foot="__('proveedores.proveedores.kpi_calificacion_pie', ['conteo' => $proveedor->calificaciones_conteo])"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('proveedores.proveedores.seccion_datos')" :count="__('proveedores.campos_contador', ['cantidad' => 8])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.proveedores.campo_razon_social') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $proveedor->razon_social }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.proveedores.campo_tipo') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('proveedores.tipo.'.$proveedor->tipo->value) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.proveedores.campo_documento_fiscal') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $proveedor->documento_fiscal ?? __('proveedores.sin_dato') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.proveedores.campo_titular') }}</p>
                        <p class="ag-detalle__campo-valor">
                            {{ $titular->nombre ?? __('proveedores.sin_dato') }}
                            @if ($titular)
                                <span class="ag-proveedores__sub">{{ $titular->username }}@if ($titular->email) · {{ $titular->email }}@endif</span>
                            @endif
                        </p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.proveedores.campo_telefono') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $proveedor->telefono ?? __('proveedores.sin_dato') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.proveedores.campo_zona') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $zona ?? __('proveedores.sin_dato') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.proveedores.campo_radio') }}</p>
                        <p class="ag-detalle__campo-valor">{{ __('proveedores.proveedores.radio_km', ['km' => rtrim(rtrim($proveedor->radio_operacion_km, '0'), '.')]) }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.proveedores.campo_aprobado') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $proveedor->aprobado_en?->format('d/m/Y H:i') ?? __('proveedores.sin_dato') }}</p>
                    </div>
                </x-molecules.form-section>

                @include('proveedores::pages._documentos-sujeto', [
                    'documentos' => $documentos,
                    'documentosFaltantes' => $documentosFaltantes,
                    'puedeVerDocumentos' => $puedeVerDocumentos,
                    'puedeRevisarDocumentos' => $puedeRevisarDocumentos,
                ])

                <x-molecules.form-section accent="distintivo-1" :title="__('proveedores.proveedores.seccion_pilotos')" :count="(string) $pilotos->count()">
                    @if ($pilotos->isEmpty())
                        <div class="ag-form-section__field--full">
                            <x-molecules.empty-state icon="person_off" :title="__('proveedores.proveedores.pilotos_vacio_titulo')" :detail="__('proveedores.proveedores.pilotos_vacio_detalle')" />
                        </div>
                    @else
                        <div class="ag-form-section__field--full">
                            <x-molecules.index-table columns="minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('proveedores.pilotos.col_piloto') }}</span>
                                    <span role="columnheader">{{ __('proveedores.pilotos.col_licencia') }}</span>
                                    <span role="columnheader">{{ __('proveedores.pilotos.col_estado') }}</span>
                                    <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                                </x-slot:head>
                                @foreach ($pilotos as $piloto)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $nombres[$piloto->usuario_id]->nombre ?? "#{$piloto->usuario_id}" }}</span>
                                        <span role="cell">{{ $piloto->licencia_numero }} · {{ $piloto->licencia_vence->format('d/m/Y') }}</span>
                                        <span role="cell"><x-atoms.badge :variant="$piloto->estado->tono()">{{ __('aprobacion.estado.'.$piloto->estado->value) }}</x-atoms.badge></span>
                                        <span role="cell" class="ag-index-table__acciones">
                                            @if ($puedeVerPilotos)
                                                <x-organisms.row-actions>
                                                    <x-atoms.button :href="route('panel.pilotos.show', $piloto)" variant="info-outline" size="sm" icon="visibility">{{ __('proveedores.ver') }}</x-atoms.button>
                                                </x-organisms.row-actions>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-molecules.form-section accent="distintivo-2" :title="__('proveedores.proveedores.seccion_auxiliares')" :count="(string) $auxiliares->count()">
                    @if ($auxiliares->isEmpty())
                        <div class="ag-form-section__field--full">
                            <x-molecules.empty-state icon="person_off" :title="__('proveedores.proveedores.auxiliares_vacio_titulo')" :detail="__('proveedores.proveedores.auxiliares_vacio_detalle')" />
                        </div>
                    @else
                        <div class="ag-form-section__field--full">
                            <x-molecules.index-table columns="minmax(0, 1.4fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                                <x-slot:head>
                                    <span role="columnheader">{{ __('proveedores.auxiliares.col_auxiliar') }}</span>
                                    <span role="columnheader">{{ __('proveedores.auxiliares.col_estado') }}</span>
                                    <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                                </x-slot:head>
                                @foreach ($auxiliares as $auxiliar)
                                    <div class="ag-index-table__row" role="row">
                                        <span role="cell">{{ $nombres[$auxiliar->usuario_id]->nombre ?? "#{$auxiliar->usuario_id}" }}</span>
                                        <span role="cell"><x-atoms.badge :variant="$auxiliar->estado->tono()">{{ __('aprobacion.estado.'.$auxiliar->estado->value) }}</x-atoms.badge></span>
                                        <span role="cell" class="ag-index-table__acciones">
                                            @if ($puedeVerAuxiliares)
                                                <x-organisms.row-actions>
                                                    <x-atoms.button :href="route('panel.auxiliares.show', $auxiliar)" variant="info-outline" size="sm" icon="visibility">{{ __('proveedores.ver') }}</x-atoms.button>
                                                </x-organisms.row-actions>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </x-molecules.index-table>
                        </div>
                    @endif
                </x-molecules.form-section>

                <x-slot:aside>
                    <x-molecules.form-section accent="info" :title="__('proveedores.historial.titulo')" :count="(string) count($lineaDeTiempo)">
                        @if ($lineaDeTiempo === [])
                            <div class="ag-form-section__field--full">
                                <p class="ag-detalle__campo-valor">{{ __('proveedores.historial.vacio') }}</p>
                            </div>
                        @else
                            <div class="ag-form-section__field--full">
                                <x-molecules.timeline :items="$lineaDeTiempo" />
                            </div>
                        @endif
                    </x-molecules.form-section>
                </x-slot:aside>
            </x-molecules.form-layout>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
