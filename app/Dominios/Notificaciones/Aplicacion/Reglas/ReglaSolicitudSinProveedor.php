<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Asignacion\Contratos\Eventos\SolicitudSinProveedor;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Se agotaron las rondas sin proveedor (especificación §12, SolicitudSinProveedor): se avisa al cliente, que elige qué hacer, y al equipo de operaciones, que puede asignar a mano.
 */
final class ReglaSolicitudSinProveedor implements ReglaNotificacion
{
    public function evento(): string
    {
        return SolicitudSinProveedor::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof SolicitudSinProveedor) {
            throw new InvalidArgumentException('ReglaSolicitudSinProveedor solo atiende SolicitudSinProveedor.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::SolicitudSinProveedor,
            claveEvento: "solicitud_sin_proveedor:{$evento->solicitudId}:{$evento->ciclo}",
            parametros: [
                'solicitud' => $evento->solicitudId,
            ],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: [Destinatario::usuario($evento->clienteUsuarioId), Destinatario::rol(RolDestinatario::EncargadoOperaciones)],
        );
    }
}
