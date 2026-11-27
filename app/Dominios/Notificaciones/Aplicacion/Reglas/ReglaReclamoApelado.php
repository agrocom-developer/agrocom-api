<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoApelado;
use InvalidArgumentException;

/**
 * Una apelación le importa a la contraparte y a soporte: el reclamo vuelve a revisión (política de reclamos, «Proceso»). La clave es el reclamo: se apela una sola vez.
 */
final class ReglaReclamoApelado implements ReglaNotificacion
{
    public function evento(): string
    {
        return ReclamoApelado::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof ReclamoApelado) {
            throw new InvalidArgumentException('ReglaReclamoApelado solo atiende ReclamoApelado.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::ReclamoApelado,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::ReclamoApelado, $evento->reclamoId),
            parametros: ['solicitud' => $evento->solicitudId],
            recurso: RecursoNotificable::Reclamo,
            recursoId: $evento->reclamoId,
            destinatarios: [
                ...array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosContraparte),
                Destinatario::rol(RolDestinatario::Administrador),
            ],
        );
    }
}
