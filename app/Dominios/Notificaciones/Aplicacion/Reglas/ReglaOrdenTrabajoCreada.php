<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\FormatoNotificacion;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Operaciones\Contratos\Eventos\OrdenTrabajoCreada;
use InvalidArgumentException;

/**
 * Orden de trabajo creada → le importa al equipo asignado: sus integrantes
 * vigentes se enteran de que tienen trabajo nuevo. La orden de APLICACIÓN no
 * tiene equipo cuando nace (el reparto lo confirma la orden de trabajo), así
 * que el aviso al equipo sale de esta tanda y no de la alta de la orden.
 * Lleva a la orden de trabajo; un rol que no puede abrirla (piloto, ayudante)
 * aterriza en su tablero.
 */
final class ReglaOrdenTrabajoCreada implements ReglaNotificacion
{
    public function evento(): string
    {
        return OrdenTrabajoCreada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof OrdenTrabajoCreada) {
            throw new InvalidArgumentException('ReglaOrdenTrabajoCreada solo atiende OrdenTrabajoCreada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::OrdenTrabajoCreada,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::OrdenTrabajoCreada, $evento->ordenTrabajoId),
            parametros: [
                'orden' => $evento->ordenId,
                'aplicacion' => $evento->nroAplicacion,
                'hectareas' => FormatoNotificacion::hectareas($evento->hectareas),
            ],
            recurso: RecursoNotificable::OrdenTrabajo,
            recursoId: $evento->ordenTrabajoId,
            destinatarios: array_map(
                static fn (int $equipoTrabajoId): Destinatario => Destinatario::equipo($equipoTrabajoId),
                $evento->equipoTrabajoIds,
            ),
        );
    }
}
