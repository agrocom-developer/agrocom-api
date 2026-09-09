{{--
    Organism: mapa-operativo — mapa satelital (Leaflet + Esri World Imagery)
    con los polígonos reales de `com_lotes.geometria`, coloreados por el
    estado agregado de las sesiones de cada lote.

    Sin lógica de negocio: recibe el FeatureCollection ya armado por
    `ArmarMapaOperativo` y lo serializa a `data-*`;
    resources/js/organisms/dashboard-map.js (import() dinámico, ver app.js)
    lo lee e instancia Leaflet.

    La capa de sesiones georreferenciadas se retiró en la tarea 67: ninguna
    tabla del esquema guarda la posición de una sesión — `com_lotes.geometria`
    es la única columna geográfica del modelo. El mock dibujaba puntos
    inventados; el estado ahora se lee en el color del lote.

    Pantalla completa (tarea 79): mismo mecanismo que el editor de perímetro
    del lote (`shared/mapa-pantalla-completa.js`, clases compartidas
    `ag-mapa-marco`/`ag-mapa-barra`/`ag-mapa-accion` de
    `components/mapa-pantalla-completa.css`) — acá sin la barra de siete
    acciones del editor: este mapa es de solo lectura, un botón alcanza. La
    leyenda viaja DENTRO del marco para seguir viéndose en pantalla completa.

    Props:
    - lotes (requerido): FeatureCollection GeoJSON de polígonos.
    - centro (requerido): {lat, lng} — el promedio real de los vértices
      cargados, no una coordenada fija.
    - zoom (opcional, default 12).
--}}
@props([
    'lotes',
    'centro',
    'zoom' => 12,
])

<div {{ $attributes->class(['ag-mapa-operativo']) }}>
    <div class="ag-mapa-marco" data-ag-mapa-marco>
        <div class="ag-mapa-barra">
            <button
                type="button"
                class="ag-mapa-accion ag-mapa-accion--pantalla-completa"
                data-ag-mapa-boton-pantalla-completa
                title="{{ __('seguridad.dashboard.mapa_pantalla_completa') }}"
                aria-label="{{ __('seguridad.dashboard.mapa_pantalla_completa') }}"
                aria-pressed="false"
                data-ag-mapa-entrar="{{ __('seguridad.dashboard.mapa_pantalla_completa') }}"
                data-ag-mapa-salir="{{ __('seguridad.dashboard.mapa_salir_pantalla_completa') }}"
            >
                <x-atoms.icon name="fullscreen" data-ag-mapa-icono-pantalla-completa />
            </button>
        </div>

        <div
            class="ag-mapa-operativo__lienzo"
            data-ag-map
            data-ag-map-lotes="{{ json_encode($lotes) }}"
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
</div>
