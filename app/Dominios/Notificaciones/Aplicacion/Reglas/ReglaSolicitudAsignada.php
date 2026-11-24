<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Asignacion\Contratos\Eventos\SolicitudAsignada;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una solicitud asignada le importa al cliente (especificación §12, SolicitudAsignada) y al proveedor que la tomó. La clave lleva el ciclo: una reasignación es otro aviso.
 */
final class ReglaSolicitudAsignada implements ReglaNotificacion
{
    public function evento(): string
    {
        return SolicitudAsignada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof SolicitudAsignada) {
            throw new InvalidArgumentException('ReglaSolicitudAsignada solo atiende SolicitudAsignada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::SolicitudAsignada,
            claveEvento: "solicitud_asignada:{$evento->solicitudId}:{$evento->ciclo}",
            parametros: [
                'solicitud' => $evento->solicitudId,
                'proveedor' => $evento->proveedorNombre,
            ],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: array_map(
                static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId),
                array_values(array_unique([...$evento->usuariosDestinatarios, $evento->clienteUsuarioId])),
            ),
        );
    }
}
