<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva de un contrato para quien solo necesita ubicar su
 * cliente, sin importar los modelos Eloquent `Contrato`/`Cliente` (ADR 0003,
 * regla 2): hoy, `Operaciones\Aplicacion\ListarReportesTecnicos` (HU-43,
 * tarea 57), que resuelve el nombre de cliente de cada reporte técnico y
 * filtra el listado por `cliente_id`.
 */
final readonly class DatosResumenContrato
{
    public function __construct(
        public int $contratoId,
        public int $clienteId,
        public string $clienteNombre,
    ) {}
}
