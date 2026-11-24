<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Asignacion\Contratos\Eventos\OfertaRetirada;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una oferta retirada (otro aceptó, el cliente canceló o un administrador asignó a mano) le importa al proveedor que la tenía viva o aceptada.
 */
final class ReglaOfertaRetirada implements ReglaNotificacion
{
    public function evento(): string
    {
        return OfertaRetirada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof OfertaRetirada) {
            throw new InvalidArgumentException('ReglaOfertaRetirada solo atiende OfertaRetirada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::OfertaRetirada,
            claveEvento: "oferta_retirada:{$evento->ofertaId}",
            parametros: [
                'solicitud' => $evento->solicitudId,
                'motivo' => mb_strtolower(Texto::de('asignacion.motivo_retiro.'.$evento->motivo)),
            ],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosDestinatarios),
        );
    }
}
