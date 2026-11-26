<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Proveedores\Contratos\Eventos\ProveedorRegistrado;
use InvalidArgumentException;

/**
 * Un proveedor nuevo espera revisión (ADR 0032, punto 1): le importa a quien
 * aprueba, el administrador. Mide también el «tiempo de
 * aprobación de un proveedor» de las métricas (especificación §14).
 */
final class ReglaProveedorRegistrado implements ReglaNotificacion
{
    public function evento(): string
    {
        return ProveedorRegistrado::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof ProveedorRegistrado) {
            throw new InvalidArgumentException('ReglaProveedorRegistrado solo atiende ProveedorRegistrado.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::ProveedorRegistrado,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::ProveedorRegistrado, $evento->proveedorId),
            parametros: [
                'tipo' => mb_strtolower(Texto::de('proveedores.tipo.'.$evento->tipo)),
                'nombre' => $evento->razonSocial,
            ],
            recurso: RecursoNotificable::Proveedor,
            recursoId: $evento->proveedorId,
            destinatarios: [
                Destinatario::rol(RolDestinatario::Administrador),
            ],
        );
    }
}
