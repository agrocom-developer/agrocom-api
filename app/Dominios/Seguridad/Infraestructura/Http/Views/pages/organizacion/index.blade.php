{{--
    Page: organizacion/index (GET /panel/organizacion, panel.organizacion.index)
    Pestaña "Organización": mockup visual de "Registro de la compañía" — vista previa
    de una pantalla de gestión de organización multi-tenant futura (SIN implementación
    real de tenancy, sin tabla, sin persistencia). Prellenada con datos realistas para
    demostración. Botones "Guardar"/"Descartar" deshabilitados, estado fijo "Sin cambios
    pendientes" (nada de esta pestaña es editable).

    Pestaña "Facturación" (tarea 78, HU-55): REAL. Formulario propio, con su propio botón
    "Guardar", que persiste en `sec_datos_fiscales` vía
    `OrganizacionController::actualizarFacturacion()`. Gateada por
    `seguridad.organizacion.editar` ($puedeEditarFacturacion) — sin ese permiso, los
    campos se muestran deshabilitados y sin barra de acciones, mismo criterio que el
    resto del panel (ver vs. editar).

    Reconstruida sobre el arquetipo formulario (tarea 31): es el caso de prueba de
    `organisms/page-header`, `molecules/tabs`, `molecules/form-section` evolucionado
    (tarjeta + grid de dos columnas), `molecules/progress-meter`, `molecules/summary-card`,
    `molecules/file-field` y `organisms/form-actions-bar` — ver
    docs/diseno/guia_pantalla_panel.md §6.3.

    Datos esperados (ver OrganizacionController::index()): la cáscara completa de
    CascaraPanel (menu/roles/…/tema/zonaHoraria/version) + tabs/progreso/
    suscripcion/logoArchivo/tabActiva/datosFiscales/puedeEditarFacturacion.

    NO tiene pestaña de "Usuarios y roles": usuarios internos y la asignación de
    sus roles ya son una pantalla REAL y propia — Seguridad › Usuarios
    (`panel.usuarios.index`, HU-45), con la columna de roles en chips y el alta/baja
    de asignaciones. Duplicar esa gestión adentro de un mockup de organización
    dejaba dos puertas a lo mismo, y la de acá no mostraba nada.
--}}
<x-templates.panel-shell :title="__('seguridad.organizacion.titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.organizacion.titulo')"
    >
        <div class="ag-organizacion">
            <x-organisms.page-header
                :title="__('seguridad.organizacion.titulo')"
                :subtitle="__('seguridad.organizacion.subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button type="button" variant="outline" disabled>
                        {{ __('seguridad.organizacion.accion_descartar') }}
                    </x-atoms.button>
                    <x-atoms.button type="button" variant="primary" disabled>
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($tabActiva === 'organizacion')
                <x-molecules.alert-strip variant="info" icon="visibility">
                    {{ __('seguridad.organizacion.alerta_vista_previa') }}
                </x-molecules.alert-strip>
            @endif

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.tabs :items="$tabs" :aria-label="__('seguridad.organizacion.tabs_aria')" />

            <div class="tab-content ag-organizacion__panes">
                <div class="tab-pane fade {{ $tabActiva === 'organizacion' ? 'show active' : '' }}" id="ag-tab-organizacion" role="tabpanel" tabindex="0">
                    <form class="ag-organizacion__form" onsubmit="return false">
                        <div class="ag-organizacion__layout">
                            <div class="ag-organizacion__main">
                                <x-molecules.form-section
                                    :title="__('seguridad.organizacion.seccion_datos_empresa')"
                                    :count="__('seguridad.organizacion.campos_contador', ['cantidad' => 3])"
                                >
                                    <x-atoms.input
                                        type="text"
                                        name="empresa_nombre"
                                        label="{{ __('seguridad.organizacion.campo_nombre') }}"
                                        value="{{ __('seguridad.organizacion.mock_nombre_empresa') }}"
                                        disabled
                                    />

                                    <x-atoms.input
                                        type="text"
                                        name="empresa_rubro"
                                        label="{{ __('seguridad.organizacion.campo_rubro') }}"
                                        value="{{ __('seguridad.organizacion.mock_rubro') }}"
                                        disabled
                                    />

                                    <x-molecules.file-field
                                        class="ag-form-section__field--full"
                                        :label="__('seguridad.organizacion.campo_logo')"
                                        :file-name="$logoArchivo['nombre']"
                                        :file-size="$logoArchivo['peso']"
                                        :help="__('seguridad.organizacion.campo_logo_ayuda')"
                                        :replace-label="__('seguridad.organizacion.campo_logo_reemplazar')"
                                        :remove-label="__('seguridad.organizacion.campo_logo_quitar')"
                                    >
                                        <x-atoms.logo size="sm" />
                                    </x-molecules.file-field>
                                </x-molecules.form-section>

                                <x-molecules.form-section
                                    :title="__('seguridad.organizacion.seccion_contacto')"
                                    :count="__('seguridad.organizacion.campos_contador', ['cantidad' => 3])"
                                >
                                    <x-atoms.input
                                        type="email"
                                        name="contacto_email"
                                        label="{{ __('seguridad.organizacion.campo_email') }}"
                                        value="{{ __('seguridad.organizacion.mock_email') }}"
                                        disabled
                                    />

                                    <x-atoms.input
                                        type="tel"
                                        name="contacto_telefono"
                                        label="{{ __('seguridad.organizacion.campo_telefono') }}"
                                        value="{{ __('seguridad.organizacion.mock_telefono') }}"
                                        disabled
                                    />

                                    <x-atoms.input
                                        class="ag-form-section__field--full"
                                        type="text"
                                        name="contacto_direccion"
                                        label="{{ __('seguridad.organizacion.campo_direccion') }}"
                                        value="{{ __('seguridad.organizacion.mock_direccion') }}"
                                        disabled
                                    />
                                </x-molecules.form-section>

                                <x-molecules.form-section :title="__('seguridad.organizacion.seccion_plan')">
                                    <div
                                        role="radiogroup"
                                        aria-label="{{ __('seguridad.organizacion.plan_group_label') }}"
                                        class="ag-organizacion__plan-grid ag-form-section__field--full"
                                    >
                                        <x-molecules.plan-card
                                            name="plan"
                                            value="basico"
                                            plan-name="{{ __('seguridad.organizacion.plan_basico_nombre') }}"
                                            price="{{ __('seguridad.organizacion.plan_basico_precio') }}"
                                            period="{{ __('seguridad.organizacion.plan_period') }}"
                                            :features="[
                                                __('seguridad.organizacion.plan_basico_feat_1'),
                                                __('seguridad.organizacion.plan_basico_feat_2'),
                                                __('seguridad.organizacion.plan_basico_feat_3'),
                                            ]"
                                            disabled
                                        />

                                        <x-molecules.plan-card
                                            name="plan"
                                            value="profesional"
                                            plan-name="{{ __('seguridad.organizacion.plan_profesional_nombre') }}"
                                            price="{{ __('seguridad.organizacion.plan_profesional_precio') }}"
                                            period="{{ __('seguridad.organizacion.plan_period') }}"
                                            :features="[
                                                __('seguridad.organizacion.plan_profesional_feat_1'),
                                                __('seguridad.organizacion.plan_profesional_feat_2'),
                                                __('seguridad.organizacion.plan_profesional_feat_3'),
                                                __('seguridad.organizacion.plan_profesional_feat_4'),
                                            ]"
                                            selected
                                            disabled
                                            highlighted-label="{{ __('seguridad.organizacion.plan_destacado') }}"
                                        />

                                        <x-molecules.plan-card
                                            name="plan"
                                            value="enterprise"
                                            plan-name="{{ __('seguridad.organizacion.plan_enterprise_nombre') }}"
                                            price="{{ __('seguridad.organizacion.plan_enterprise_precio') }}"
                                            period="{{ __('seguridad.organizacion.plan_period') }}"
                                            :features="[
                                                __('seguridad.organizacion.plan_enterprise_feat_1'),
                                                __('seguridad.organizacion.plan_enterprise_feat_2'),
                                                __('seguridad.organizacion.plan_enterprise_feat_3'),
                                                __('seguridad.organizacion.plan_enterprise_feat_4'),
                                                __('seguridad.organizacion.plan_enterprise_feat_5'),
                                            ]"
                                            disabled
                                        />
                                    </div>
                                </x-molecules.form-section>

                                <x-molecules.form-section
                                    :title="__('seguridad.organizacion.seccion_funcionalidades')"
                                    :count="__('seguridad.organizacion.campos_contador', ['cantidad' => 1])"
                                >
                                    <x-atoms.switch
                                        name="multi_sucursal"
                                        label="{{ __('seguridad.organizacion.switch_multi_sucursal') }}"
                                        :checked="false"
                                        :help="__('seguridad.organizacion.switch_multi_sucursal_help')"
                                        disabled
                                    />
                                </x-molecules.form-section>
                            </div>

                            <aside class="ag-organizacion__aside">
                                <x-molecules.progress-meter
                                    :title="__('seguridad.organizacion.aside_progreso_titulo')"
                                    :percent="$progreso['percent']"
                                    :summary-label="$progreso['summaryLabel']"
                                    :items="$progreso['items']"
                                />

                                <x-molecules.summary-card
                                    :title="__('seguridad.organizacion.aside_suscripcion_titulo')"
                                    :items="$suscripcion"
                                >
                                    <x-slot:action>
                                        <x-atoms.button type="button" variant="outline" block disabled>
                                            {{ __('seguridad.organizacion.aside_suscripcion_accion') }}
                                        </x-atoms.button>
                                    </x-slot:action>
                                </x-molecules.summary-card>
                            </aside>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade {{ $tabActiva === 'facturacion' ? 'show active' : '' }}" id="ag-tab-facturacion" role="tabpanel" tabindex="0">
                    <form
                        class="ag-organizacion__form"
                        method="POST"
                        action="{{ route('panel.organizacion.facturacion.actualizar') }}"
                    >
                        @csrf
                        <x-molecules.form-section
                            :title="__('seguridad.organizacion.seccion_facturacion')"
                            :count="__('seguridad.organizacion.campos_contador', ['cantidad' => 5])"
                        >
                            <x-atoms.input
                                type="text"
                                name="razon_social_fiscal"
                                label="{{ __('seguridad.organizacion.campo_razon_social_fiscal') }}"
                                value="{{ old('razon_social_fiscal', $datosFiscales?->razon_social_fiscal) }}"
                                error="{{ $errors->first('razon_social_fiscal') }}"
                                required
                                :disabled="! $puedeEditarFacturacion"
                            />

                            <x-atoms.input
                                type="text"
                                name="nit"
                                label="{{ __('seguridad.organizacion.campo_nit') }}"
                                value="{{ old('nit', $datosFiscales?->nit) }}"
                                error="{{ $errors->first('nit') }}"
                                required
                                :disabled="! $puedeEditarFacturacion"
                            />

                            <x-atoms.input
                                class="ag-form-section__field--full"
                                type="text"
                                name="domicilio_fiscal"
                                label="{{ __('seguridad.organizacion.campo_domicilio_fiscal') }}"
                                value="{{ old('domicilio_fiscal', $datosFiscales?->domicilio_fiscal) }}"
                                error="{{ $errors->first('domicilio_fiscal') }}"
                                required
                                :disabled="! $puedeEditarFacturacion"
                            />

                            <x-atoms.input
                                class="ag-form-section__field--full"
                                type="text"
                                name="actividad_economica"
                                label="{{ __('seguridad.organizacion.campo_actividad_economica') }}"
                                value="{{ old('actividad_economica', $datosFiscales?->actividad_economica) }}"
                                error="{{ $errors->first('actividad_economica') }}"
                                required
                                :disabled="! $puedeEditarFacturacion"
                            />

                            <x-atoms.textarea
                                class="ag-form-section__field--full"
                                name="leyenda_pie"
                                label="{{ __('seguridad.organizacion.campo_leyenda_pie') }}"
                                value="{{ old('leyenda_pie', $datosFiscales?->leyenda_pie) }}"
                                help="{{ __('seguridad.organizacion.campo_leyenda_pie_ayuda') }}"
                                :disabled="! $puedeEditarFacturacion"
                            />
                        </x-molecules.form-section>

                        @if ($puedeEditarFacturacion)
                            <x-organisms.form-actions-bar :status="__('seguridad.organizacion.facturacion_estado')">
                                <x-slot:actions>
                                    <x-atoms.button type="submit" variant="primary">
                                        {{ __('ui.action.save') }}
                                    </x-atoms.button>
                                </x-slot:actions>
                            </x-organisms.form-actions-bar>
                        @endif
                    </form>
                </div>
            </div>

            <x-organisms.form-actions-bar :status="__('seguridad.organizacion.estado_sin_cambios')">
                <x-slot:actions>
                    <x-atoms.button type="button" variant="outline" disabled>
                        {{ __('seguridad.organizacion.accion_descartar') }}
                    </x-atoms.button>
                    <x-atoms.button type="button" variant="primary" disabled>
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
