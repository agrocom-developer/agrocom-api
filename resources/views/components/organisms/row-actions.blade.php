{{--
    Organism: row-actions (`.ag-row-actions`)
    Envuelve las acciones de una fila de tabla (los `<x-atoms.button>` que
    cada página ya arma detrás de `@puede`/`@if` de permiso) y colapsa las
    que no entran a un menú "⋮" — nunca más de 2 acciones sueltas en una
    celda (pedido explícito del usuario, 16/9/2026: simetría de ancho de
    columna entre páginas, sin importar cuántos estados tenga cada una), y
    en mobile están TODAS en el menú.

    Es organism (no molecule) por el mismo criterio que `topbar`: orquesta
    botones y tiene JS propio (dropdown de Bootstrap + el title automático
    de resources/js/organisms/row-actions.js).

    El slot se renderiza DOS VECES a propósito — una vez en `.ag-row-actions__visible`
    (CSS oculta según breakpoint cuáles se ven, siempre con ícono + texto,
    nunca solo-ícono), otra dentro del menú `.ag-row-actions__menu` (los que
    no entraron). Es la única forma de no duplicar en el Blade de cada
    página los `@puede`/`@if` de permiso de cada acción: el llamador arma
    sus botones UNA vez, en el slot, y este componente decide qué se ve
    dónde por CSS, no por PHP.

    No recibe props: no necesita saber CUÁNTAS acciones tiene ni de qué tipo
    son — eso es exactamente lo que el llamador ya resuelve con sus propios
    `@puede`. El componente no conoce rutas, modelos ni permisos (DIP).
--}}
<div class="ag-row-actions">
    <div class="ag-row-actions__visible">
        {{ $slot }}
    </div>

    <div class="dropdown ag-row-actions__more">
        <button
            type="button"
            class="ag-button ag-button--outline ag-button--sm ag-row-actions__more-btn"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="{{ __('ui.tabla.mas_acciones') }}"
        >
            <x-atoms.icon name="more_vert" size="sm" />
        </button>

        <div class="dropdown-menu dropdown-menu-end ag-row-actions__menu">
            {{ $slot }}
        </div>
    </div>
</div>
