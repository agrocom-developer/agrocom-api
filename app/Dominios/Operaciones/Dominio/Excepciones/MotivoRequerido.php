<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use DomainException;

/**
 * Pausar o cancelar una aplicación exige decir por qué (pedido del dueño,
 * 18/9/2026, ADR 0022): con esa información el operador y el dueño deciden
 * cómo seguir, y queda registrada. La regla vive acá y no solo en el
 * `FormRequest`, así la respeta cualquier otro punto de entrada.
 */
final class MotivoRequerido extends DomainException
{
    public static function paraPausar(): self
    {
        return new self(Texto::de('operaciones.errores.motivo_pausa_requerido'));
    }

    public static function paraCancelar(): self
    {
        return new self(Texto::de('operaciones.errores.motivo_cancelacion_requerido'));
    }
}
