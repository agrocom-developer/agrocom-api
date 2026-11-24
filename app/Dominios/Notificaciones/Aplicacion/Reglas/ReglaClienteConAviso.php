<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Solicitudes\Contratos\Eventos\ClienteAvisadoPorCalificacion;
use InvalidArgumentException;

/**
 * El aviso por promedio bajo le importa al cliente (política de calificación): si el promedio no se recupera, se limita su publicación. La clave es el cliente: hay un aviso vigente a la vez.
 */
final class ReglaClienteConAviso implements ReglaNotificacion
{
    public function evento(): string
    {
        return ClienteAvisadoPorCalificacion::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof ClienteAvisadoPorCalificacion) {
            throw new InvalidArgumentException('ReglaClienteConAviso solo atiende ClienteAvisadoPorCalificacion.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::ClienteConAviso,
            claveEvento: "cliente_con_aviso:{$evento->clienteId}:{$evento->promedio}",
            parametros: ['promedio' => $evento->promedio],
            recurso: RecursoNotificable::Cliente,
            recursoId: $evento->clienteId,
            destinatarios: [Destinatario::usuario($evento->clienteUsuarioId)],
        );
    }
}
