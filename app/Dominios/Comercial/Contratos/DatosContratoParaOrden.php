<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Lo que `Operaciones` necesita saber de un contrato para emitir una orden de
 * aplicación (ADR 0022): si admite órdenes (`vigente`), cuántas aplicaciones
 * tiene previstas, sus hectáreas contratadas y el conjunto de lotes que la
 * orden va a copiar. Forma primitiva, sin importar `Contrato` ni `Lote`
 * (ADR 0003, regla 2) — hermana de {@see DatosResumenContrato}.
 *
 * `vigente` es un booleano y no el enum de Comercial: el estado del contrato es
 * del dominio de Comercial, y a la orden solo le importa si admite órdenes.
 */
final readonly class DatosContratoParaOrden
{
    /**
     * @param  list<LoteDeContrato>  $lotes  lotes activos del contrato, ordenados por propiedad y código.
     */
    public function __construct(
        public int $contratoId,
        public int $clienteId,
        public bool $vigente,
        public int $aplicacionesPrevistas,
        public string $hectareasContratadas,
        public array $lotes,
    ) {}
}
