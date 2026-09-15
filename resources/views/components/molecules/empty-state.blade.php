{{--
    Molecule: empty-state (`.ag-empty-state`, docs/diseno/guia_pantalla_panel.md
    §5.1, caso 2): la pantalla o un bloque suyo no tiene NADA que mostrar,
    más allá de cualquier filtro. Tercera aparición de este patrón
    (`dashboard/_sin-secciones`, `personas/desempeno`) — se promueve al
    catálogo en vez de copiar la clase BEM una cuarta vez.

    No es `alert-strip`: esa es la pieza para "el filtro elegido no trae
    nada" (banda delgada, en línea con el contenido). Esta es para "no hay
    nada que filtrar todavía" (tarjeta centrada, reemplaza al bloque
    entero, incluida la barra de filtros).

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
