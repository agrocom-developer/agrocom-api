<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Asignacion\Contratos\Eventos\ProveedorSancionado;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * El motor aplicó una sanción a un proveedor (ADR 0038, política de calificación
 * y de cancelación): se le avisa a él con el motivo y, si dejó una revisión de
 * suspensión pendiente, también a quien decide, el dueño y el encargado de
 * operaciones. La clave lleva el id de la sanción.
 */
final class ReglaProveedorSancionado implements ReglaNotificacion
{
    public function evento(): string
    {
        return ProveedorSancionado::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof ProveedorSancionado) {
            throw new InvalidArgumentException('ReglaProveedorSancionado solo atiende ProveedorSancionado.');
        }

        $destinatarios = array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosDestinatarios);

        if ($evento->tipo === 'revision_suspension') {
            $destinatarios[] = Destinatario::rol(RolDestinatario::Dueno);
            $destinatarios[] = Destinatario::rol(RolDestinatario::EncargadoOperaciones);
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::ProveedorSancionado,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::ProveedorSancionado, $evento->sancionId),
            parametros: [
                'proveedor' => $evento->proveedorNombre,
                'sancion' => mb_strtolower(Texto::de('asignacion.sanciones.tipo.'.$evento->tipo)),
                'causa' => mb_strtolower(Texto::de('asignacion.sanciones.causa.'.$evento->causa)),
            ],
            recurso: RecursoNotificable::Proveedor,
            recursoId: $evento->proveedorId,
            destinatarios: $destinatarios,
        );
    }
}
