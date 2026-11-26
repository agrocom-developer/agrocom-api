<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\FormatoNotificacion;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Operaciones\Contratos\Eventos\TrabajoCerrado;
use InvalidArgumentException;

/**
 * Trabajo cerrado en campo → le importa al administrador, el único rol de panel
 * que queda con alcance sobre la operación (ADR 0046). Lleva al trabajo.
 */
final class ReglaTrabajoCerrado implements ReglaNotificacion
{
    public function evento(): string
    {
        return TrabajoCerrado::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof TrabajoCerrado) {
            throw new InvalidArgumentException('ReglaTrabajoCerrado solo atiende TrabajoCerrado.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::TrabajoCerrado,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::TrabajoCerrado, $evento->trabajoId),
            parametros: [
                'solicitud' => $evento->solicitudId,
                'hectareas' => FormatoNotificacion::hectareas($evento->hectareas),
            ],
            recurso: RecursoNotificable::Trabajo,
            recursoId: $evento->trabajoId,
            destinatarios: [
                Destinatario::rol(RolDestinatario::Administrador),
            ],
        );
    }
}
