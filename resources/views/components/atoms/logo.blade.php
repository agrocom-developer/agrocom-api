{{--
    Atom: logo
    Alterna entre `logo-light.jpeg`/`logo-dark.jpeg` según el tema activo,
    resuelto en CSS puro (ver resources/css/components/logo.css) — no decide
    el tema, solo se pinta distinto según el `[data-bs-theme]` ya resuelto en
    el <html> por quien arma la página.

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
        src="{{ asset('logo-light.jpeg') }}"
        alt="{{ $resolvedAlt }}"
        class="ag-logo__image ag-logo__image--light"
    >
    <img
        src="{{ asset('logo-dark.jpeg') }}"
        alt="{{ $resolvedAlt }}"
        class="ag-logo__image ag-logo__image--dark"
    >
</span>
