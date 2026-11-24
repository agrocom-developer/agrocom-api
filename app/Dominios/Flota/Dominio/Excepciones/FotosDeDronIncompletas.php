<?php

namespace App\Dominios\Flota\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Se intentó aprobar un dron al que le faltan fotos obligatorias aprobadas
 * (`config('flota.fotos_obligatorias')`): el mensaje nombra cuáles faltan.
 */
final class FotosDeDronIncompletas extends RuntimeException
{
    /** @param  list<string>  $faltantes  tipos de foto sin una versión aprobada. */
    public function __construct(public readonly array $faltantes)
    {
        $nombres = array_map(static fn (string $tipo): string => Texto::de('flota.fotos.tipo.'.$tipo), $faltantes);

        parent::__construct(Texto::de('flota.errores.fotos_incompletas', ['fotos' => implode(', ', $nombres)]));
    }
}
