<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

final class TarifaDuplicada extends RuntimeException
{
    public static function porNombre(string $nombre): self
    {
        return new self(Texto::de('finanzas.errores.tarifa_duplicada', ['nombre' => $nombre]));
    }
}
