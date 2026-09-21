{{--
    Parcial: franja de alertas por excepción (HU-19) — lo único del
    dashboard que pide una acción, así que va primero.

    Reemplaza al aviso fijo "2 sesiones cerradas sin captura del RC" de la
    maqueta: aquel número estaba escrito a mano y no cambiaba nunca.

    Espera: $alertas (list<AlertaPanel>).
--}}
@foreach ($alertas as $alerta)
    <x-molecules.alert-strip :variant="$alerta->pendiente ? 'danger' : 'accent'" icon="warning">
        <strong>{{ __('operaciones.alertas.tipo.'.$alerta->tipo) }}</strong>
        <span class="ag-dash__rc-detalle">{{ $alerta->mensaje }}</span>
        <x-slot:action>
            <x-atoms.button
                :variant="$alerta->pendiente ? 'danger-outline' : 'outline'"
                size="sm"
                :href="route('panel.alertas.index')"
            >{{ __('seguridad.dashboard.alertas_ver') }}</x-atoms.button>
        </x-slot:action>
    </x-molecules.alert-strip>
@endforeach
