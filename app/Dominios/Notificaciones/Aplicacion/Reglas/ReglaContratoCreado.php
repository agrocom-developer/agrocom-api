<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Comercial\Contratos\Eventos\ContratoCreado;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\FormatoNotificacion;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * Contrato creado → le importa a operaciones: el encargado tiene que saber que
 * hay un contrato nuevo para empezar a emitir sus órdenes (primera cadena de
 * la tarea 141, ADR 0025). Lleva al contrato.
 */
final class ReglaContratoCreado implements ReglaNotificacion
{
    public function evento(): string
    {
        return ContratoCreado::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof ContratoCreado) {
            throw new InvalidArgumentException('ReglaContratoCreado solo atiende ContratoCreado.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::ContratoCreado,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::ContratoCreado, $evento->contratoId),
            parametros: [
                'cliente' => $evento->cliente,
                'hectareas' => FormatoNotificacion::hectareas($evento->hectareas),
            ],
            recurso: RecursoNotificable::Contrato,
            recursoId: $evento->contratoId,
            destinatarios: [Destinatario::rol(RolDestinatario::EncargadoOperaciones)],
        );
    }
}
