{{--
    Organism: mapa-operativo (Fase 6) — mapa satelital (Leaflet + Esri World
    Imagery) con los polígonos de lotes coloreados por estado y las
    sesiones de fumigación georreferenciadas. Sin lógica de negocio: recibe
    los FeatureCollection ya armados (mismo shape que `com_lotes.geometria`)
    y los serializa a `data-*` — resources/js/organisms/dashboard-map.js
    (import() dinámico, ver app.js) los lee e instancia Leaflet.

    Props:
    - lotes (requerido): FeatureCollection GeoJSON de polígonos.
    - sesiones (requerido): FeatureCollection GeoJSON de puntos.
    - centro (opcional): {lat, lng} del centro inicial del mapa.
    - zoom (opcional, default 13).
--}}
@props([
    'lotes',
    'sesiones',
    'centro' => ['lat' => -17.343, 'lng' => -62.843],
    'zoom' => 13,
])

<div {{ $attributes->class(['ag-mapa-operativo']) }}>
    <div
        class="ag-mapa-operativo__lienzo"
        data-ag-map
        data-ag-map-lotes="{{ json_encode($lotes) }}"
        data-ag-map-sesiones="{{ json_encode($sesiones) }}"
        data-ag-map-centro="{{ json_encode($centro) }}"
        data-ag-map-zoom="{{ $zoom }}"
    ></div>

    <ul class="ag-mapa-operativo__leyenda">
        <li class="ag-mapa-operativo__leyenda-item">
            <span class="ag-mapa-operativo__dot ag-mapa-operativo__dot--info" aria-hidden="true"></span>
            {{ __('seguridad.dashboard.mapa_leyenda_en_vuelo') }}
        </li>
        <li class="ag-mapa-operativo__leyenda-item">
            <span class="ag-mapa-operativo__dot ag-mapa-operativo__dot--warning" aria-hidden="true"></span>
            {{ __('seguridad.dashboard.mapa_leyenda_atencion') }}
        </li>
        <li class="ag-mapa-operativo__leyenda-item">
            <span class="ag-mapa-operativo__dot ag-mapa-operativo__dot--neutral" aria-hidden="true"></span>
            {{ __('seguridad.dashboard.mapa_leyenda_programado') }}
        </li>
        <li class="ag-mapa-operativo__leyenda-item">
            <span class="ag-mapa-operativo__dot ag-mapa-operativo__dot--success" aria-hidden="true"></span>
            {{ __('seguridad.dashboard.mapa_leyenda_completado') }}
        </li>
    </ul>
</div>
