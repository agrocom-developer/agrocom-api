{{--
    Page: propiedades/lotes-generar (GET/POST /panel/propiedades/{propiedad}/lotes/generar,
    panel.propiedades.lotes.generar[.guardar])
    "Crear Lotes" con un solo botón (HU-72 reconstruida, 16/9/2026): cuántos
    lotes y, opcionalmente, con qué cultivo sembrarlos en qué campaña. Se
    entra desde el aside "Lotes" de la ficha de la propiedad, gateada por
    `comercial.lote.crear` (ver GenerarLotesController).

    Cada lote nace con un código provisorio ("Lote N") y una hectárea
    placeholder — se renombra, se dibuja el polígono y, si corresponde, se
    cambia el cultivo desde la propia ficha del lote (aside "Siembra
    actual") o desde `propiedades/siembra`. Esta pantalla no es un ABM: al
    guardar vuelve al listado de lotes, ya filtrado por esta propiedad.

    Datos esperados (ver GenerarLotesController::mostrar()): la cáscara de
    CascaraPanel, más:
    - $propiedad (Propiedad).
    - $cultivosDisponibles (Collection<int, string>): id => nombre, cultivos activos.
    - $campaniasDisponibles (Collection<int, string>): id => código, todo el catálogo.
--}}
<x-templates.panel-shell :title="__('comercial.propiedades.lotes_generar_titulo', ['propiedad' => $propiedad->nombre])" :tema="$tema">
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
        :vista-actual="__('comercial.propiedades.lotes_generar_titulo', ['propiedad' => $propiedad->nombre])"
    >
        <div class="ag-lotes-generar">
            <x-organisms.page-header
                :title="__('comercial.propiedades.lotes_generar_titulo', ['propiedad' => $propiedad->nombre])"
                :subtitle="__('comercial.propiedades.lotes_generar_subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button href="{{ route('panel.propiedades.edit', $propiedad) }}" variant="outline" icon="arrow_back">
                        {{ __('comercial.propiedades.lotes_generar_volver') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ __('comercial.propiedades.lotes_generar_error') }}
                </x-molecules.alert-strip>
            @endif

            <form method="POST" action="{{ route('panel.propiedades.lotes.generar.guardar', $propiedad) }}" class="ag-lotes-generar-form" novalidate>
                @csrf

                <x-molecules.form-section
                    :title="__('comercial.propiedades.lotes_generar_seccion')"
                    :count="__('comercial.propiedades.campos_contador', ['cantidad' => 3])"
                >
                    <x-atoms.input
                        type="number"
                        name="cantidad"
                        label="{{ __('comercial.propiedades.lotes_generar_cantidad') }}"
                        help="{{ __('comercial.propiedades.lotes_generar_cantidad_ayuda') }}"
                        value="{{ old('cantidad', 1) }}"
                        min="1"
                        max="50"
                        required
                        error="{{ $errors->first('cantidad') }}"
                    />

                    <x-atoms.select
                        name="cultivo_id"
                        id="cultivo_id"
                        label="{{ __('comercial.propiedades.lotes_generar_cultivo') }}"
                        :options="$cultivosDisponibles"
                        :value="old('cultivo_id')"
                        placeholder="{{ __('comercial.propiedades.lotes_generar_cultivo_placeholder') }}"
                        error="{{ $errors->first('cultivo_id') }}"
                    />

                    <x-atoms.select
                        name="campania_id"
                        id="campania_id"
                        label="{{ __('comercial.propiedades.lotes_generar_campania') }}"
                        :options="$campaniasDisponibles"
                        :value="old('campania_id')"
                        placeholder="{{ __('comercial.propiedades.lotes_generar_campania_placeholder') }}"
                        help="{{ __('comercial.propiedades.lotes_generar_campania_ayuda') }}"
                        error="{{ $errors->first('campania_id') }}"
                    />
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('comercial.propiedades.lotes_generar_estado_form')">
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.propiedades.edit', $propiedad) }}" variant="outline">
                            {{ __('ui.action.cancel') }}
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary" icon="add">
                            {{ __('comercial.propiedades.lotes_generar_accion') }}
                        </x-atoms.button>
                    </x-slot:actions>
                </x-organisms.form-actions-bar>
            </form>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
