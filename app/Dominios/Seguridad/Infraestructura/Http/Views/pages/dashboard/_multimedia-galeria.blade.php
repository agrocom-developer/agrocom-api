{{--
    Parcial: galería multimedia — una tarjeta por sesión con la evidencia
    gráfica que dejó (captura del control remoto y fotos de incidencia).

    Las imágenes se sirven por `panel.evidencias.archivo`, que reverifica el
    permiso: la evidencia vive en un disco privado, no en public/.

    Espera: $sesiones (list): filas de ArmarDashboard::multimedia().
--}}
@php
    /** Minutos a "h:mm" — formato, no dato. `null` en una sesión sin cerrar. */
    $comoDuracion = fn (?int $minutos) => $minutos === null
        ? '—'
        : sprintf('%d:%02d', intdiv($minutos, 60), $minutos % 60);
@endphp

<div class="ag-multimedia-grid">
    @foreach ($sesiones as $sesion)
        <x-molecules.captura-rc-card
            :fecha="\Illuminate\Support\Carbon::parse($sesion['fecha'])->format('d/m/Y H:i')"
            :piloto="$sesion['piloto'] ?? '—'"
            :lote="$sesion['lote']"
            :dron="$sesion['dron'] ?? '—'"
            :hectareas="number_format((float) $sesion['hectareas'], 2, ',', '.').' ha'"
            :tiempo-vuelo="$comoDuracion($sesion['minutosVuelo'])"
            :pesticida-litros="$sesion['litros'] !== null ? number_format((float) $sesion['litros'], 2, ',', '.').' L' : '—'"
            :capturas="array_map(fn (array $captura) => [
                'url' => $captura['url'],
                'descripcion' => __('operaciones.evidencias.tipo.'.$captura['tipo']),
            ], $sesion['capturas'])"
        />
    @endforeach
</div>
