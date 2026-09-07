<?php

namespace App\Dominios\Operaciones\Contratos;

use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;

/**
 * Agregado de avance operativo de un lote (ADR 0003, regla 2): cuántas
 * sesiones lleva, cuántas hectáreas se aplicaron y en qué estado global
 * quedó. Alimenta el tab "Resumen por lote" y el coloreado de los polígonos
 * del mapa — el mismo dato, dos vistas.
 *
 * `hectareasAplicadas` es string decimal (invariante 6): la suma se acumula
 * con `BigDecimal` en PHP, nunca con `SUM()` de SQL, por el mismo motivo que
 * {@see ObtenerAvanceComercial} (en
 * SQLite la agregación numérica pasa por float).
 */
final readonly class ResumenLotePanel
{
    public function __construct(
        public int $loteId,
        public int $sesiones,
        public int $sesionesValidadas,
        public string $hectareasAplicadas,
        public string $tono,
        public ?string $ultimaSesion,
        public string $litrosConsumidos,
        public int $minutosVuelo,
    ) {}
}
