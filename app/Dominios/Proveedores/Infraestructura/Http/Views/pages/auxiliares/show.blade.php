{{--
    Page: auxiliares/show (GET /panel/auxiliares/{auxiliar}, panel.auxiliares.show)
    Ficha de revisión de un auxiliar — arquetipo Detalle (§6.4 de
    docs/diseno/guia_pantalla_panel.md): cabecera con el estado y las acciones de
    aprobación que el rol activo puede hacer, KPIs, los datos, los documentos
    con su revisión y el historial. Quien lo registró (un piloto o el proveedor)
    se muestra en los datos.

    Datos esperados (ver AuxiliaresController::show()): la cáscara de
    CascaraPanel, más $auxiliar (con `proveedor`), $cuenta (?DatosCuenta),
    $registradoPor (?string), $documentos (list<DatosDocumento>),
    $documentosFaltantes, $acciones, los permisos `$puede*` y $lineaDeTiempo.

    Gateada por `proveedores.auxiliar.ver`, verificado server-side en el
    controlador; cada acción exige además su propio permiso.
--}}
@php
    $nombreAuxiliar = $cuenta->nombre ?? "#{$auxiliar->usuario_id}";
@endphp
<x-templates.panel-shell :title="$nombreAuxiliar" :tema="$tema">
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
        :vista-actual="$nombreAuxiliar"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="$nombreAuxiliar"
                :subtitle="__('proveedores.auxiliares.detalle_subtitulo', ['proveedor' => $auxiliar->proveedor->razon_social])"
            >
                <x-slot:chip>
                    <x-atoms.badge :variant="$auxiliar->estado->tono()">{{ __('aprobacion.estado.'.$auxiliar->estado->value) }}</x-atoms.badge>
                </x-slot:chip>
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.auxiliares.index')" :label="__('proveedores.auxiliares.volver')" />
                </x-slot:actions>
            </x-organisms.page-header>

            @include('proveedores::pages._maquina-estado-aprobacion', ['acciones' => $acciones, 'estado' => $auxiliar->estado, 'modalBase' => 'auxiliar'])

            @include('proveedores::pages._modales-aprobacion', [
                'acciones' => $acciones,
                'estado' => $auxiliar->estado,
                'nombre' => $nombreAuxiliar,
                'urlResolver' => route('panel.auxiliares.resolver', $auxiliar),
                'modalBase' => 'auxiliar',
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

            @if ($auxiliar->motivo_estado && in_array($auxiliar->estado->value, ['rechazado', 'suspendido', 'baja'], true))
                <x-molecules.alert-strip variant="danger" icon="info">
                    {{ __('proveedores.proveedores.motivo_estado', ['estado' => mb_strtolower(__('aprobacion.estado.'.$auxiliar->estado->value)), 'motivo' => $auxiliar->motivo_estado]) }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-detalle__kpis">
                <x-molecules.stat-card
                    :label="__('proveedores.proveedores.kpi_estado')"
                    icon="verified_user"
                    :value="__('aprobacion.estado.'.$auxiliar->estado->value)"
                    :state="$auxiliar->estado->value === 'aprobado' ? 'success' : ($auxiliar->estado->value === 'pendiente' ? 'warning' : 'danger')"
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
                <x-molecules.form-section accent="primary-2" :title="__('proveedores.auxiliares.seccion_datos')" :count="__('proveedores.campos_contador', ['cantidad' => 4])">
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.auxiliares.campo_nombre') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $nombreAuxiliar }}</p>
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
                                <a href="{{ route('panel.proveedores.show', $auxiliar->proveedor) }}">{{ $auxiliar->proveedor->razon_social }}</a>
                            @else
                                {{ $auxiliar->proveedor->razon_social }}
                            @endif
                        </p>
                    </div>
                    <div class="ag-detalle__campo">
                        <p class="ag-detalle__campo-label">{{ __('proveedores.auxiliares.campo_registrado_por') }}</p>
                        <p class="ag-detalle__campo-valor">{{ $registradoPor ?? __('proveedores.auxiliares.registrado_por_proveedor') }}</p>
                    </div>
                </x-molecules.form-section>

                @include('proveedores::pages._documentos-sujeto', [
                    'documentos' => $documentos,
                    'documentosFaltantes' => $documentosFaltantes,
                    'puedeVerDocumentos' => $puedeVerDocumentos,
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
