<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Compartido\Contratos\Eventos\EstadoDeAprobacionCambiado;
use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Cambió el estado de aprobación de un proveedor, piloto o dron
 * (ADR 0032; especificación §12: `ProveedorAprobado`, `ProveedorSuspendido`).
 *
 * - Aprobación, rechazo, suspensión, rehabilitación y baja: le importan a quien
 *   es el sujeto y al titular de su proveedor. Se les avisa a las cuentas que el
 *   emisor declara en `usuariosDestinatarios`.
 * - Reenvío a revisión: le importa a quien aprueba (el administrador), no a
 *   quien reenvió.
 *
 * La clave del hecho lleva el id del historial: un mismo sujeto puede pasar por
 * aprobado, suspendido y rehabilitado muchas veces y cada cambio es un aviso.
 */
final class ReglaEstadoDeAprobacionCambiado implements ReglaNotificacion
{
    public function evento(): string
    {
        return EstadoDeAprobacionCambiado::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof EstadoDeAprobacionCambiado) {
            throw new InvalidArgumentException('ReglaEstadoDeAprobacionCambiado solo atiende EstadoDeAprobacionCambiado.');
        }

        $tipo = match ($evento->accion) {
            AccionAprobacion::Aprobar => TipoNotificacion::SujetoAprobado,
            AccionAprobacion::Rehabilitar => TipoNotificacion::SujetoRehabilitado,
            AccionAprobacion::Rechazar => TipoNotificacion::SujetoRechazado,
            AccionAprobacion::Suspender => TipoNotificacion::SujetoSuspendido,
            AccionAprobacion::DarDeBaja => TipoNotificacion::SujetoDadoDeBaja,
            AccionAprobacion::Reenviar => TipoNotificacion::SujetoReenviado,
        };

        $destinatarios = $evento->accion === AccionAprobacion::Reenviar
            ? [Destinatario::rol(RolDestinatario::Administrador)]
            : array_map(static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId), $evento->usuariosDestinatarios);

        return new NotificacionArmada(
            tipo: $tipo,
            claveEvento: "{$tipo->value}:{$evento->sujetoTipo}-{$evento->sujetoId}:{$evento->historialId}",
            parametros: [
                'sujeto' => mb_strtolower(Texto::de('aprobacion.sujeto.'.$evento->sujetoTipo)),
                'nombre' => $evento->nombre,
                'motivo' => $evento->motivo ?? '',
            ],
            recurso: RecursoNotificable::from($evento->sujetoTipo),
            recursoId: $evento->sujetoId,
            destinatarios: $destinatarios,
        );
    }
}
