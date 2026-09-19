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
    las hectáreas que se indiquen acá, las mismas para todos (19/9/2026: antes
    nacían con 1 ha de relleno y había que corregirlas lote por lote) — el
    polígono se dibuja después desde la propia ficha del lote, junto con el
    nombre si hace falta. Esta pantalla no es un ABM: al guardar vuelve al
    listado de lotes, ya filtrado por esta propiedad. El formulario es el
    mismo de "Editar en bloque" (`_lotes-bloque-formulario.blade.php`).

    Datos esperados (ver GenerarLotesController::mostrar()): la cáscara de
    CascaraPanel, más:
    - $propiedad (Propiedad, con `cliente` cargado).
    - $lotesExistentes (int): lotes que la propiedad ya tiene — cuentan para
      repartir su superficie en la sugerencia de hectáreas.
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

            @include('comercial::pages.propiedades._lotes-bloque-formulario', [
                'modo' => 'crear',
                'propiedad' => $propiedad,
                'valores' => ['prefijo' => 'Lote ', 'cantidad' => 1, 'hectareas' => '', 'terreno' => []],
                'lotesBase' => $lotesExistentes,
                'hectareasAsignadas' => $hectareasAsignadas,
                'lotesPorTanda' => $lotesPorTanda,
                'cantidadMin' => 1,
                'cantidadMax' => $lotesPorTanda,
            ])
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
