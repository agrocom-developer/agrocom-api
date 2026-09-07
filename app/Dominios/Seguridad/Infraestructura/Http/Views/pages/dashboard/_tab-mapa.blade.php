{{--
    Parcial: pestaña "Mapa" — polígonos reales de `com_lotes.geometria`
    coloreados por el estado agregado de las sesiones de cada lote.

    No hay capa de puntos de sesión: ninguna tabla del esquema guarda la
    posición de una sesión (ver ArmarMapaOperativo). El estado se lee en el
    color del lote, no en un marcador inventado.

    Espera: $secciones['mapa'] = {lotes, centro, zoom}.
--}}
@php($mapa = $secciones['mapa'])

<div class="ag-dash__stack">
    <x-organisms.mapa-operativo
        :lotes="$mapa['lotes']"
        :centro="$mapa['centro']"
        :zoom="$mapa['zoom']"
    />
</div>
