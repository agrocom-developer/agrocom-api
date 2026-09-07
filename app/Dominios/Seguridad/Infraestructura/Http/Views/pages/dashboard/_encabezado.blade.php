{{--
    Parcial: encabezado del dashboard — título, fecha de hoy y rol activo.

    La fecha se formatea acá y no llega del servidor a propósito: es
    presentación pura (el mismo instante, en el idioma del panel), no un dato
    de negocio. Antes venía del mock como el string fijo "Viernes 28 de
    agosto", que a los dos días ya mentía.

    Espera: $rol (string|null) — nombre legible del rol activo.
--}}
<div class="ag-dash__header">
    <div class="ag-dash__heading">
        <h1 class="ag-dash__title">{{ __('seguridad.dashboard.titulo') }}</h1>
        <div class="ag-dash__subtitle-row">
            <p class="ag-dash__subtitle">
                {{ __('seguridad.dashboard.bajada', [
                    'fecha' => \Illuminate\Support\Str::ucfirst(now()->locale(app()->getLocale())->isoFormat('dddd D [de] MMMM')),
                ]) }}
            </p>
            @if ($rol)
                <span class="ag-dash__ventana-chip">
                    <x-atoms.icon name="badge" size="sm" />
                    <span>{{ __('seguridad.dashboard.viendo_como', ['rol' => $rol]) }}</span>
                </span>
            @endif
        </div>
    </div>
</div>
