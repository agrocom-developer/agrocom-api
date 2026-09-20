<?php

namespace App\Dominios\Inventario\Contratos;

/**
 * Una línea de repuesto consumida al cerrar una orden de mantenimiento, con
 * lo que `Mantenimiento` necesita para mostrarla y sin nada más del asiento
 * (`inv_movimientos`) ni del catálogo (`inv_repuestos`) — ADR 0003 regla 2.
 *
 * `baseId` viaja crudo: la base es de `Personal` y el nombre lo resuelve
 * quien ya lo tiene a mano, no `Inventario`.
 *
 * Cantidades y costos son DECIMAL como string (invariante 6 de CLAUDE.md:
 * nunca float), siempre recalculables desde el asiento de origen.
 */
final readonly class DatosConsumoOrden
{
    public function __construct(
        public int $repuestoId,
        public string $codigo,
        public string $descripcion,
        public int $baseId,
        public string $cantidad,
        public string $costoUnitario,
        public string $costoTotal,
    ) {}

    /** Texto para una celda o un `<select>`: "RP-01 — Hélice". */
    public function etiqueta(): string
    {
        return $this->descripcion === '' ? $this->codigo : "{$this->codigo} — {$this->descripcion}";
    }
}
