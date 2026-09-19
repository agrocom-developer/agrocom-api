<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Edición en bloque de los lotes de una propiedad: al subir la cantidad
 * nacen lotes nuevos, y un lote no existe sin hectáreas. Si los lotes que ya
 * estaban tienen hectáreas distintas entre sí, el formulario deja el campo
 * vacío (no hay un valor común que proponer) y eso solo es válido mientras no
 * haya que crear ninguno.
 */
final class LotesNuevosSinHectareas extends RuntimeException
{
    public static function alSubirLaCantidad(): self
    {
        return new self(Texto::de('comercial.errores.lotes_nuevos_sin_hectareas'));
    }
}
