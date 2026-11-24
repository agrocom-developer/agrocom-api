<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Solicitudes\Contratos\Eventos\PublicacionDeClienteLimitada;
use InvalidArgumentException;

/**
 * La limitación de la publicación le importa al cliente y a quien la revisa, el dueño y el encargado de operaciones (política de calificación). La clave lleva el cliente y el promedio.
 */
final class ReglaPublicacionLimitada implements ReglaNotificacion
{
    public function evento(): string
    {
        return PublicacionDeClienteLimitada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof PublicacionDeClienteLimitada) {
            throw new InvalidArgumentException('ReglaPublicacionLimitada solo atiende PublicacionDeClienteLimitada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::PublicacionLimitada,
            claveEvento: "publicacion_limitada:{$evento->clienteId}:{$evento->promedio}",
            parametros: ['cliente' => $evento->clienteNombre, 'promedio' => $evento->promedio],
            recurso: RecursoNotificable::Cliente,
            recursoId: $evento->clienteId,
            destinatarios: [
                Destinatario::usuario($evento->clienteUsuarioId),
                Destinatario::rol(RolDestinatario::Dueno),
                Destinatario::rol(RolDestinatario::EncargadoOperaciones),
            ],
        );
    }
}
