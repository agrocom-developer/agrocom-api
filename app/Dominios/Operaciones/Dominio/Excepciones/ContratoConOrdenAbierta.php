<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use DomainException;

/**
 * El contrato ya tiene una aplicación en curso (`emitida`, `vigente` o
 * `pausada`): la siguiente solo se emite cuando esa se cierra o se cancela
 * (pedido del dueño, 18/9/2026, ADR 0022). La verifica
 * {@see NumeracionAplicaciones::siguiente()} al numerar, y la respalda un
 * índice único parcial en la base contra dos altas simultáneas.
 */
final class ContratoConOrdenAbierta extends DomainException
{
    public static function paraContrato(int $contratoId, int $nroAplicacion): self
    {
        return new self(Texto::de('operaciones.errores.contrato_con_orden_abierta', [
            'id' => $contratoId,
            'nro' => $nroAplicacion,
        ]));
    }

    /** Dos altas simultáneas: la otra ganó la carrera y todavía no sabemos su número. */
    public static function porAltaSimultanea(int $contratoId): self
    {
        return new self(Texto::de('operaciones.errores.contrato_con_orden_abierta_simultanea', ['id' => $contratoId]));
    }
}
