<?php

namespace App\Dominios\Solicitudes\Contratos;

use RuntimeException;

/**
 * La solicitud ya no está en un estado que admita el paso pedido (la
 * adjudicaron, la cancelaron o expiró entre la lectura y la escritura). Quien
 * decide lo traduce a su propio 409.
 */
final class SolicitudNoNegociable extends RuntimeException
{
    public static function enEstado(string $estado, string $hacia): self
    {
        return new self("La solicitud está {$estado} y no puede pasar a {$hacia}.");
    }
}
