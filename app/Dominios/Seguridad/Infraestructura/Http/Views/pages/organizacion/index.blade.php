{{--
    Page: organizacion/index (GET /panel/organizacion, panel.organizacion.index)
    Mockup visual de "Registro de la compañía" — vista previa de una pantalla de
    gestión de organización multi-tenant futura (SIN implementación real de tenancy,
    sin tabla, sin persistencia). Prellenada con datos realistas para demostración.

    Datos esperados (ver OrganizacionController::index()): la cáscara
    completa de CascaraPanel (menu/roles/…/tema/campana/periodo/version).

    Este es un mockup de PRESENTACIÓN sin guardado funcional. El botón "Guardar"
    está deshabilitado con un help text visual que lo aclara.
--}}
<x-templates.panel-shell :title="__('seguridad.organizacion.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
    >
        <h1>{{ __('seguridad.organizacion.titulo') }}</h1>
        <p class="ag-organizacion__intro">
            {{ __('seguridad.organizacion.subtitulo') }}
        </p>

        {{-- Formulario mock (no envía nada) --}}
        <form class="ag-organizacion__form" onsubmit="return false">
            <x-molecules.form-section :title="__('seguridad.organizacion.seccion_datos_empresa')">
                <x-atoms.input
                    type="text"
                    name="empresa_nombre"
                    label="{{ __('seguridad.organizacion.campo_nombre') }}"
                    value="Agrocom SRL"
                    disabled
                />

                <x-atoms.input
                    type="text"
                    name="empresa_rubro"
                    label="{{ __('seguridad.organizacion.campo_rubro') }}"
                    value="{{ __('seguridad.organizacion.mock_rubro') }}"
                    disabled
                />

                <div>
                    <label class="ag-organizacion__logo-label">
                        {{ __('seguridad.organizacion.campo_logo') }}
                    </label>
                    <div class="ag-organizacion__logo-preview">
                        <span class="ag-organizacion__logo-preview-icon">
                            <x-atoms.logo />
                        </span>
                        <span class="ag-organizacion__logo-preview-desc">
                            {{ __('seguridad.organizacion.mock_logo_desc') }}
                        </span>
                    </div>
                </div>
            </x-molecules.form-section>

            <x-molecules.form-section :title="__('seguridad.organizacion.seccion_contacto')">
                <x-atoms.input
                    type="email"
                    name="contacto_email"
                    label="{{ __('seguridad.organizacion.campo_email') }}"
                    value="contacto@agrocom.com.ar"
                    disabled
                />

                <x-atoms.input
                    type="tel"
                    name="contacto_telefono"
                    label="{{ __('seguridad.organizacion.campo_telefono') }}"
                    value="+54 9 3815 55-4433"
                    disabled
                />

                <x-atoms.input
                    type="text"
                    name="contacto_direccion"
                    label="{{ __('seguridad.organizacion.campo_direccion') }}"
                    value="{{ __('seguridad.organizacion.mock_direccion') }}"
                    disabled
                />
            </x-molecules.form-section>

            <x-molecules.form-section :title="__('seguridad.organizacion.seccion_plan')">
                <div role="radiogroup" aria-label="{{ __('seguridad.organizacion.plan_group_label') }}" class="ag-organizacion__plan-grid">
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

            <x-molecules.form-section :title="__('seguridad.organizacion.seccion_funcionalidades')">
                <x-atoms.switch
                    name="multi_sucursal"
                    label="{{ __('seguridad.organizacion.switch_multi_sucursal') }}"
                    :checked="false"
                    :help="__('seguridad.organizacion.switch_multi_sucursal_help')"
                    disabled
                />
            </x-molecules.form-section>

            {{-- Botones de acción (decorativos/deshabilitados) --}}
            <div class="ag-organizacion__actions">
                <x-atoms.button type="button" disabled>
                    {{ __('ui.action.save') }}
                </x-atoms.button>
                <p class="ag-organizacion__actions-note">
                    {{ __('seguridad.organizacion.vista_previa_nota') }}
                </p>
            </div>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
