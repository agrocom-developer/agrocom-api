<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Calificaciones\Contratos\Eventos\CalificacionRegistrada;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una calificación recibida le importa al proveedor (especificación §12, CalificacionRegistrada). La que el proveedor escribe sobre el cliente es privada: llega sin destinatarios y no avisa a nadie. La clave es la calificación.
 */
final class ReglaCalificacionRegistrada implements ReglaNotificacion
{
    public function evento(): string
    {
        return CalificacionRegistrada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof CalificacionRegistrada) {
            throw new InvalidArgumentException('ReglaCalificacionRegistrada solo atiende CalificacionRegistrada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::CalificacionRegistrada,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::CalificacionRegistrada, $evento->calificacionId),
            parametros: ['solicitud' => $evento->solicitudId, 'estrellas' => $evento->estrellas],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosDestinatarios),
        );
    }
}
