{{--
    Molecule: section-head (sexta vuelta parte 2 — lenguaje visual de
    docs/ganadosoft-dashboard.html §2.1, traducido a tokens)
    Título de sector: barra lateral de color + rótulo uppercase chico +
    contador mono opcional a la derecha. Separa el dashboard en sectores
    (KPIs, distribución, etc.) — puramente presentación, sin lógica.

    Props:
    - title (requerido): rótulo del sector, ya traducido/resuelto.
    - count (nullable string|int): contador a la derecha, ya formateado.
--}}
@props([
    'title',
    'count' => null,
])

<div {{ $attributes->class(['ag-section-head']) }}>
    <span class="ag-section-head__bar" aria-hidden="true"></span>
    <h2 class="ag-section-head__title">{{ $title }}</h2>
    @if ($count !== null)
        <span class="ag-section-head__count">{{ $count }}</span>
    @endif
</div>
