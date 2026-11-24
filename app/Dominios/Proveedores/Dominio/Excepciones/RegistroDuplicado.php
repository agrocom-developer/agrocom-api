<?php

namespace App\Dominios\Proveedores\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** Un dato único (documento fiscal, licencia) que ya pertenece a otro registro vivo. `campo` dice dónde mostrar el error. */
final class RegistroDuplicado extends RuntimeException
{
    private function __construct(string $mensaje, public readonly string $campo)
    {
        parent::__construct($mensaje);
    }

    public static function porDocumentoFiscal(string $documento): self
    {
        return new self(Texto::de('proveedores.errores.documento_fiscal_duplicado', ['documento' => $documento]), 'documento_fiscal');
    }
}
