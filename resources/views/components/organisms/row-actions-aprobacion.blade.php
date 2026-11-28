{{--
    Organism: row-actions-aprobacion
    Las acciones de fila de un sujeto con la máquina de estados de aprobación
    (proveedor, piloto, dron; ADR 0032): Ver, Aprobar, las transiciones que el estado
    y el permiso admiten y, al final, Eliminar (dar de baja). Arma sus propios
    `<form>` y modales de confirmación (los mismos de la ficha, `_modales-aprobacion`)
    FUERA de `organisms/row-actions` y delega en él el orden y el formato.

    No decide ninguna transición: `$acciones` ya llega filtrada por estado, permiso y
    separación de funciones desde el controlador, igual que en la ficha. Tras resolver,
    el servidor vuelve al listado (campo oculto `desde`).

    Props:
    - acciones (list<AccionAprobacion>), estado (EstadoAprobacion).
    - nombre (string): del sujeto, para el título del modal.
    - url-resolver (string): destino del POST.
    - modal-base (string): prefijo único del id de los modales (uno por fila).
    - href-ver (string|null), label-ver (string|null): enlace a la ficha. Sin `href-ver` no hay «Ver».
--}}
@props([
    'acciones' => [],
    'estado',
    'nombre',
    'urlResolver',
    'modalBase',
    'hrefVer' => null,
    'labelVer' => null,
])

@php
    use App\Dominios\Compartido\Dominio\AccionAprobacion;

    $iconos = [
        'aprobar' => 'check_circle',
        'rechazar' => 'cancel',
        'suspender' => 'pause_circle',
        'rehabilitar' => 'lock_open',
        'dar_de_baja' => 'delete',
    ];
    $modal = fn (AccionAprobacion $accion): string => $modalBase.'-'.$accion->value.'-modal';
    $aprobar = collect($acciones)->first(fn (AccionAprobacion $a) => $a === AccionAprobacion::Aprobar);
    $baja = collect($acciones)->first(fn (AccionAprobacion $a) => $a === AccionAprobacion::DarDeBaja);
    $transiciones = collect($acciones)->reject(fn (AccionAprobacion $a) => in_array($a, [AccionAprobacion::Aprobar, AccionAprobacion::DarDeBaja], true));
@endphp

@if ($acciones !== [])
    @include('proveedores::pages._modales-aprobacion', [
        'acciones' => $acciones,
        'estado' => $estado,
        'nombre' => $nombre,
        'urlResolver' => $urlResolver,
        'modalBase' => $modalBase,
        'desde' => 'listado',
    ])
@endif

<x-organisms.row-actions>
    @if ($hrefVer)
        <x-slot:ver>
            <x-molecules.row-action kind="ver" :href="$hrefVer" icon="visibility" :label="$labelVer ?? __('ui.tabla.ver')" />
        </x-slot:ver>
    @endif

    @if ($aprobar)
        <x-slot:aprobar>
            <x-molecules.row-action kind="aprobar" :modal="$modal($aprobar)" :icon="$iconos['aprobar']" :label="__('aprobacion.accion.aprobar')" />
        </x-slot:aprobar>
    @endif

    @if ($transiciones->isNotEmpty())
        <x-slot:transiciones>
            @foreach ($transiciones as $accion)
                <x-molecules.row-action kind="transicion" :tone="$accion->estadoDestino()->tono()" :modal="$modal($accion)" :icon="$iconos[$accion->value] ?? 'sync_alt'" :label="__('aprobacion.accion.'.$accion->value)" />
            @endforeach
        </x-slot:transiciones>
    @endif

    @if ($baja)
        <x-slot:eliminar>
            <x-molecules.row-action kind="eliminar" :modal="$modal($baja)" :icon="$iconos['dar_de_baja']" :label="__('aprobacion.accion.dar_de_baja')" />
        </x-slot:eliminar>
    @endif
</x-organisms.row-actions>
