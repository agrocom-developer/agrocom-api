{{--
    Atom: logo
    Un único archivo (`public/logo.png`, fondo transparente) — ya no alterna
    entre versiones "light"/"dark" por tema: eran dos JPEG con el mismo
    isotipo sobre fondo sólido horneado (blanco/negro) porque JPEG no soporta
    transparencia; se reprocesó a PNG con canal alfa real (limitación que
    documentaba `logo.css`, ya resuelta), así que el mismo archivo se ve bien
    sobre cualquier superficie/tema sin necesitar una variante por color de
    fondo.

    Props:
    - alt (string|null): por defecto, clave de traducción `ui.logo.alt`.
    - size (sm|md|lg, default "md").
--}}
@props([
    'alt' => null,
    'size' => 'md',
])

@php($resolvedAlt = $alt ?? __('ui.logo.alt'))

<span {{ $attributes->class(['ag-logo', "ag-logo--{$size}"]) }}>
    <img
        src="{{ asset('logo.png') }}"
        alt="{{ $resolvedAlt }}"
        class="ag-logo__image"
    >
</span>
