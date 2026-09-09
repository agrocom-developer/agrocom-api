{{--
    Molecule: theme-toggle (docs/diseno/sistema_diseno_panel.md §4.2, §12)
    Switch binario claro/oscuro: resuelve y aplica `data-bs-theme` en
    `<html>`, persiste contra `sec_user_preferencia.tema` y despacha
    `agrocom:theme-changed` — ver resources/js/molecules/theme-toggle.js.

    Se usa más de una vez en la misma página a propósito (topbar y header de
    auth-layout) — un click en CUALQUIER instancia cambia el único
    `data-bs-theme` global y, como el ícono visible depende solo de ese
    atributo vía CSS, todas quedan sincronizadas sin código de
    sincronización explícito.

    Octava vuelta (29/8/2026, pedido explícito): de un segmented control de
    3 celdas (claro/oscuro/sistema — auditoría visual externa obs. #9, ver
    docs/diseno/sistema_diseno_panel.md §7.7) a un solo botón que ALTERNA
    entre las dos preferencias que persiste el backend. Se retira "sistema"
    por pedido directo (no una re-auditoría): simplifica de vuelta el modelo
    a exactamente lo que `sec_user_preferencia.tema` sabe representar, sin
    la indirección `data-ag-theme-preference` vs. `data-bs-theme` que
    "sistema" obligaba (con "sistema" resuelto a oscuro, ambos atributos
    podían diferir; sin "sistema" son siempre el mismo valor — se retira
    `data-ag-theme-preference`, ver panel-shell.blade.php).

    Motivo del cambio de segmented control a botón único: el contenedor tipo
    "pastilla" (`--ag-color-bg`) quedaba descolgado del chrome que lo rodea
    (`--ag-color-bg-chrome`) desde que ambos tokens dejaron de compartir
    superficie (séptima vuelta, tema oscuro) — se veía como un parche de
    color aparte en el topbar. Ahora es un ícono suelto, mismo lenguaje
    visual que el resto de acciones de ícono del topbar
    (`.ag-topbar__icon-btn`: transparente, wash sutil en hover) — ver
    theme-toggle.css.

    El ícono visible es el de la PRÓXIMA preferencia (a la que se pasa al
    presionar), no la actual: el botón se lee como una acción ("tocá esto
    para pasar a oscuro"), no como un indicador de estado. Los dos
    `<x-atoms.icon>` quedan en el DOM a la vez — CSS muestra solo uno según
    `[data-bs-theme]` en `<html>`.

    Props:
    - showLabel (bool, default false): agrega el texto "Tema claro"/"Tema
      oscuro" junto al ícono (traducido vía `ui.theme.*`), mismo criterio de
      "próxima preferencia" que el ícono. Puramente decorativo
      (`aria-hidden`) — el estado real lo comunica el cambio real de tema,
      no este texto.
--}}
@props([
    'showLabel' => false,
])

<button
    type="button"
    {{ $attributes->class(['ag-theme-toggle']) }}
    data-ag-theme-toggle
    aria-label="{{ __('ui.theme.toggle') }}"
>
    <x-atoms.icon name="dark_mode" size="sm" class="ag-theme-toggle__icon ag-theme-toggle__icon--to-dark" />
    <x-atoms.icon name="light_mode" size="sm" class="ag-theme-toggle__icon ag-theme-toggle__icon--to-light" />

    @if ($showLabel)
        <span class="ag-theme-toggle__label" aria-hidden="true">
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--to-dark">{{ __('ui.theme.dark') }}</span>
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--to-light">{{ __('ui.theme.light') }}</span>
        </span>
    @endif
</button>
