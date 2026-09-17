<?php

namespace App\Dominios\Inventario\Contratos\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda de dominio de "el stock nunca queda negativo" (CA esencial de
 * HU-36, tarea 52). `RegistrarMovimientoStock` la lanza ANTES de restar,
 * dentro de la transacción con `lockForUpdate()` — es el camino feliz de la
 * validación; el `CHECK (cantidad >= 0)` de `inv_stock` es el backstop, no
 * el mecanismo primario. El controlador la traduce a 422, nunca a un 500 de
 * `QueryException` por violación de CHECK.
 *
 * Vive en `Contratos/` (tarea 82) porque ya es parte de la frontera pública
 * de `Inventario`: el contrato `EscrituraConsumoStock::consumir()` la declara
 * como su `@throws` para quien lo invoque desde otro módulo (`Mantenimiento`).
 */
final class StockInsuficiente extends RuntimeException
{
    public static function paraMovimiento(string $codigoRepuesto, int $baseId, string $disponible, string $solicitada): self
    {
        return new self(
            Texto::de('inventario.errores.stock_insuficiente', [
                'codigo_repuesto' => $codigoRepuesto,
                'base_id' => $baseId,
                'disponible' => $disponible,
                'solicitada' => $solicitada,
            ]),
        );
    }
}
