<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Disputas\Contratos\Eventos\DisputaAbierta;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una disputa abierta le importa a la contraparte, que responde en 48 horas, y a soporte (especificación §12, DisputaAbierta). La clave es la disputa: hay una por solicitud.
 */
final class ReglaDisputaAbierta implements ReglaNotificacion
{
    public function evento(): string
    {
        return DisputaAbierta::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof DisputaAbierta) {
            throw new InvalidArgumentException('ReglaDisputaAbierta solo atiende DisputaAbierta.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::DisputaAbierta,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::DisputaAbierta, $evento->disputaId),
            parametros: [
                'solicitud' => $evento->solicitudId,
                'motivo' => mb_strtolower(Texto::de('disputas.motivo.'.$evento->motivo)),
            ],
            recurso: RecursoNotificable::Disputa,
            recursoId: $evento->disputaId,
            destinatarios: [
                ...array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosContraparte),
                Destinatario::rol(RolDestinatario::Administrador),
            ],
        );
    }
}
