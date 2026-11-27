<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoAbierto;
use InvalidArgumentException;

/**
 * Un reclamo abierto le importa a la contraparte, que responde en 48 horas, y a soporte (especificación §12, ReclamoAbierto). La clave es el reclamo: hay una por solicitud.
 */
final class ReglaReclamoAbierto implements ReglaNotificacion
{
    public function evento(): string
    {
        return ReclamoAbierto::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof ReclamoAbierto) {
            throw new InvalidArgumentException('ReglaReclamoAbierto solo atiende ReclamoAbierto.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::ReclamoAbierto,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::ReclamoAbierto, $evento->reclamoId),
            parametros: [
                'solicitud' => $evento->solicitudId,
                'motivo' => mb_strtolower(Texto::de('reclamos.motivo.'.$evento->motivo)),
            ],
            recurso: RecursoNotificable::Reclamo,
            recursoId: $evento->reclamoId,
            destinatarios: [
                ...array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosContraparte),
                Destinatario::rol(RolDestinatario::Administrador),
            ],
        );
    }
}
