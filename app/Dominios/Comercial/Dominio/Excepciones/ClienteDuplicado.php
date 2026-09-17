<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `com_clientes_nit_unico` (HU-22): un NIT no puede repetirse entre clientes
 * ACTIVOS (un cliente dado de baja lógica no bloquea el re-alta con el mismo
 * NIT). El caso de uso que persiste `Cliente` captura la `QueryException` y la
 * relanza como esta excepción — nunca deja propagarse el 500 crudo del motor
 * de base de datos. Mismo criterio que `UsuarioDuplicado` en Seguridad.
 */
final class ClienteDuplicado extends RuntimeException
{
    public static function porNit(string $nit): self
    {
        return new self(Texto::de('comercial.errores.cliente_nit_duplicado', ['nit' => $nit]));
    }
}
