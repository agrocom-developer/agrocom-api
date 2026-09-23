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
 * Trabajo cerrado en campo → le importa a quien dirige y a quien planifica:
 * jefe de campo y encargado de operaciones. El dueño NO lo recibe (ADR 0025,
 * punto 10): es el aviso más frecuente (uno por equipo×lote) y su tablero ya
 * muestra el avance. Si algún día lo quisiera, es sumar
 * `Destinatario::rol(RolDestinatario::Dueno)` a esta lista. Lleva al trabajo.
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
                'orden' => $evento->ordenId,
                'aplicacion' => $evento->nroAplicacion,
                'hectareas' => FormatoNotificacion::hectareas($evento->hectareas),
            ],
            recurso: RecursoNotificable::Trabajo,
            recursoId: $evento->trabajoId,
            destinatarios: [
                Destinatario::rol(RolDestinatario::JefeCampo),
                Destinatario::rol(RolDestinatario::EncargadoOperaciones),
            ],
        );
    }
}
