{{--
    Page: propiedades/lotes-generar (GET/POST /panel/propiedades/{propiedad}/lotes/generar,
    panel.propiedades.lotes.generar[.guardar])
    "Crear Lotes" con un solo botón (HU-72 reconstruida, 16/9/2026): cuántos
    lotes, con qué prefijo de código, y con qué atributos de terreno — LOS
    MISMOS para todos los lotes generados (pedido directo, 16/9/2026: una
    sola carga, no una fila por lote; si alguno necesita algo distinto se
    ajusta después desde su propia ficha). Sin cultivo ni campaña (ver
    docblock de CrearLotesMasivo: eso es siembra, no estructura del lote).
    Se entra desde el aside "Lotes" de la ficha de la propiedad, gateada
    por `comercial.lote.crear`.

    Cliente y propiedad se muestran SIEMPRE, de solo lectura (pedido
    directo, 16/9/2026): a simple vista tiene que quedar claro a dónde van
    a parar estos lotes.

    Cada lote nace con código "{prefijo}{número correlativo}" (el número
    sigue desde el máximo ya usado en la propiedad con ese mismo prefijo) y
    una hectárea placeholder — se corrige después dibujando el polígono
    ("usar superficie") desde la propia ficha del lote, junto con el
    nombre si hace falta. Esta pantalla no es un ABM: al guardar vuelve al
    listado de lotes, ya filtrado por esta propiedad.

    Datos esperados (ver GenerarLotesController::mostrar()): la cáscara de
    CascaraPanel, más:
    - $propiedad (Propiedad, con `cliente` cargado).
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
                    <x-molecules.boton-volver :href="route('panel.propiedades.edit', $propiedad)" :label="__('comercial.propiedades.lotes_generar_volver')" />
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ __('comercial.propiedades.lotes_generar_error') }}
                </x-molecules.alert-strip>
            @endif

            <form method="POST" action="{{ route('panel.propiedades.lotes.generar.guardar', $propiedad) }}" class="ag-lotes-generar-form" novalidate data-ag-lotes-generar-form>
                @csrf

                <x-molecules.form-section
                    :title="__('comercial.propiedades.lotes_generar_seccion_destino')"
                    :count="__('comercial.propiedades.campos_contador', ['cantidad' => 2])"
                >
                    <div class="ag-input">
                        <span class="ag-input__label">{{ __('comercial.propiedades.lotes_generar_cliente') }}</span>
                        <p class="ag-lotes-generar__solo-lectura">{{ $propiedad->cliente->razon_social }}</p>
                    </div>

                    <div class="ag-input">
                        <span class="ag-input__label">{{ __('comercial.propiedades.lotes_generar_propiedad') }}</span>
                        <p class="ag-lotes-generar__solo-lectura">{{ $propiedad->nombre }}</p>
                    </div>
                </x-molecules.form-section>

                <x-molecules.form-section
                    :title="__('comercial.propiedades.lotes_generar_seccion_cuantos')"
                    :count="__('comercial.propiedades.campos_contador', ['cantidad' => 2])"
                >
                    <x-atoms.input
                        type="text"
                        name="prefijo"
                        :label="__('comercial.propiedades.lotes_generar_prefijo')"
                        :help="__('comercial.propiedades.lotes_generar_prefijo_ayuda')"
                        :value="old('prefijo', 'Lote ')"
                        required
                        :error="$errors->first('prefijo')"
                    />

                    <x-atoms.input
                        type="number"
                        name="cantidad"
                        :label="__('comercial.propiedades.lotes_generar_cantidad')"
                        :value="old('cantidad', 1)"
                        min="1"
                        max="50"
                        required
                        :error="$errors->first('cantidad')"
                    />
                </x-molecules.form-section>

                <x-molecules.form-section
                    :title="__('comercial.propiedades.lotes_generar_seccion_terreno')"
                    :count="__('comercial.propiedades.campos_contador', ['cantidad' => 3])"
                >
                    <p class="ag-form-section__field--full ag-lotes-generar__ayuda-terreno">
                        {{ __('comercial.propiedades.lotes_generar_terreno_ayuda') }}
                    </p>

                    @include('comercial::pages.lotes._lote-terreno', [
                        'lote' => old('terreno', []),
                        'prefijo' => 'terreno',
                        'idBase' => 'terreno',
                        'erroresPrefijo' => 'terreno',
                    ])
                </x-molecules.form-section>

                <x-organisms.form-actions-bar :status="__('comercial.propiedades.lotes_generar_estado_form')">
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.propiedades.edit', $propiedad)" variant="outline">
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
