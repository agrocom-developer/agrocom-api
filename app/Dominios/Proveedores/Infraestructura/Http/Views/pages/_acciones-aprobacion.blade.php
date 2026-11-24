{{--
    Partial: botones de aprobación de la cabecera de una ficha (ADR 0032).
    Un botón por acción que el rol activo puede hacer sobre el estado actual del
    sujeto; cada uno abre su modal (ver `_modales-aprobacion`). Solo uno es
    sólido: aprobar o rehabilitar. El resto es `<tono>-outline`, mismo criterio
    de «el color del estado de llegada» que el resto del panel.

    Espera:
    - $acciones (list<AccionAprobacion>): las acciones ya filtradas por estado y permiso.
    - $modalBase (string): prefijo del id de los modales de esta ficha.
--}}
@php
    $variantePorAccion = [
        'aprobar' => 'primary',
        'rehabilitar' => 'primary',
        'rechazar' => 'danger-outline',
        'suspender' => 'warning-outline',
        'dar_de_baja' => 'danger-outline',
    ];
    $iconoPorAccion = [
        'aprobar' => 'verified',
        'rehabilitar' => 'restore',
        'rechazar' => 'cancel',
        'suspender' => 'pause_circle',
        'dar_de_baja' => 'block',
    ];
@endphp
@foreach ($acciones as $accion)
    <x-atoms.button
        type="button"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalBase }}-{{ $accion->value }}-modal"
        :variant="$variantePorAccion[$accion->value] ?? 'outline'"
        :icon="$iconoPorAccion[$accion->value] ?? null"
    >
        {{ __('aprobacion.accion.'.$accion->value) }}
    </x-atoms.button>
@endforeach
