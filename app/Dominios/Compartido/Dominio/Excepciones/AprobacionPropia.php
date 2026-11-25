<?php

namespace App\Dominios\Compartido\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** Quien registró a un proveedor, piloto o dron desde el panel intenta aprobarlo él mismo (separación de funciones). */
final class AprobacionPropia extends RuntimeException
{
    public static function nueva(): self
    {
        return new self(Texto::de('aprobacion.errores.aprobacion_propia'));
    }
}
