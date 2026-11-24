<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Asignacion\Contratos\Eventos\OfertaVencida;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una oferta que venció sin respuesta le importa al proveedor: perdió la oportunidad y cuenta en su tasa de aceptación.
 */
final class ReglaOfertaVencida implements ReglaNotificacion
{
    public function evento(): string
    {
        return OfertaVencida::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof OfertaVencida) {
            throw new InvalidArgumentException('ReglaOfertaVencida solo atiende OfertaVencida.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::OfertaVencida,
            claveEvento: "oferta_vencida:{$evento->ofertaId}",
            parametros: [
                'solicitud' => $evento->solicitudId,
            ],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosDestinatarios),
        );
    }
}
