{{--
    Molecule: theme-toggle (docs/diseno/sistema_diseno_panel.md §4.2)
    Comportamiento (presentación pura, sin backend): alterna `data-bs-theme`
    en <html> y despacha `agrocom:theme-changed` — ver
    resources/js/molecules/theme-toggle.js. NO persiste la preferencia: quien
    la use (un componente Livewire, más adelante) escucha el evento y hace el
    POST contra `sec_user_preferencia.tema`.

    Se usa más de una vez en la misma página a propósito (topbar Y pie del
    sidebar en desktop; solo topbar visible cuando el sidebar está cerrado en
    mobile) — el JS sincroniza `aria-checked` de TODAS las instancias en cada
    click, y el thumb/ícono de cada instancia se pinta vía CSS a partir del
    único atributo global `[data-bs-theme]`, nunca de estado propio del
    componente.

    Props:
    - showLabel (bool, default false): agrega el texto "Tema claro"/"Tema
      oscuro" junto al switch (ambos ya traducidos vía `ui.theme.*`, ninguno
      literal). El texto es puramente decorativo/redundante para lectores de
      pantalla (el estado real lo comunica `aria-checked`) — por eso va
      `aria-hidden`.
--}}
@props([
    'showLabel' => false,
])

<div {{ $attributes->class(['ag-theme-toggle']) }}>
    <button
        type="button"
        class="ag-theme-toggle__track"
        data-ag-theme-toggle
        role="switch"
        aria-checked="false"
        aria-label="{{ __('ui.theme.toggle') }}"
    >
        <x-atoms.icon name="light_mode" size="sm" class="ag-theme-toggle__icon ag-theme-toggle__icon--sun" />
        <x-atoms.icon name="dark_mode" size="sm" class="ag-theme-toggle__icon ag-theme-toggle__icon--moon" />
        <span class="ag-theme-toggle__thumb" aria-hidden="true"></span>
    </button>

    @if ($showLabel)
        <span class="ag-theme-toggle__label" aria-hidden="true">
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--light">{{ __('ui.theme.light') }}</span>
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--dark">{{ __('ui.theme.dark') }}</span>
        </span>
    @endif
</div>
