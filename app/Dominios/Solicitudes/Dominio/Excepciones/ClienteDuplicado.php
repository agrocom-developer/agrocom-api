<?php

namespace App\Dominios\Solicitudes\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** El documento fiscal de un cliente nuevo ya pertenece a otro cliente vivo. */
final class ClienteDuplicado extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo)
    {
        parent::__construct($mensaje);
    }

    public static function porDocumentoFiscal(string $documento): self
    {
        return new self(Texto::de('solicitudes.errores.documento_fiscal_duplicado', ['documento' => $documento]), 'documento_fiscal');
    }
}
