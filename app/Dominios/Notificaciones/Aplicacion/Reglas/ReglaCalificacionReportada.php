<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Calificaciones\Contratos\Eventos\CalificacionReportada;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Una calificación que el proveedor reporta espera la decisión de quien modera: el dueño y el encargado de operaciones (política de calificación). La clave es la calificación.
 */
final class ReglaCalificacionReportada implements ReglaNotificacion
{
    public function evento(): string
    {
        return CalificacionReportada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof CalificacionReportada) {
            throw new InvalidArgumentException('ReglaCalificacionReportada solo atiende CalificacionReportada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::CalificacionReportada,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::CalificacionReportada, $evento->calificacionId),
            parametros: ['solicitud' => $evento->solicitudId, 'proveedor' => $evento->proveedorNombre],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: [Destinatario::rol(RolDestinatario::Dueno), Destinatario::rol(RolDestinatario::EncargadoOperaciones)],
        );
    }
}
