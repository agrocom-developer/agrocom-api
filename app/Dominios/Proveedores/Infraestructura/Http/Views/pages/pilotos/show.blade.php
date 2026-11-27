{{--
    Page: pilotos/show (GET /panel/pilotos/{piloto}, panel.pilotos.show)
    Ficha de revisión de un piloto — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y las acciones de
    aprobación que el rol activo puede hacer, KPIs, los datos de la licencia, los
    documentos con su revisión y el historial.

    Datos esperados (ver PilotosController::show()): la cáscara de CascaraPanel,
    más $piloto (con `proveedor`), $cuenta (?DatosCuenta), $documentos
    (list<DatosDocumento>), $documentosFaltantes, $acciones, los permisos
    `$puede*` y $lineaDeTiempo.

    Gateada por `proveedores.piloto.ver`, verificado server-side en el
    controlador; cada acción exige además su propio permiso.
--}}
@php
    $nombrePiloto = $cuenta->nombre ?? "#{$piloto->usuario_id}";
@endphp
<x-templates.panel-shell :title="$nombrePiloto" :tema="$tema">
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
        :vista-actual="$nombrePiloto"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="$nombrePiloto"
                :subtitle="__('proveedores.pilotos.detalle_subtitulo', ['proveedor' => $piloto->proveedor->razon_social])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$piloto->estado->tono()">{{ __('aprobacion.estado.'.$piloto->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.pilotos.index')" :label="__('proveedores.pilotos.volver')" />
                @include('proveedores::pages._boton-eliminar-aprobacion', ['acciones' => $acciones, 'modalBase' => 'piloto'])
                </x-slot:actions>
            </x-organisms.page-header>

            @include('proveedores::pages._maquina-estado-aprobacion', ['acciones' => $acciones, 'estado' => $piloto->estado, 'modalBase' => 'piloto'])

            @include('proveedores::pages._modales-aprobacion', [
                'acciones' => $acciones,
                'estado' => $piloto->estado,
                'nombre' => $nombrePiloto,
                'urlResolver' => route('panel.pilotos.resolver', $piloto),
                'modalBase' => 'piloto',
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

            @if ($piloto->motivo_estado && in_array($piloto->estado->value, ['rechazado', 'suspendido', 'baja'], true))
                <x-molecules.alert-strip variant="danger" icon="info">
                    {{ __('proveedores.proveedores.motivo_estado', ['estado' => mb_strtolower(__('aprobacion.estado.'.$piloto->estado->value)), 'motivo' => $piloto->motivo_estado]) }}
                </x-molecules.alert-strip>
            @endif

            @php
                $licenciaVencida = $piloto->licencia_vence->isPast();
            @endphp
            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('proveedores.proveedores.kpi_estado')"
                    icon="verified_user"
                    :value="__('aprobacion.estado.'.$piloto->estado->value)"
                    :state="$piloto->estado->value === 'aprobado' ? 'success' : ($piloto->estado->value === 'pendiente' ? 'warning' : 'danger')"
                />
                <x-molecules.stat-card
                    :label="__('proveedores.pilotos.kpi_licencia')"
                    icon="badge"
                    :value="$piloto->licencia_numero"
                    :foot="$licenciaVencida ? __('proveedores.pilotos.licencia_vencida') : __('proveedores.pilotos.licencia_vence', ['fecha' => $piloto->licencia_vence->format('d/m/Y')])"
                    :foot-tone="$licenciaVencida ? 'warning' : 'muted'"
                    state="info"
                />
                <x-molecules.stat-card
                    :label="__('proveedores.pilotos.kpi_documentos')"
                    icon="folder_open"
                    :value="(string) count($documentos)"
                    :foot="$documentosFaltantes === [] ? __('proveedores.documentos.completos') : __('proveedores.documentos.faltan', ['cantidad' => count($documentosFaltantes)])"
                    :foot-tone="$documentosFaltantes === [] ? 'success' : 'warning'"
                    state="distintivo-1"
                />
            </div>

            <x-molecules.form-layout>
                <x-molecules.form-section accent="primary-2" :title="__('proveedores.pilotos.seccion_datos')" :count="__('proveedores.campos_contador', ['cantidad' => 5])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.pilotos.campo_nombre') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $nombrePiloto }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.pilotos.campo_cuenta') }}</p>
                        <p class="ag-detalle__campo-valor">
                            {{ $cuenta->username ?? __('proveedores.sin_dato') }}
                            @if ($cuenta?->email)
                                <span class="ag-proveedores__sub">{{ $cuenta->email }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.pilotos.campo_proveedor') }}</p>
                        <p class="ag-detalle__campo-valor">
                            @if ($puedeVerProveedor)
                                <a href="{{ route('panel.proveedores.show', $piloto->proveedor) }}">{{ $piloto->proveedor->razon_social }}</a>
                            @else
                                {{ $piloto->proveedor->razon_social }}
                            @endif
                        </p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.pilotos.campo_licencia') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $piloto->licencia_numero }} · {{ $piloto->licencia_vence->format('d/m/Y') }}</p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.pilotos.campo_categoria') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $piloto->categoria_habilitacion }}</p>
                    </div>
                </x-molecules.form-section>

                @include('proveedores::pages._documentos-sujeto', [
                    'documentos' => $documentos,
                    'documentosFaltantes' => $documentosFaltantes,
                    'puedeVerDocumentos' => $puedeVerDocumentos,
                    'itemsDocumentos' => $itemsDocumentos,
                    'puedeRevisarDocumentos' => $puedeRevisarDocumentos,
                ])

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
