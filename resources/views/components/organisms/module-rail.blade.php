{{--
    Organism: module-rail (quinta vuelta — nivel 1 del layout, maqueta 4a)
    Riel vertical de módulos: logo arriba, un botón-ícono de 46px por módulo
    (title + aria-label con el nombre — el label visible vive en el nivel 2),
    engranaje de configuración al pie. Superficie oliva oscura EN AMBOS temas
    (tokens --ag-color-bg-rail*, constantes — chrome de marca).

    Solo recibe los módulos que el rol activo puede ver: el árbol llega
    filtrado por ObtenerMenuPorRolActivo y normalizado por panel-layout —
    este organism no consulta sec_* ni resuelve rutas (ADR 0008).

    74px de ancho en escritorio, 64px en tablet, oculto en móvil (ahí los
    módulos viven en organisms/module-drawer) — ver module-rail.css.

    Props:
    - modulos (list): `{label, icono, items: [{href, active…}], active}` ya
      normalizados. El href del botón es el del primer ítem con ruta.
    - configuracionHref (nullable string): destino del engranaje.
--}}
@props([
    'modulos' => [],
    'configuracionHref' => null,
])

<nav class="ag-rail" aria-label="{{ __('ui.rail.aria') }}">
    <a class="ag-rail__logo" href="{{ route('panel.dashboard') }}" aria-label="{{ __('ui.logo.alt') }}">
        <img src="{{ asset('logo.png') }}" alt="" aria-hidden="true">
    </a>

    <ul class="ag-rail__list">
        @foreach ($modulos as $modulo)
            @php
                $destino = collect($modulo['items'])->firstWhere('href')['href'] ?? null;
                $nombre = __($modulo['label']);
            @endphp
            <li>
                @if ($destino)
                    <a
                        href="{{ $destino }}"
                        class="ag-rail__btn {{ $modulo['active'] ? 'is-active' : '' }}"
                        title="{{ $nombre }}"
                        aria-label="{{ $nombre }}"
                        @if ($modulo['active']) aria-current="true" @endif
                    >
                        <x-atoms.icon :name="$modulo['icono']" />
                    </a>
                @else
                    <button
                        type="button"
                        class="ag-rail__btn {{ $modulo['active'] ? 'is-active' : '' }}"
                        title="{{ $nombre }}"
                        aria-label="{{ $nombre }}"
                        @if ($modulo['active']) aria-current="true" @endif
                    >
                        <x-atoms.icon :name="$modulo['icono']" />
                    </button>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($configuracionHref)
        <a
            href="{{ $configuracionHref }}"
            class="ag-rail__btn ag-rail__config"
            title="{{ __('ui.rail.configuracion') }}"
            aria-label="{{ __('ui.rail.configuracion') }}"
        >
            <x-atoms.icon name="settings" />
        </a>
    @endif
</nav>
