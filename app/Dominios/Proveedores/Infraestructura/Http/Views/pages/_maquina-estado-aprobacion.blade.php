{{--
    Partial: máquina de estados de aprobación de una ficha (ADR 0032), dibujada
    con `molecules/step-arrow` en vez de una fila de botones. Cada paso al que
    el rol activo puede pasar abre el modal de confirmación de su acción (ver
    `_modales-aprobacion`); el resto queda bloqueado o sin permiso, con su pista.

    Lo que se dibuja sale de `TransicionesAprobacion` (la tabla de la propia
    máquina) y de las acciones que el servidor ya filtró por estado, permiso y
    separación de funciones: no decide ninguna transición. «Reenviar» es del
    solicitante, así que «Pendiente» no se ofrece como destino desde «Rechazado».

    Espera:
    - $acciones (list<AccionAprobacion>): las acciones que el rol activo puede hacer ahora.
    - $estado (EstadoAprobacion): estado actual del sujeto.
    - $modalBase (string): prefijo del id de los modales (el de `_modales-aprobacion`).
--}}
@php
    use App\Dominios\Compartido\Dominio\EstadoAprobacion;
    use App\Dominios\Compartido\Dominio\TransicionesAprobacion;
    use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;

    $modales = collect($acciones)->mapWithKeys(
        fn ($accion) => [$accion->estadoDestino()->value => $modalBase.'-'.$accion->value.'-modal'],
    )->all();

    $recorridos = match ($estado) {
        EstadoAprobacion::Pendiente => [],
        EstadoAprobacion::Suspendido => [EstadoAprobacion::Pendiente, EstadoAprobacion::Aprobado],
        default => [EstadoAprobacion::Pendiente],
    };

    $tonos = collect(EstadoAprobacion::cases())->mapWithKeys(fn (EstadoAprobacion $e) => [$e->value => $e->tono()])->all();

    $pasos = PasosDeEstado::armar(
        ruta: [EstadoAprobacion::Pendiente, EstadoAprobacion::Aprobado, EstadoAprobacion::Suspendido, EstadoAprobacion::Rechazado, EstadoAprobacion::Baja],
        actual: $estado,
        permitida: fn (EstadoAprobacion $desde, EstadoAprobacion $hacia): bool => $hacia !== EstadoAprobacion::Pendiente
            && TransicionesAprobacion::permitida($desde, $hacia),
        tonos: $tonos,
        claveEtiqueta: 'aprobacion.estado',
        prefijoModal: $modalBase,
        puedeCambiar: $modales !== [],
        recorridos: $recorridos,
        iconos: ['rechazado' => 'cancel', 'baja' => 'block', 'suspendido' => 'pause_circle'],
        puedeIrA: fn (EstadoAprobacion $destino): bool => isset($modales[$destino->value]),
        modales: $modales,
    );
@endphp
<x-molecules.step-arrow
    :steps="$pasos"
    :label="__('aprobacion.maquina_aria')"
    :help="PasosDeEstado::ayuda($pasos, 'aprobacion.ayuda')"
/>
