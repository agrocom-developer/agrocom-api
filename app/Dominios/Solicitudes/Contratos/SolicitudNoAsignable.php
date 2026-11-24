<?php

namespace App\Dominios\Solicitudes\Contratos;

use RuntimeException;

/** La solicitud no está en un estado que admita el paso pedido (otro actor la movió antes). */
final class SolicitudNoAsignable extends RuntimeException
{
    public static function enEstado(string $estado, string $paso): self
    {
        return new self("La solicitud está «{$estado}» y no admite el paso «{$paso}».");
    }
}
