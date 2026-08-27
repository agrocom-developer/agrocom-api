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
    click, y la celda activa de cada instancia se pinta vía CSS a partir del
    único atributo global `[data-bs-theme]`, nunca de estado propio del
    componente.

    Rediseño (HU-02, rediseño de login, tercera vuelta): el switch
    track+thumb deslizante se reemplaza por una pastilla ("segmented") con
    DOS celdas fijas, una por ícono (`light_mode`/`dark_mode`) — la celda del
    tema activo queda con fondo elevado + color de acento; la inactiva, en
    muted. Es un cambio puramente visual:
    - El elemento raíz sigue siendo UN solo `<button data-ag-theme-toggle
      role="switch" aria-checked="...">` — no dos botones independientes con
      `aria-pressed` propio. Con dos controles separados, el estado "activo"
      quedaría ambiguo para un lector de pantalla (¿cuál de los dos truthy
      representa el tema actual?) y el JS existente tendría que reescribirse
      para alternar dos elementos en vez de uno. Envolver ambos íconos en el
      mismo `<button>` (igual que el track anterior, que también tenía dos
      `<x-atoms.icon>` adentro) preserva la semántica de "un control, dos
      estados posibles" con el mínimo cambio de contrato.
    - `resources/js/molecules/theme-toggle.js` NO cambia: solo lee/escribe
      `data-bs-theme` en <html> y sincroniza `aria-checked` de
      `[data-ag-theme-toggle]` — ninguna de esas dos cosas depende del
      marcado interno del botón.

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
        class="ag-theme-toggle__control"
        data-ag-theme-toggle
        role="switch"
        aria-checked="false"
        aria-label="{{ __('ui.theme.toggle') }}"
    >
        <span class="ag-theme-toggle__cell ag-theme-toggle__cell--light" aria-hidden="true">
            <x-atoms.icon name="light_mode" size="sm" class="ag-theme-toggle__icon" />
        </span>
        <span class="ag-theme-toggle__cell ag-theme-toggle__cell--dark" aria-hidden="true">
            <x-atoms.icon name="dark_mode" size="sm" class="ag-theme-toggle__icon" />
        </span>
    </button>

    @if ($showLabel)
        <span class="ag-theme-toggle__label" aria-hidden="true">
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--light">{{ __('ui.theme.light') }}</span>
            <span class="ag-theme-toggle__label-text ag-theme-toggle__label-text--dark">{{ __('ui.theme.dark') }}</span>
        </span>
    @endif
</div>
