<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Disputas\Contratos\Eventos\DisputaApelada;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una apelación le importa a la contraparte y a soporte: la disputa vuelve a revisión (política de disputas, «Proceso»). La clave es la disputa: se apela una sola vez.
 */
final class ReglaDisputaApelada implements ReglaNotificacion
{
    public function evento(): string
    {
        return DisputaApelada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof DisputaApelada) {
            throw new InvalidArgumentException('ReglaDisputaApelada solo atiende DisputaApelada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::DisputaApelada,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::DisputaApelada, $evento->disputaId),
            parametros: ['solicitud' => $evento->solicitudId],
            recurso: RecursoNotificable::Disputa,
            recursoId: $evento->disputaId,
            destinatarios: [
                ...array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosContraparte),
                Destinatario::rol(RolDestinatario::Dueno),
                Destinatario::rol(RolDestinatario::EncargadoOperaciones),
            ],
        );
    }
}
