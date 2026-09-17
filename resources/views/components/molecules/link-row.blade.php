{{--
    Molecule: link-row (17/9/2026 — sección "Vínculos" del arquetipo Detalle,
    primer consumidor `ordenes/show.blade.php`)
    Fila clicable que enlaza a OTRA pantalla del panel con información
    relacionada: ícono en círculo de color + título + meta corta (ya
    resuelta por el llamador, p. ej. un conteo) + flecha. Pensada para no
    duplicar esa información acá — el dato completo vive en la pantalla de
    destino, esto es solo el acceso ("3 trabajos — Ver órdenes de trabajo").
    Varias filas de este componente, apiladas, arman una sección sin ocupar
    el espacio de una tarjeta completa por vínculo.

    Sin lógica de negocio: no decide si el vínculo corresponde (el llamador
    ya resolvió permiso + conteo antes de pasarlo).

    Props:
    - href (requerido).
    - icon (requerido): ícono Material Symbols, ya elegido por el llamador.
    - title (requerido): ya traducido.
    - meta (nullable): texto corto a la derecha, ya formateado.
    - tone (success|warning|danger|info|neutral, default "neutral"): color
      del círculo del ícono — mismo set que `atoms/badge`.
--}}
@props([
    'href',
    'icon',
    'title',
    'meta' => null,
    'tone' => 'neutral',
])

<a href="{{ $href }}" {{ $attributes->class(['ag-link-row']) }}>
    <span class="ag-link-row__icon ag-link-row__icon--{{ $tone }}">
        <x-atoms.icon :name="$icon" size="sm" />
    </span>
    <span class="ag-link-row__title">{{ $title }}</span>
    @if ($meta !== null)
        <span class="ag-link-row__meta">{{ $meta }}</span>
    @endif
    <x-atoms.icon name="chevron_right" size="sm" class="ag-link-row__chevron" />
</a>
