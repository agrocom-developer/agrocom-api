<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use DomainException;

/**
 * El contrato ya agotó sus `aplicaciones_previstas` (pedido del dueño,
 * 18/9/2026, ADR 0022): no se pueden emitir más órdenes. Antes esto era solo
 * presentación (el select ocultaba opciones); ahora lo hace cumplir el
 * servidor — {@see NumeracionAplicaciones::siguiente()}.
 */
final class AplicacionesCompletas extends DomainException
{
    public static function paraContrato(int $contratoId, int $previstas): self
    {
        return new self(Texto::de('operaciones.errores.aplicaciones_completas', [
            'id' => $contratoId,
            'previstas' => $previstas,
        ]));
    }
}
