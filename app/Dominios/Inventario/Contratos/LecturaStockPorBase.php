<?php

namespace App\Dominios\Inventario\Contratos;

/**
 * Frontera de lectura de Inventario hacia `Personal` (ADR 0003, regla 2): la
 * ficha de una base muestra, en su resumen relacionado, cuánto stock tiene
 * —cuántos repuestos y cuántos están bajo el mínimo— sin importar el modelo
 * `Stock`. Hermano de {@see LecturaContadoresPanel}, que cuenta lo mismo pero
 * sobre todas las bases.
 */
interface LecturaStockPorBase
{
    /** `$baseId` es el id de `per_bases`. Sin stock, todo en cero. */
    public function deBase(int $baseId): DatosStockBase;
}
