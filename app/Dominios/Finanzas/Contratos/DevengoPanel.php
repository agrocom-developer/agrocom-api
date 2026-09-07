<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Forma primitiva de un devengo para el dashboard del piloto/auxiliar (ADR
 * 0003, regla 2). Dinero y hectáreas como string decimal (invariante 6):
 * este es el número que la persona compara con su recibo.
 */
final readonly class DevengoPanel
{
    public function __construct(
        public int $id,
        public int $sesionId,
        public string $fecha,
        public string $hectareas,
        public string $tarifaHa,
        public string $monto,
    ) {}
}
