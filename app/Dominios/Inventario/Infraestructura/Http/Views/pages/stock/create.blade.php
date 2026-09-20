{{--
    Page: stock/create (GET /panel/stock/movimientos/crear, panel.stock.movimientos.create)
    Alta de un movimiento de stock (HU-36, tarea 52): el formulario real vive
    en `_formulario.blade.php`.

    Datos esperados (ver StockController::create()): la cáscara de
    CascaraPanel, más $repuestosDisponibles (Collection<int, string>),
    $basesDisponibles (array<int, string>), $tipos
    (list<TipoMovimientoInventario>), $sentidos (list<SentidoAjusteInventario>)
    y $repuestoIdInicial (int|null, el repuesto que ya llega elegido).

    Gateada por `inventario.movimiento.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('inventario.stock.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('inventario.stock.titulo_crear')"
    >
        @include('inventario::pages.stock._formulario', [
            'repuestosDisponibles' => $repuestosDisponibles,
            'basesDisponibles' => $basesDisponibles,
            'tipos' => $tipos,
            'sentidos' => $sentidos,
            'repuestoIdInicial' => $repuestoIdInicial,
        ])
    </x-templates.panel-layout>
</x-templates.panel-shell>
