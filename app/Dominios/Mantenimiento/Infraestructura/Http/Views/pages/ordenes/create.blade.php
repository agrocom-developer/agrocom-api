{{--
    Page: ordenes/create (GET /panel/ordenes-mantenimiento/crear, panel.ordenes-mantenimiento.create)
    Alta de una orden de mantenimiento (HU-37, tarea 53): abre la orden en
    estado `Abierta` (MaquinaEstadosOrdenMantenimiento::abrir()) — el cierre es
    la otra cara de la misma ficha (ordenes/edit.blade.php), otra
    responsabilidad (invariante 7 de CLAUDE.md).

    El formulario real vive en `_formulario.blade.php`, compartido con
    `edit.blade.php` desde la tarea 116.

    Datos esperados (ver OrdenesMantenimientoController::create()): la cáscara
    de CascaraPanel, más $dronesDisponibles y $vehiculosDisponibles
    (Collection<int, string>): id => identificador, equipos vivos.

    Gateada por `mantenimiento.orden.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.ordenes.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.ordenes.titulo_crear')"
    >
        @include('mantenimiento::pages.ordenes._formulario', [
            'orden' => null,
            'dronesDisponibles' => $dronesDisponibles,
            'vehiculosDisponibles' => $vehiculosDisponibles,
            'etiquetaEquipo' => null,
            'repuestosDisponibles' => collect(),
            'basesDisponibles' => collect(),
            'stockPorRepuesto' => [],
            'consumos' => null,
            'nombresBase' => [],
            'puedeCerrar' => false,
            'pasosEstado' => null,
            'ayudaEstado' => null,
            'resumenRelacionado' => null,
        ])
    </x-templates.panel-layout>
</x-templates.panel-shell>
