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
    - tone (success|warning|danger|info|distintivo-1|distintivo-2|
      distintivo-3|primary-2|neutral, default "neutral"): color del círculo
      del ícono. Relleno SÓLIDO (`-contrast-fill`) + ícono blanco (18/9/2026,
      pedido explícito del usuario — mismo criterio que `atoms/badge`/
      `molecules/stat-card`), salvo `neutral`, que se queda con el gris
      tenue de siempre (no es un estado real). Ícono en `md` (antes `sm`):
      contenedor de 2.5rem, mismo par tamaño-de-caja que ya usa
      `stat-card__icon-box`.
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
        <x-atoms.icon :name="$icon" size="md" />
    </span>
    <span class="ag-link-row__title">{{ $title }}</span>
    @if ($meta !== null)
        <span class="ag-link-row__meta">{{ $meta }}</span>
    @endif
    <x-atoms.icon name="chevron_right" size="sm" class="ag-link-row__chevron" />
</a>
