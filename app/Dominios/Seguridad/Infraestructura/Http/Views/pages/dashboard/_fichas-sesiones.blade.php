{{--
    Parcial: sesiones como FICHAS (quinta vuelta — móvil, maqueta 5b):
    tarjetas apiladas con hora + lote + chip arriba y piloto/dron/ha abajo.
    Solo visible <768px (pages/dashboard.css).

    Espera:
    - $sesiones (list): filas de DatosDemoPanel::sesiones().
    - $limite (int, opcional, default 3): la maqueta 5b muestra 3.
--}}
@php($limite = $limite ?? 3)

<div class="ag-session-cards">
    @foreach (array_slice($sesiones, 0, $limite) as $sesion)
        <div class="ag-session-cards__card">
            <div class="ag-session-cards__head">
                <span class="ag-session-cards__hora">{{ $sesion['hora'] }}</span>
                <span class="ag-session-cards__lote">{{ $sesion['lote'] }}</span>
                <x-atoms.badge :variant="$sesion['variante']">{{ __('operaciones.sesion.estado.'.$sesion['estado']) }}</x-atoms.badge>
            </div>
            <div class="ag-session-cards__meta">
                <span>{{ $sesion['piloto'] }}</span>
                <span>{{ $sesion['dron'] }}</span>
                <span>{{ $sesion['ha'] }}</span>
            </div>
        </div>
    @endforeach
</div>
