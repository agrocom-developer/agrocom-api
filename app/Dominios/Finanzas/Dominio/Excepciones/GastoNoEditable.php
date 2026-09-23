<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Finanzas\Dominio\PoliticaEdicionGasto;
use RuntimeException;

/**
 * Se intentó editar un gasto cuya rendición asociada ya está `presentada` o
 * `aprobada` — ya recalculó y congeló su `monto` sumando este gasto
 * ({@see PoliticaEdicionGasto}). Un gasto sin rendición, o con una rendición
 * todavía `abierta`, sí se corrige (tarea 134).
 */
final class GastoNoEditable extends RuntimeException
{
    public static function porRendicion(int $rendicionId, string $estado): self
    {
        return new self(Texto::de('finanzas.errores.gasto_no_editable', [
            'rendicion_id' => $rendicionId,
            'estado' => $estado,
        ]));
    }
}
