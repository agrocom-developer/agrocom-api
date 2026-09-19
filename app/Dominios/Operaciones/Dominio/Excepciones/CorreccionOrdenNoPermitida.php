<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrden;
use DomainException;

/**
 * Se quiso corregir en una orden algo que su estado actual ya no permite (ADR
 * 0022, adenda del 19/9/2026): cambiar el insumo o la dosis cuando la orden ya
 * tiene trabajos. La regla es {@see PoliticaEdicionOrden}.
 */
final class CorreccionOrdenNoPermitida extends DomainException
{
    public static function insumoConTrabajos(): self
    {
        return new self(Texto::de('operaciones.errores.correccion_insumo_con_trabajos'));
    }
}
