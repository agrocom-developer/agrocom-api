<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use DomainException;

/**
 * Pausar, cancelar o corregir una aplicación ya publicada exige decir por qué (pedido del dueño,
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

    /** Corregir una orden ya publicada (ADR 0022, adenda del 19/9/2026). */
    public static function paraCorregir(): self
    {
        return new self(Texto::de('operaciones.errores.motivo_correccion_requerido'));
    }
}
