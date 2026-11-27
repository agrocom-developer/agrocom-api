<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoResuelto;
use InvalidArgumentException;

/**
 * Un reclamo resuelto o retirado le importa a ambas partes, con el resultado y el reembolso (especificación §12, ReclamoResuelto). La clave lleva la ronda: la resolución de una apelación es otro aviso.
 */
final class ReglaReclamoResuelto implements ReglaNotificacion
{
    public function evento(): string
    {
        return ReclamoResuelto::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof ReclamoResuelto) {
            throw new InvalidArgumentException('ReglaReclamoResuelto solo atiende ReclamoResuelto.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::ReclamoResuelto,
            claveEvento: "reclamo_resuelto:{$evento->reclamoId}:{$evento->ronda}",
            parametros: [
                'solicitud' => $evento->solicitudId,
                'resultado' => mb_strtolower(Texto::de('reclamos.resultado.'.$evento->resultado)),
                'monto' => $evento->montoReembolso,
            ],
            recurso: RecursoNotificable::Reclamo,
            recursoId: $evento->reclamoId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), array_values(array_unique($evento->usuariosDestinatarios))),
        );
    }
}
