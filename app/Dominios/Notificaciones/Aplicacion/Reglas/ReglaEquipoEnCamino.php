<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Asignacion\Contratos\Eventos\EquipoEnCamino;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * El equipo salió hacia el lote: le importa al cliente, que sabe que el servicio empieza pronto (especificación §12, EquipoEnCamino). La clave es la solicitud y el equipo: un servicio sale en camino una vez por asignación.
 */
final class ReglaEquipoEnCamino implements ReglaNotificacion
{
    public function evento(): string
    {
        return EquipoEnCamino::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof EquipoEnCamino) {
            throw new InvalidArgumentException('ReglaEquipoEnCamino solo atiende EquipoEnCamino.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::EquipoEnCamino,
            claveEvento: "equipo_en_camino:{$evento->solicitudId}:{$evento->equipoId}",
            parametros: ['solicitud' => $evento->solicitudId],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: [Destinatario::usuario($evento->clienteUsuarioId)],
        );
    }
}
