{{--
    Page: ordenes/edit (GET /panel/ordenes/{orden}/editar, panel.ordenes.edit)
    Edición de una orden de aplicación (HU-25, tarea 38): el formulario real
    vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver OrdenesController::edit()): la cáscara de
    CascaraPanel, más:
    - $orden, $contratosDisponibles, $datosContrato (con los contactos del
      cliente del contrato, nunca los de otros) y $categoriasInsumoDisponibles.
    - $pasosEstado (los pasos de `molecules/step-arrow`) y $ayudaEstado (el
      párrafo que los acompaña), de `PasosDeOrden`.
    - $resumenOrden (el resumen de la orden, con sus trabajos y hectáreas: decide
      si «Cerrar» ofrece confirmar o avisa qué falta).
    - $exigeMotivo (bool: la orden ya está publicada, la corrección pide motivo) y
      $insumoBloqueado (bool: la orden ya tiene trabajos, el insumo y la dosis no
      se cambian), de `PoliticaEdicionOrden`.
    - $resumenRelacionado (tarjetas del aside: órdenes de trabajo, asignación
      de equipos y estadías en hacienda).

    Gateada por `operaciones.orden.editar`, verificado server-side en el
    controlador. Que la orden siga siendo editable lo
    exige `Aplicacion/ActualizarOrden` (`PoliticaEdicionOrden`): se corrige una orden
    abierta —`emitida`, `vigente` o `pausada`—; una cerrada redirige al detalle. El cambio de ESTADO no se
    procesa acá: los pasos del formulario solo abren un modal, cuyos `<form>`
    van en `_orden-modales.blade.php`, después del formulario y no adentro (un
    `<form>` no se anida en otro) — mismo criterio que `contratos/edit`.
--}}
<x-templates.panel-shell :title="__('operaciones.ordenes.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes.titulo_editar')"
    >
        @include('operaciones::pages.ordenes._formulario', ['orden' => $orden])
        @include('operaciones::pages.ordenes._orden-modales', ['orden' => $orden, 'contexto' => 'edicion-', 'conEliminar' => false, 'resumenOrden' => $resumenOrden])
    </x-templates.panel-layout>
</x-templates.panel-shell>
