{{--
    Parcial: encabezado del dashboard — título, fecha de hoy y rol activo.

    La fecha se formatea acá y no llega del servidor a propósito: es
    presentación pura (el mismo instante, en el idioma del panel), no un dato
    de negocio. Antes venía del mock como el string fijo "Viernes 28 de
    agosto", que a los dos días ya mentía.

    Envuelve `organisms/page-header` (mismo markup que el resto del panel,
    ver su docblock) en vez del `.ag-dash__header` propio que tenía antes —
    el slot `chip` es exactamente para este caso.

    Espera: $rol (string|null) — nombre legible del rol activo; $tecnico
    (bool) — el rol es técnico (tarea 139) y lleva su propio título y bajada;
    $puedeVerComo (bool) — el rol activo tiene `seguridad.usuario.ver_como`:
    junto al chip aparece el botón «Cambiar vista», que abre el modal
    `_modal-cambiar-vista` (incluido aparte, a nivel de `.ag-dash`, para no
    anidar un modal Bootstrap dentro de este header).
--}}
<x-organisms.page-header
    :title="__($tecnico ? 'seguridad.dashboard.titulo_tecnico' : 'seguridad.dashboard.titulo')"
    :subtitle="__($tecnico ? 'seguridad.dashboard.bajada_tecnica' : 'seguridad.dashboard.bajada', [
        'fecha' => \Illuminate\Support\Str::ucfirst(now()->locale(app()->getLocale())->isoFormat('dddd D [de] MMMM')),
    ])"
>
    @if ($rol || $puedeVerComo)
        <x-slot:chip>
            @if ($rol)
                <span class="ag-dash__ventana-chip">
                    <x-atoms.icon name="badge" size="sm" />
                    <span>{{ __('seguridad.dashboard.viendo_como', ['rol' => $rol]) }}</span>
                </span>
            @endif

            @if ($puedeVerComo)
                <x-atoms.button
                    variant="outline"
                    size="sm"
                    icon="swap_horiz"
                    data-bs-toggle="modal"
                    data-bs-target="#ag-cambiar-vista-modal"
                >
                    {{ __('seguridad.vista_como.selector_boton') }}
                </x-atoms.button>
            @endif
        </x-slot:chip>
    @endif
</x-organisms.page-header>
