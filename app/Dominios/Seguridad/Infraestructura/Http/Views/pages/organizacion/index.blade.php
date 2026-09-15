{{--
    Page: organizacion/index (GET /panel/organizacion, panel.organizacion.index)
    Pestaña "Organización": "Datos de empresa" y "Datos de contacto" son REALES desde
    el 11/9/2026 — formulario propio, con su propio botón "Guardar", que persiste en
    `sec_datos_empresa` vía `OrganizacionController::actualizarEmpresa()`. El resto de
    la pestaña (logo, plan de suscripción, multi-sucursal) SIGUE siendo mockup sin
    tabla ni ADR — es la conversación sobre un pivot SaaS multi-tenant que no está
    decidida. Por eso esas secciones NO viven dentro del `<form>` real: son `<div>`s
    con controles `disabled`, deliberadamente fuera de cualquier form (HTML no permite
    forms anidados, y meterlas dentro del real sugeriría que se guardan con el resto).

    Pestaña "Facturación" (tarea 78, HU-55): REAL, mismo criterio. Formulario propio,
    con su propio botón "Guardar", que persiste en `sec_datos_fiscales` vía
    `OrganizacionController::actualizarFacturacion()`.

    Las dos pestañas reales comparten el mismo gate: `seguridad.organizacion.editar`
    ($puedeEditarOrganizacion) — sin ese permiso, los campos se muestran deshabilitados
    y sin barra de acciones, mismo criterio que el resto del panel (ver vs. editar).

    Sin barra de acciones global ni acciones en `page-header` (11/9/2026, corrección
    de bug real): había una `form-actions-bar` decorativa y deshabilitada FUERA de
    los tabs, de la era 100% mockup. `form-actions-bar` es `position: sticky; bottom:
    0` — con dos barras en el mismo contenedor con scroll, la de más abajo en el DOM
    (esta, siempre deshabilitada) pintaba ENCIMA de la real del tab activo y absorbía
    el clic: el botón "Guardar" real quedaba inalcanzable. Cada tab ya tiene su propia
    barra real, dentro de su propio `<form>` — no hace falta una global.

    Reconstruida sobre el arquetipo formulario (tarea 31): es el caso de prueba de
    `organisms/page-header`, `molecules/tabs`, `molecules/form-section` evolucionado
    (tarjeta + grid de dos columnas), `molecules/progress-meter`, `molecules/summary-card`,
    `molecules/file-field` y `organisms/form-actions-bar` — ver
    docs/diseno/guia_pantalla_panel.md §6.3.

    Datos esperados (ver OrganizacionController::index()): la cáscara completa de
    CascaraPanel (menu/roles/…/tema/zonaHoraria/version) + tabs/progreso/
    suscripcion/logoArchivo/tabActiva/datosEmpresa/datosFiscales/puedeEditarOrganizacion.

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
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.tabs :items="$tabs" :aria-label="__('seguridad.organizacion.tabs_aria')" />

            <div class="tab-content ag-organizacion__panes">
                <div class="tab-pane fade {{ $tabActiva === 'organizacion' ? 'show active' : '' }}" id="ag-tab-organizacion" role="tabpanel" tabindex="0">
                    <div class="ag-organizacion__layout">
                        <div class="ag-organizacion__main">
                            <form
                                method="POST"
                                action="{{ route('panel.organizacion.empresa.actualizar') }}"
                                enctype="multipart/form-data"
                                class="ag-organizacion__form"
                            >
                                @csrf
                                <x-molecules.form-section
                                    :title="__('seguridad.organizacion.seccion_datos_empresa')"
                                    :count="__('seguridad.organizacion.campos_contador', ['cantidad' => 3])"
                                >
                                    <x-atoms.input
                                        type="text"
                                        name="nombre"
                                        label="{{ __('seguridad.organizacion.campo_nombre') }}"
                                        value="{{ old('nombre', $datosEmpresa?->nombre) }}"
                                        error="{{ $errors->first('nombre') }}"
                                        required
                                        :disabled="! $puedeEditarOrganizacion"
                                    />

                                    <x-atoms.input
                                        type="text"
                                        name="rubro"
                                        label="{{ __('seguridad.organizacion.campo_rubro') }}"
                                        value="{{ old('rubro', $datosEmpresa?->rubro) }}"
                                        error="{{ $errors->first('rubro') }}"
                                        required
                                        :disabled="! $puedeEditarOrganizacion"
                                    />

                                    <x-molecules.file-field
                                        class="ag-form-section__field--full"
                                        name="logo"
                                        size="lg"
                                        accept=".png,.svg"
                                        remove-name="logo_eliminar"
                                        :label="__('seguridad.organizacion.campo_logo')"
                                        :file-name="$logoArchivo['nombre'] ?? null"
                                        :file-size="$logoArchivo['peso'] ?? null"
                                        :preview-url="$logoArchivo['url'] ?? null"
                                        :help="__('seguridad.organizacion.campo_logo_ayuda')"
                                        :replace-label="__('seguridad.organizacion.campo_logo_reemplazar')"
                                        :remove-label="$logoArchivo ? __('seguridad.organizacion.campo_logo_quitar') : null"
                                        :disabled="! $puedeEditarOrganizacion"
                                        error="{{ $errors->first('logo') }}"
                                    >
                                        <x-atoms.logo size="md" />
                                    </x-molecules.file-field>
                                </x-molecules.form-section>

                                <x-molecules.form-section
                                    :title="__('seguridad.organizacion.seccion_contacto')"
                                    :count="__('seguridad.organizacion.campos_contador', ['cantidad' => 3])"
                                >
                                    <x-atoms.input
                                        type="email"
                                        name="email"
                                        label="{{ __('seguridad.organizacion.campo_email') }}"
                                        value="{{ old('email', $datosEmpresa?->email) }}"
                                        error="{{ $errors->first('email') }}"
                                        :disabled="! $puedeEditarOrganizacion"
                                    />

                                    <x-atoms.input
                                        type="tel"
                                        name="telefono"
                                        label="{{ __('seguridad.organizacion.campo_telefono') }}"
                                        value="{{ old('telefono', $datosEmpresa?->telefono) }}"
                                        error="{{ $errors->first('telefono') }}"
                                        :disabled="! $puedeEditarOrganizacion"
                                    />

                                    <x-atoms.input
                                        class="ag-form-section__field--full"
                                        type="text"
                                        name="direccion"
                                        label="{{ __('seguridad.organizacion.campo_direccion') }}"
                                        value="{{ old('direccion', $datosEmpresa?->direccion) }}"
                                        error="{{ $errors->first('direccion') }}"
                                        :disabled="! $puedeEditarOrganizacion"
                                    />
                                </x-molecules.form-section>

                                @if ($puedeEditarOrganizacion)
                                    <x-organisms.form-actions-bar :status="__('seguridad.organizacion.empresa_estado')">
                                        <x-slot:actions>
                                            <x-atoms.button type="submit" variant="primary">
                                                {{ __('ui.action.save') }}
                                            </x-atoms.button>
                                        </x-slot:actions>
                                    </x-organisms.form-actions-bar>
                                @endif
                            </form>

                            {{-- Plan de suscripción / funcionalidades: mockup sin persistencia
                                 (pivot SaaS multi-tenant sin decidir, sin ADR) — a propósito
                                 FUERA del <form> de arriba, HTML no permite forms anidados y
                                 meterlas adentro sugeriría que se guardan con "Datos de empresa". --}}
                            <div class="ag-organizacion__form">
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
                                :disabled="! $puedeEditarOrganizacion"
                            />

                            <x-atoms.input
                                type="text"
                                name="nit"
                                label="{{ __('seguridad.organizacion.campo_nit') }}"
                                value="{{ old('nit', $datosFiscales?->nit) }}"
                                error="{{ $errors->first('nit') }}"
                                required
                                :disabled="! $puedeEditarOrganizacion"
                            />

                            <x-atoms.input
                                class="ag-form-section__field--full"
                                type="text"
                                name="domicilio_fiscal"
                                label="{{ __('seguridad.organizacion.campo_domicilio_fiscal') }}"
                                value="{{ old('domicilio_fiscal', $datosFiscales?->domicilio_fiscal) }}"
                                error="{{ $errors->first('domicilio_fiscal') }}"
                                required
                                :disabled="! $puedeEditarOrganizacion"
                            />

                            <x-atoms.input
                                class="ag-form-section__field--full"
                                type="text"
                                name="actividad_economica"
                                label="{{ __('seguridad.organizacion.campo_actividad_economica') }}"
                                value="{{ old('actividad_economica', $datosFiscales?->actividad_economica) }}"
                                error="{{ $errors->first('actividad_economica') }}"
                                required
                                :disabled="! $puedeEditarOrganizacion"
                            />

                            <x-atoms.textarea
                                class="ag-form-section__field--full"
                                name="leyenda_pie"
                                label="{{ __('seguridad.organizacion.campo_leyenda_pie') }}"
                                value="{{ old('leyenda_pie', $datosFiscales?->leyenda_pie) }}"
                                help="{{ __('seguridad.organizacion.campo_leyenda_pie_ayuda') }}"
                                :disabled="! $puedeEditarOrganizacion"
                            />
                        </x-molecules.form-section>

                        @if ($puedeEditarOrganizacion)
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
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
