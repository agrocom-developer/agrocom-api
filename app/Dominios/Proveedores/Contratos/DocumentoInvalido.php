<?php

namespace App\Dominios\Proveedores\Contratos;

use RuntimeException;

/**
 * El documento que otro módulo pidió cargar no se puede aceptar (por ejemplo,
 * ya vence en el pasado). Forma parte del contrato {@see CargaDocumentos} para
 * que quien carga lo capture sin conocer el dominio de Proveedores.
 */
final class DocumentoInvalido extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo = 'vence_en')
    {
        parent::__construct($mensaje);
    }
}
