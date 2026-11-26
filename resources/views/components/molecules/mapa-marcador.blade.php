{{--
    Molecule: mapa-marcador
    Mapa satelital con un marcador arrastrable. Un click o arrastre llena los
    inputs de latitud y longitud (que siguen siendo los que viajan en el POST);
    escribir en ellos mueve el marcador. Funciona sin mapa: los inputs mandan.

    Props:
    - latId, lngId: id de los inputs de latitud y longitud de la misma página.
    - label (nullable): título del mapa, ya traducido.
    - help (nullable): ayuda bajo el mapa, ya traducida.
--}}
@props(['latId', 'lngId', 'label' => null, 'help' => null])
<div
    {{ $attributes->class(['ag-mapa-marcador']) }}
    data-ag-mapa-marcador
    data-ag-mapa-lat="{{ $latId }}"
    data-ag-mapa-lng="{{ $lngId }}"
>
    @if ($label)
        <span class="ag-file-field__label">{{ $label }}</span>
    @endif
    <div class="ag-mapa-marcador__lienzo" data-ag-mapa-lienzo role="application" aria-label="{{ $label }}"></div>
    @if ($help)
        <p class="ag-mapa-marcador__ayuda">{{ $help }}</p>
    @endif
</div>
