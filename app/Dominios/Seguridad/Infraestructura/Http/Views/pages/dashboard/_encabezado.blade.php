{{--
    Parcial: encabezado del dashboard — título, fecha de hoy y rol activo.

    La fecha se formatea acá y no llega del servidor a propósito: es
    presentación pura (el mismo instante, en el idioma del panel), no un dato
    de negocio. Antes venía del mock como el string fijo "Viernes 28 de
    agosto", que a los dos días ya mentía.

    Envuelve `organisms/page-header` (mismo markup que el resto del panel,
    ver su docblock) en vez del `.ag-dash__header` propio que tenía antes —
    el slot `chip` es exactamente para este caso.

    Espera: $rol (string|null) — nombre legible del rol activo.
--}}
<x-organisms.page-header
    :title="__('seguridad.dashboard.titulo')"
    :subtitle="__('seguridad.dashboard.bajada', [
        'fecha' => \Illuminate\Support\Str::ucfirst(now()->locale(app()->getLocale())->isoFormat('dddd D [de] MMMM')),
    ])"
>
    @if ($rol)
        <x-slot:chip>
            <span class="ag-dash__ventana-chip">
                <x-atoms.icon name="badge" size="sm" />
                <span>{{ __('seguridad.dashboard.viendo_como', ['rol' => $rol]) }}</span>
            </span>
        </x-slot:chip>
    @endif
</x-organisms.page-header>
