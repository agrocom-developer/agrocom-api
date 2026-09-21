{{--
    Page: propiedades/lotes-editar-bloque (GET/PUT /panel/propiedades/{propiedad}/lotes/bloque,
    panel.propiedades.lotes.bloque[.guardar])
    "Editar en bloque" (19/9/2026, pedido directo): la contraparte de
    "Crear Lotes" para los lotes que la propiedad ya tiene. Con un solo
    formulario se corrigen las hectáreas y el terreno de TODOS, y se suman o
    quitan lotes cambiando la cantidad — sin tocar lote por lote un dato que
    en todos es el mismo (p. ej. la hectárea de relleno con la que nacen).
    Se entra desde el resumen "Lotes" de la ficha de la propiedad (junto a
    "Ver lista de lotes"), gateado por `comercial.lote.editar`.

    El formulario es el mismo de "Crear Lotes"
    (`_lotes-bloque-formulario.blade.php`), precargado con lo que los lotes
    tienen hoy: si un atributo es igual en todos llega con su valor, y si
    varía llega vacío y la pantalla avisa que al guardar se igualan. Las
    hectáreas vacías dejan las de cada lote como están (ver
    `EditarLotesEnBloque`). Al guardar se queda acá, con el resultado.

    Datos esperados (ver EditarLotesBloqueController::mostrar()): la cáscara
    de CascaraPanel, más:
    - $propiedad (Propiedad, con `cliente` cargado).
    - $resumen (ResumenLotesEnBloque): prefijo, códigos y valores comunes.
    - $puedeCrear, $puedeEliminar (bool): permisos de Lote que habilitan subir
      o bajar la cantidad — el formulario limita el número a lo permitido.
--}}
@php
    $total = $resumen->total();
    $valores = [
        'prefijo' => $resumen->prefijo,
        'cantidad' => $total,
        'hectareas' => $resumen->hectareas ?? '',
        'terreno' => [
            'desnivel' => $resumen->desnivel,
            'limpieza' => $resumen->limpieza,
            'restricciones' => $resumen->restricciones,
        ],
    ];
@endphp
<x-templates.panel-shell :title="__('comercial.propiedades.lotes_bloque_titulo', ['propiedad' => $propiedad->nombre])" :tema="$tema">
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
        :vista-actual="__('comercial.propiedades.lotes_bloque_titulo', ['propiedad' => $propiedad->nombre])"
    >
        <div class="ag-lotes-generar">
            <x-organisms.page-header
                :title="__('comercial.propiedades.lotes_bloque_titulo', ['propiedad' => $propiedad->nombre])"
                :subtitle="__('comercial.propiedades.lotes_bloque_subtitulo')"
            >
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.propiedades.edit', $propiedad)" :label="__('comercial.propiedades.lotes_generar_volver')" />
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ __('comercial.propiedades.lotes_bloque_error') }}
                </x-molecules.alert-strip>
            @endif

            @include('comercial::pages.propiedades._lotes-bloque-formulario', [
                'modo' => 'editar',
                'propiedad' => $propiedad,
                'valores' => $valores,
                'lotesBase' => 0,
                'hectareasAsignadas' => '0',
                'lotesPorTanda' => $lotesPorTanda,
                'cantidadMin' => $puedeEliminar ? 1 : $total,
                'cantidadMax' => $puedeCrear ? $total + $lotesPorTanda : $total,
                'codigos' => $resumen->codigos,
                'variables' => $resumen->variables,
            ])
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
