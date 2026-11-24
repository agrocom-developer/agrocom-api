<?php

namespace App\Dominios\Compartido\Dominio\Excepciones;

use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** Aprobar a un sujeto al que le faltan documentos obligatorios vigentes (ADR 0032). */
final class DocumentosIncompletos extends RuntimeException
{
    /** @param  list<TipoDocumento>  $faltantes */
    public function __construct(public readonly array $faltantes)
    {
        $nombres = array_map(
            static fn (TipoDocumento $tipo): string => Texto::de('proveedores.documentos.tipo.'.$tipo->value),
            $faltantes,
        );

        parent::__construct(Texto::de('proveedores.errores.documentos_incompletos', ['documentos' => implode(', ', $nombres)]));
    }
}
