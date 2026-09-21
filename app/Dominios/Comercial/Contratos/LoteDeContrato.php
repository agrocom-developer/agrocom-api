<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Un lote del contrato, en forma primitiva, para quien solo necesita
 * mostrarlo o copiarlo sin importar los modelos Eloquent de Comercial (ADR
 * 0003, regla 2). `hectareas` va como texto decimal (`DECIMAL`, nunca float,
 * invariante 6).
 */
final readonly class LoteDeContrato
{
    public function __construct(
        public int $loteId,
        public string $codigo,
        public string $propiedad,
        public string $hectareas,
    ) {}
}
