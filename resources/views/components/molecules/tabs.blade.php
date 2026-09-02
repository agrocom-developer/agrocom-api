{{--
    Molecule: tabs (tarea 31 — arquetipo formulario)
    Nivel 3 del layout (pestañas dentro del contenido, nunca las pone
    `panel-layout`). El CSS (`resources/css/components/tabs.css`) ya existía
    desde el rediseño del dashboard; faltaba el componente — el dashboard
    sigue armando su nav a mano (fuera de alcance de esta tarea, se migra
    cuando le toque). Mecanismo: `tab` nativo de Bootstrap 5.3
    (`data-bs-toggle="tab"`, JS ya cargado) — este componente solo pinta el
    nav; el `tab-content`/`tab-pane` de cada panel lo arma la página, porque
    su contenido varía demasiado para generalizarlo acá (mismo criterio que
    "una lista de X es casi siempre de la página, no del catálogo", §2 de la
    guía de pantalla).

    Props:
    - items (requerido): array de `['id' => 'ag-tab-x', 'label' => '...', 'active' => bool]`,
      labels ya traducidos por el llamador. `id` es el id del `tab-pane`
      correspondiente (sin el `#`).
    - ariaLabel (nullable): rótulo del `role="tablist"` para lectores de pantalla.
--}}
@props([
    'items' => [],
    'ariaLabel' => null,
])

<div {{ $attributes->class(['ag-tabs']) }} role="tablist" @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif>
    @foreach ($items as $item)
        @php $active = $item['active'] ?? false; @endphp
        <button
            type="button"
            class="ag-tabs__tab {{ $active ? 'active' : '' }}"
            data-bs-toggle="tab"
            data-bs-target="#{{ $item['id'] }}"
            role="tab"
            aria-controls="{{ $item['id'] }}"
            aria-selected="{{ $active ? 'true' : 'false' }}"
        >
            {{ $item['label'] }}
        </button>
    @endforeach
</div>
