<?php

namespace App\Dominios\Operaciones\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Evento de dominio (ADR 0003, regla 2; tarea 141, ADR 0025), mismo molde que
 * {@see SesionValidada}: se anuncia que se confirmó una Orden de Trabajo (una
 * tanda de equipos sobre una orden vigente). DTO de primitivos.
 *
 * `$equipoTrabajoIds` son los equipos asignados a la tanda: quien lo escucha
 * (`Notificaciones`) avisa a sus integrantes vigentes sin leer
 * `ope_orden_trabajo_equipos`. `$hectareas` es el total repartido en la
 * tanda, decimal exacto (invariante 6).
 *
 * Lo dispara `Aplicacion/CrearOrdenTrabajo::ejecutar()` DESPUÉS de que la
 * transacción que crea la cabecera, sus equipos y sus trabajos se confirmó.
 * No pasa por ninguna máquina de estados: crear una tanda no es una
 * transición (la orden de aplicación sigue `vigente`).
 *
 * Oyente real: `Notificaciones\Infraestructura\NotificacionesServiceProvider`
 * (regla `ReglaOrdenTrabajoCreada`).
 */
final readonly class OrdenTrabajoCreada implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $equipoTrabajoIds
     */
    public function __construct(
        public int $ordenTrabajoId,
        public int $ordenId,
        public int $nroAplicacion,
        public array $equipoTrabajoIds,
        public string $hectareas,
    ) {}
}
