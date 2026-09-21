<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Lo que Mantenimiento sabe de un dron, en datos primitivos, para el resumen
 * relacionado de su ficha en `Operaciones` (ADR 0003, regla 2): si tiene ficha
 * de inventario (y cuáles son sus datos de identidad) y cuántas órdenes de
 * mantenimiento tiene abiertas y en total.
 */
final readonly class DatosMantenimientoDron
{
    public function __construct(
        public ?int $fichaId,
        public ?string $numeroSerie,
        public ?string $versionSoftware,
        public int $ordenesAbiertas,
        public int $ordenesTotal,
    ) {}
}
