{{--
    Template: legal-layout (documentos legales públicos: términos y privacidad)
    Cáscara mínima y pública (sin sesión, sin menú): el aviso de borrador
    arriba, una cabecera con la marca, el cambio de tema y el regreso al
    inicio, el contenido en una columna de lectura y un pie con el otro
    documento legal. Es el hermano de `auth-layout` para texto largo: la
    pantalla dividida con foto de `auth-layout` no sirve para leer un
    documento.

    Props:
    - volverLabel (string): texto del enlace de regreso, ya traducido.
    - pieAriaLabel (string): etiqueta accesible del pie, ya traducida.

    Slots:
    - banner (opcional): franja de aviso sobre la cabecera (el borrador).
    - footer (opcional): contenido del pie (el enlace al otro documento).
    - default: el documento, dentro de <main>.
--}}
@props([
    'volverLabel',
    'pieAriaLabel',
])

<div class="ag-legal">
    @isset($banner)
        <div class="ag-legal__banner">{{ $banner }}</div>
    @endisset

    <header class="ag-legal__header">
        <x-atoms.logo size="md" />

        <span class="ag-legal__header-end">
            <a href="{{ url('/') }}" class="ag-legal__volver">
                <x-atoms.icon name="arrow_back" size="sm" />
                <span>{{ $volverLabel }}</span>
            </a>
            <x-molecules.theme-toggle />
        </span>
    </header>

    <main class="ag-legal__main">
        {{ $slot }}
    </main>

    <footer class="ag-legal__footer">
        <span>{{ __('ui.footer.copyright', ['year' => date('Y')]) }}</span>

        @isset($footer)
            <nav class="ag-legal__footer-nav" aria-label="{{ $pieAriaLabel }}">{{ $footer }}</nav>
        @endisset
    </footer>
</div>
