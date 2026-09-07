{{--
    Parcial: barras de "Pausas por causa" (quinta vuelta — maquetas 4a/5a y
    pestaña Pausas). Cada causa: fila con nombre + horas en mono, y una
    barra con el ancho de la maqueta y su tono de estado (no-texto, 3:1).

    Espera:
    - $causas (list<{causa, horas, pct, tono}>): ya resueltas por
      `_seccion-pausas`, que traduce la causa y calcula el porcentaje.
    - $grande (bool, opcional): barras de 9px (pestaña Pausas) en vez de 6px.
--}}
@php($grande = $grande ?? false)

<div class="ag-bars {{ $grande ? 'ag-bars--lg' : '' }}">
    @foreach ($causas as $causa)
        <div class="ag-bars__item">
            <div class="ag-bars__meta">
                <span>{{ $causa['causa'] }}</span>
                <span class="ag-bars__horas">{{ $causa['horas'] }}</span>
            </div>
            <div class="ag-bars__track">
                <div class="ag-bars__fill ag-bars__fill--{{ $causa['tono'] }}" style="width: {{ $causa['pct'] }}%"></div>
            </div>
        </div>
    @endforeach
</div>
