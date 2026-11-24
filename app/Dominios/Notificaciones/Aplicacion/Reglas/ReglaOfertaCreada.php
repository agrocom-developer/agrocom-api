<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Asignacion\Contratos\Eventos\OfertaCreada;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una oferta nueva le importa al proveedor (especificación §12): se le avisa a su titular y a sus pilotos, con la cuenta regresiva del vencimiento. La clave es la oferta: reintentar el evento no repite el aviso.
 */
final class ReglaOfertaCreada implements ReglaNotificacion
{
    public function evento(): string
    {
        return OfertaCreada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof OfertaCreada) {
            throw new InvalidArgumentException('ReglaOfertaCreada solo atiende OfertaCreada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::OfertaCreada,
            claveEvento: "oferta_creada:{$evento->ofertaId}",
            parametros: [
                'solicitud' => $evento->solicitudId,
                'hectareas' => number_format((float) $evento->hectareas, 2, ',', '.'),
                'hora' => (new \DateTimeImmutable($evento->venceEn))->format('H:i'),
            ],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosDestinatarios),
        );
    }
}
