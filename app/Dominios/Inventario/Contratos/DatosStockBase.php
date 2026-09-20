<?php

namespace App\Dominios\Inventario\Contratos;

/**
 * Lo que Inventario sabe de una base, en números, para el resumen
 * relacionado de su ficha en `Personal` (ADR 0003, regla 2): con cuántos
 * repuestos cuenta y cuántos están en el mínimo o por debajo.
 */
final readonly class DatosStockBase
{
    public function __construct(
        public int $repuestos,
        public int $bajoMinimo,
    ) {}
}
