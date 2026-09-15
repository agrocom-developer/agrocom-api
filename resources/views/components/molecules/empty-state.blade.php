{{--
    Molecule: empty-state (`.ag-empty-state`, docs/diseno/guia_pantalla_panel.md
    §5.1): cubre las DOS situaciones de "no hay filas que mostrar" — la
    pantalla no tiene NADA todavía, o el filtro/búsqueda aplicado no trae
    nada. Hasta el 15/9/2026 el segundo caso usaba `alert-strip` (banda
    delgada en línea); pedido directo del usuario: una sola pieza para
    ambos, tarjeta centrada siempre, cambiando solo `icon`/`title`/`detail`
    (p. ej. `icon="search_off"` + "Sin resultados para este filtro" contra
    el `icon` propio de la pantalla + "Todavía no hay X" del caso vacío
    real). El criterio para elegir cuál mensaje usar sigue siendo el mismo
    `$hayFiltrosActivos` que gatea la barra de filtros.

    Props:
    - icon (string, requerido): ícono Material Symbols — mismo criterio
      semántico que ya tenga el `alert-strip` de esa pantalla, no uno nuevo.
    - title (string, requerido): ya resuelto por quien lo usa (vía __()),
      nunca un literal ni una clave sin resolver acá.
    - detail (string, requerido): igual, ya resuelto — causa probable y qué
      hace falta para que deje de estar vacío.

    Slots: action (opcional) — botón/link secundario debajo del detalle.
--}}
@props([
    'icon',
    'title',
    'detail',
])

<div {{ $attributes->class(['ag-empty-state']) }}>
    <x-atoms.icon :name="$icon" size="lg" class="ag-empty-state__icon" />
    <h2 class="ag-empty-state__title">{{ $title }}</h2>
    <p class="ag-empty-state__detail">{{ $detail }}</p>

    @isset($action)
        <div class="ag-empty-state__action">{{ $action }}</div>
    @endisset
</div>
