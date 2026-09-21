{{--
    Organism: page-header (tarea 31 — arquetipo formulario, §6.3/§5 de
    docs/diseno/guia_pantalla_panel.md)
    Cabecera común a las tres pantallas arquetipo del panel: h1 + bajada de
    una línea + slot de acciones a la derecha. Ocupa la franja completa
    superior de la pantalla y orquesta las acciones (`atoms/button`) que le
    pasa el llamador — por eso organism y no molecule, mismo criterio que
    `topbar`. Es el mismo markup que hoy usan ~40 pantallas del panel;
    dashboard (`pages/dashboard/_encabezado`) lo consume vía el slot `chip`
    para su indicador de rol activo — ninguna pantalla nueva debe volver a
    armar este markup a mano.

    Props:
    - title (requerido): título de la pantalla, ya traducido. Se renderiza
      como el único `h1` de la página.
    - subtitle (nullable): bajada de una línea, ya traducida.

    Slots con nombre:
    - actions: botones de la cabecera (`atoms/button`), un único sólido
      (`variant="primary"`) y el resto `outline` (regla fija, §5).
    - chip (opcional): indicador corto en línea junto al subtítulo (p. ej.
      "Viendo como X rol") — arma su propia pastilla/tono, este componente
      solo le da un lugar al lado del subtítulo. Sin `chip`, el subtítulo
      se ve exactamente igual que antes (compatible con las ~40 pantallas
      que no lo usan).
--}}
@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class(['ag-page-header']) }}>
    <div class="ag-page-header__heading">
        <h1 class="ag-page-header__title">{{ $title }}</h1>

        @if ($subtitle)
            @if (isset($chip))
                <div class="ag-page-header__subtitle-row">
                    <p class="ag-page-header__subtitle">{{ $subtitle }}</p>
                    {{ $chip }}
                </div>
            @else
                <p class="ag-page-header__subtitle">{{ $subtitle }}</p>
            @endif
        @endif
    </div>

    @isset($actions)
        <div class="ag-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
