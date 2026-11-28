{{--
    Parcial: encabezado del tablero — título y fecha de hoy.

    La fecha se formatea acá y no llega del servidor a propósito: es
    presentación pura (el mismo instante, en el idioma del panel), no un dato
    de negocio.

    Envuelve `organisms/page-header` (mismo markup que el resto del panel,
    ver su docblock). El tablero no ofrece cambiar de vista: lo que se ve sale
    de los permisos del rol de la sesión.
--}}
<x-organisms.page-header
    :title="__('seguridad.dashboard.titulo')"
    :subtitle="__('seguridad.dashboard.bajada', [
        'fecha' => \Illuminate\Support\Str::ucfirst(now()->locale(app()->getLocale())->isoFormat('dddd D [de] MMMM')),
    ])"
/>
