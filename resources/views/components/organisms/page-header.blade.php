{{--
    Organism: page-header (tarea 31 — arquetipo formulario, §6.3/§5 de
    docs/diseno/guia_pantalla_panel.md)
    Cabecera común a las tres pantallas arquetipo del panel: h1 + bajada de
    una línea + slot de acciones a la derecha. Ocupa la franja completa
    superior de la pantalla y orquesta las acciones (`atoms/button`) que le
    pasa el llamador — por eso organism y no molecule, mismo criterio que
    `topbar`. Reemplaza el markup que hoy repiten `ag-dash__header` (dashboard)
    y `ag-organizacion__intro` (organizacion); esos dos NO se migran en esta
    tarea (fuera de alcance), pero ninguna pantalla nueva debe volver a armar
    ese markup a mano.

    Props:
    - title (requerido): título de la pantalla, ya traducido. Se renderiza
      como el único `h1` de la página.
    - subtitle (nullable): bajada de una línea, ya traducida.

    Slot con nombre:
    - actions: botones de la cabecera (`atoms/button`), un único sólido
      (`variant="primary"`) y el resto `outline` (regla fija, §5).
--}}
@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class(['ag-page-header']) }}>
    <div class="ag-page-header__heading">
        <h1 class="ag-page-header__title">{{ $title }}</h1>

        @if ($subtitle)
            <p class="ag-page-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="ag-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
