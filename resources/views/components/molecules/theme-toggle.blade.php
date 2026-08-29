{{--
    Molecule: theme-toggle (docs/diseno/sistema_diseno_panel.md §4.2)
    Comportamiento (presentación pura, sin backend): resuelve y aplica
    `data-bs-theme` en <html> y despacha `agrocom:theme-changed` — ver
    resources/js/molecules/theme-toggle.js. Persiste "claro"/"oscuro" contra
    `sec_user_preferencia.tema`; "sistema" NO se persiste ahí (el enum del
    backend es Claro|Oscuro únicamente) — se guarda solo en localStorage del
    navegador (ver el JS para el detalle de esa decisión).

    Se usa más de una vez en la misma página a propósito (topbar Y pie del
    sidebar en desktop; solo topbar visible cuando el sidebar está cerrado en
    mobile) — el JS sincroniza TODAS las instancias en cada click.

    Auditoría visual externa, obs. #9: pasa de un switch de 2 estados
    (claro/oscuro) a un segmented control de 3 celdas (claro/oscuro/sistema).
    Un switch binario (`role="switch"`, un aria-checked bool) no representa 3
    opciones mutuamente excluyentes — el control pasa a ser un
    `role="radiogroup"` con 3 `role="radio"` (un botón por celda, cada uno
    con su propio `aria-checked`), el patrón ARIA correcto para "un grupo,
    varias opciones exclusivas". El estado ACTIVO ya no se puede leer solo de
    `[data-bs-theme]` (que solo conoce claro/oscuro resueltos, nunca
    "sistema"): la celda activa se pinta desde `[data-ag-theme-preference]`
    en <html>, un atributo propio que sí distingue las 3 preferencias — ver
    theme-toggle.css y panel-shell.blade.php (valor inicial servidor).

    Props:
    - showLabel (bool, default false): agrega el texto "Tema claro"/"Tema
      oscuro"/"Tema del sistema" junto al switch (traducidos vía `ui.theme.*`).
      El texto es puramente decorativo/redundante para lectores de pantalla
      (el estado real lo comunica `aria-checked` de cada radio) — por eso va
      `aria-hidden`.
--}}
@props([
    'showLabel' => false,
])

<div {{ $attributes->class(['ag-theme-toggle']) }}>
    <div class="ag-theme-toggle__control" data-ag-theme-toggle role="radiogroup" aria-label="{{ __('ui.theme.toggle') }}">
        <button
            type="button"
            class="ag-theme-toggle__cell ag-theme-toggle__cell--light"
            data-ag-theme-option="light"
            role="radio"
            aria-checked="false"
            aria-label="{{ __('ui.theme.light') }}"
        >
            <x-atoms.icon name="light_mode" size="sm" class="ag-theme-toggle__icon" />
        </button>
        <button
            type="button"
            class="ag-theme-toggle__cell ag-theme-toggle__cell--dark"
            data-ag-theme-option="dark"
            role="radio"
            aria-checked="false"
            aria-label="{{ __('ui.theme.dark') }}"
        >
            <x-atoms.icon name="dark_mode" size="sm" class="ag-theme-toggle__icon" />
        </button>
        <button
            type="button"
            class="ag-theme-toggle__cell ag-theme-toggle__cell--system"
            data-ag-theme-option="system"
            role="radio"
            aria-checked="false"
            aria-label="{{ __('ui.theme.system') }}"
        >
            <x-atoms.icon name="contrast" size="sm" class="ag-theme-toggle__icon" />
        </button>
    </div>

    @if ($showLabel)
        <span class="ag-theme-toggle__label" aria-hidden="true">
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--light">{{ __('ui.theme.light') }}</span>
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--dark">{{ __('ui.theme.dark') }}</span>
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--system">{{ __('ui.theme.system') }}</span>
        </span>
    @endif
</div>
