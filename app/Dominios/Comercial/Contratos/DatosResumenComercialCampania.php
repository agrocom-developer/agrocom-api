<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva del resumen comercial de una campaña (ADR 0003,
 * regla 2): lo que `Campania` necesita para su aside financiero/de trabajo
 * sin importar los modelos Eloquent `Contrato`/`Factura`. `contratoIds` va
 * incluido porque `Operaciones` (trabajos realizados) filtra por contrato,
 * no por campaña — evita que `Campania` tenga que conocer esa cadena de FKs.
 *
 * `facturado`/`hectareasContratadas` como `string` decimal (invariante 6 de
 * CLAUDE.md: dinero y hectáreas nunca `float`), mismo criterio que
 * `Finanzas\Contratos\DevengoPanel`.
 */
final readonly class DatosResumenComercialCampania
{
    /** @param list<int> $contratoIds */
    public function __construct(
        public string $facturado,
        public int $totalContratos,
        public string $hectareasContratadas,
        public array $contratoIds,
    ) {}
}
