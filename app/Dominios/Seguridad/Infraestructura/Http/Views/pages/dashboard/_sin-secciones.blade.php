{{--
    Parcial: estado vacío del dashboard completo — el rol activo tiene el
    permiso de entrada (`seguridad.dashboard.ver`) pero ninguna sección
    habilitada, o ninguna con datos todavía.

    Existe para que ese caso sea una pantalla que explica en vez de una
    página en blanco: es exactamente lo que ve un rol recién creado antes de
    que se le asignen permisos.

    Desde el 19/9/2026 distingue la causa más común en una instalación
    nueva —ninguna campaña abierta todavía, por eso no hay contratos,
    órdenes ni sesiones que mostrar— del caso genérico de arriba. Sin
    campaña abierta, es un mensaje de bienvenida con el atajo para crear la
    primera (solo si el rol activo puede hacerlo); con una campaña abierta y
    aun así nada que mostrar, sigue el mensaje genérico.
    `$hayCampaniaAbierta`/`$puedeCrearCampania` los arma `ArmarDashboard`.
--}}
@if (! $hayCampaniaAbierta)
    <x-molecules.empty-state
        icon="calendar_month"
        :title="__('seguridad.dashboard.sin_campania_titulo')"
        :detail="__('seguridad.dashboard.sin_campania_detalle')"
    >
        @if ($puedeCrearCampania)
            <x-slot:action>
                <x-atoms.button :href="route('panel.campanias.create')" variant="primary" icon="add">
                    {{ __('seguridad.dashboard.sin_campania_accion') }}
                </x-atoms.button>
            </x-slot:action>
        @endif
    </x-molecules.empty-state>
@else
    <x-molecules.empty-state
        icon="dashboard"
        :title="__('seguridad.dashboard.vacio_titulo')"
        :detail="__('seguridad.dashboard.vacio_detalle')"
    />
@endif
