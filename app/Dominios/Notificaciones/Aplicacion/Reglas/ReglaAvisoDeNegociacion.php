<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Los hechos de la negociación (nueva solicitud abierta, oferta o contraoferta, adjudicación,
 * inicio, avance y fin del servicio, mensaje, oferta vencida; `negociacion-contrato.md` §7). El evento ya
 * trae el tipo de aviso, la clave que lo hace idempotente y los destinatarios filtrados por sus
 * preferencias: la regla solo lo traduce. Una solicitud avisa a una cuenta una sola vez por hecho.
 */
final class ReglaAvisoDeNegociacion implements ReglaNotificacion
{
    public function evento(): string
    {
        return AvisoDeNegociacion::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof AvisoDeNegociacion) {
            throw new InvalidArgumentException('ReglaAvisoDeNegociacion solo atiende AvisoDeNegociacion.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::from($evento->tipo),
            claveEvento: $evento->claveEvento,
            parametros: $evento->parametros,
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuarios),
        );
    }
}
