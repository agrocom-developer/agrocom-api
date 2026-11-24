<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Disputas\Contratos\Eventos\DisputaResuelta;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una disputa resuelta o retirada le importa a ambas partes, con el resultado y el reembolso (especificación §12, DisputaResuelta). La clave lleva la ronda: la resolución de una apelación es otro aviso.
 */
final class ReglaDisputaResuelta implements ReglaNotificacion
{
    public function evento(): string
    {
        return DisputaResuelta::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof DisputaResuelta) {
            throw new InvalidArgumentException('ReglaDisputaResuelta solo atiende DisputaResuelta.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::DisputaResuelta,
            claveEvento: "disputa_resuelta:{$evento->disputaId}:{$evento->ronda}",
            parametros: [
                'solicitud' => $evento->solicitudId,
                'resultado' => mb_strtolower(Texto::de('disputas.resultado.'.$evento->resultado)),
                'monto' => $evento->montoReembolso,
            ],
            recurso: RecursoNotificable::Disputa,
            recursoId: $evento->disputaId,
            destinatarios: array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), array_values(array_unique($evento->usuariosDestinatarios))),
        );
    }
}
