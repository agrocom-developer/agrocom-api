{{--
    Page: ordenes/edit (GET /panel/ordenes-mantenimiento/{orden}/editar, panel.ordenes-mantenimiento.edit)
    Ficha de una orden de mantenimiento (HU-37, tarea 53; homogeneizada en la
    tarea 116): el formulario real vive en `_formulario.blade.php`, compartido
    con `create.blade.php`.

    Pese al nombre de la ruta (`edit`, el mismo molde de URL que el resto del
    panel), la orden NO admite editar sus datos descriptivos: equipo, tipo y
    descripción se dibujan como campos de lectura, y lo único que esta
    pantalla puede cambiar es el estado — el cierre, con su descripción final
    y los repuestos consumidos (ver el docblock de
    `OrdenesMantenimientoController` y el de `_formulario.blade.php`).

    Datos esperados (ver OrdenesMantenimientoController::edit()): la cáscara de
    CascaraPanel, más $orden (OrdenMantenimiento), $etiquetaEquipo (string),
    $repuestosDisponibles / $basesDisponibles (Collection<int, string>),
    $stockPorRepuesto (array), $consumos (paginador de DatosConsumoOrden),
    $nombresBase (array<int, string>), $puedeCerrar (bool), $pasosEstado /
    $ayudaEstado (los de PasosDeOrdenMantenimiento) y $resumenRelacionado
    (tarjetas del aside, resueltas por el controlador; null mientras la orden
    sigue abierta).

    Gateada por `mantenimiento.orden.ver`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.ordenes.titulo_ficha')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.ordenes.titulo')"
    >
        @include('mantenimiento::pages.ordenes._formulario', [
            'orden' => $orden,
            'dronesDisponibles' => collect(),
            'vehiculosDisponibles' => collect(),
            'etiquetaEquipo' => $etiquetaEquipo,
            'repuestosDisponibles' => $repuestosDisponibles,
            'basesDisponibles' => $basesDisponibles,
            'stockPorRepuesto' => $stockPorRepuesto,
            'consumos' => $consumos,
            'nombresBase' => $nombresBase,
            'puedeCerrar' => $puedeCerrar,
            'pasosEstado' => $pasosEstado,
            'ayudaEstado' => $ayudaEstado,
            'resumenRelacionado' => $resumenRelacionado,
        ])
    </x-templates.panel-layout>
</x-templates.panel-shell>
