{{--
    Molecule: section-head (sexta vuelta parte 2 — lenguaje visual de
    docs/ganadosoft-dashboard.html §2.1, traducido a tokens)
    Título de sector: barra lateral de color + rótulo uppercase chico +
    contador mono opcional a la derecha. Separa el dashboard en sectores
    (KPIs, distribución, etc.) — puramente presentación, sin lógica.

    Props:
    - title (requerido): rótulo del sector, ya traducido/resuelto.
    - count (nullable string|int): contador a la derecha, ya formateado.
    - accent (nullable, default null): tono de la barra lateral —
      success|warning|danger|alert|info|distintivo-1|distintivo-2|
      primary-2|null. `null` (default) mantiene el verde
      `--ag-color-primary` de siempre — NINGÚN consumidor existente cambia
      sin pasar `accent` explícito. Pensado para secciones de una misma
      pantalla que conviene distinguir de un vistazo (18/9/2026, pedido
      explícito del usuario) — no es un indicador de estado (no reemplaza
      `atoms/badge`/`state` de `stat-card`), así que en general no lleva
      `danger`/`alert`/`success` salvo que la sección realmente signifique
      eso (evitar la misma colisión "dos verdes por casualidad" que ya se
      documentó en `stat-card`).
      Excepción real, `ordenes/show.blade.php`: con 7 `section-head` en la
      misma pantalla (6 `form-section` + `progress-meter`) y solo 6 tonos
      sin carga de significado, el usuario prefirió usar 7 tonos
      distintos de los 8 antes que repetir uno — incluido `success` en
      "Datos de la orden", que sí comparte tono con el KPI "Aplicaciones"
      de esa misma pantalla, decisión suya explícita. Mapeo completo y por
      qué cada sección tiene el color que tiene: ver el comentario en
      `ordenes/show.blade.php` justo antes del primer `form-section`.
--}}
@props([
    'title',
    'count' => null,
    'accent' => null,
])

<div
    {{ $attributes->class(['ag-section-head']) }}
    @if ($accent) data-accent="{{ $accent }}" @endif
>
    <span class="ag-section-head__bar" aria-hidden="true"></span>
    <h2 class="ag-section-head__title">{{ $title }}</h2>
    @if ($count !== null)
        <span class="ag-section-head__count">{{ $count }}</span>
    @endif
</div>
