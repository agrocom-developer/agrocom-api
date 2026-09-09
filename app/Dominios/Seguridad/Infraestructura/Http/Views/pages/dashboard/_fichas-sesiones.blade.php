{{--
    Parcial: sesiones como FICHAS (móvil <768px, ver pages/dashboard.css).
    Tarjetas apiladas con hora + lote + chip arriba, piloto/dron/ha abajo.

    Lo incluye `_tabla-sesiones`, no la página: las tres representaciones
    (grilla de escritorio, lista de tablet, fichas de móvil) son el mismo
    componente a distintos anchos, y quien lo usa no debería tener que
    acordarse de incluir las tres.

    Espera:
    - $sesiones (list): mismas filas que `_tabla-sesiones`.
    - $limite (int, opcional, default 3).
--}}
@php($limite = $limite ?? 3)

<div class="ag-session-cards">
    @foreach (array_slice($sesiones, 0, $limite) as $sesion)
        <div class="ag-session-cards__card">
            <div class="ag-session-cards__head">
                <span class="ag-session-cards__hora">{{ \Illuminate\Support\Carbon::parse($sesion['inicio'])->format('H:i') }}</span>
                <span class="ag-session-cards__lote">{{ $sesion['lote'] }}</span>
                <x-atoms.badge :variant="$sesion['tono']">{{ __('operaciones.sesion.estado.'.$sesion['estado']) }}</x-atoms.badge>
            </div>
            <div class="ag-session-cards__meta">
                <span>{{ $sesion['piloto'] ?? '—' }}</span>
                <span>{{ $sesion['dron'] ?? '—' }}</span>
                <span>{{ number_format((float) $sesion['hectareas'], 2, ',', '.') }} ha</span>
            </div>
        </div>
    @endforeach
</div>
