<?php

namespace App\Dominios\Solicitudes\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Tarifa estimada de una solicitud (especificación §8.2, ADR 0027): hectáreas
 * por tarifa por hectárea, más recargos explícitos por urgencia, terreno de
 * difícil acceso y producto aportado por el proveedor. No hay tarifa dinámica
 * por demanda. Toda la aritmética es decimal exacto (invariante 6 de
 * CLAUDE.md); el monto se redondea a dos decimales, mitad hacia arriba.
 *
 * Los parámetros (`config/solicitudes.php`) se inyectan como arreglo para que
 * esta clase siga pura.
 */
final class EstimacionTarifa
{
    /**
     * @param  array<string, string>  $tarifasPorTipo  tarifa base por hectárea, por tipo de aplicación; `defecto` es el respaldo.
     * @param  string  $recargoUrgentePct  porcentaje sobre el subtotal base.
     * @param  string  $recargoAccesoDificilPct  porcentaje sobre el subtotal base.
     * @param  string  $recargoProductoHectarea  monto por hectárea cuando el proveedor aporta el producto.
     */
    public function __construct(
        private readonly array $tarifasPorTipo,
        private readonly string $recargoUrgentePct,
        private readonly string $recargoAccesoDificilPct,
        private readonly string $recargoProductoHectarea,
    ) {}

    public function estimar(
        TipoAplicacionServicio $tipo,
        string $hectareas,
        bool $urgente,
        bool $accesoDificil,
        AportaProducto $aportaProducto,
    ): DatosEstimacion {
        $ha = BigDecimal::of($hectareas);
        $tarifaBase = BigDecimal::of($this->tarifasPorTipo[$tipo->value] ?? $this->tarifasPorTipo['defecto']);
        $subtotal = $ha->multipliedBy($tarifaBase);

        $urgente = $urgente ? $this->porcentaje($subtotal, $this->recargoUrgentePct) : BigDecimal::zero();
        $acceso = $accesoDificil ? $this->porcentaje($subtotal, $this->recargoAccesoDificilPct) : BigDecimal::zero();
        $producto = $aportaProducto === AportaProducto::Proveedor
            ? $ha->multipliedBy(BigDecimal::of($this->recargoProductoHectarea))
            : BigDecimal::zero();

        $total = $subtotal->plus($urgente)->plus($acceso)->plus($producto);

        // Tarifa por hectárea con recargos incluidos: el cliente la ve y se congela.
        $tarifaConRecargos = $ha->isZero() ? $tarifaBase : $total->dividedBy($ha, 4, RoundingMode::HalfUp);

        return new DatosEstimacion(
            tarifaBaseHectarea: $tarifaBase->toScale(4, RoundingMode::HalfUp)->__toString(),
            tarifaHectarea: $tarifaConRecargos->toScale(4, RoundingMode::HalfUp)->__toString(),
            hectareas: $ha->toScale(4, RoundingMode::HalfUp)->__toString(),
            subtotalBase: $this->monto($subtotal),
            recargoUrgente: $this->monto($urgente),
            recargoAccesoDificil: $this->monto($acceso),
            recargoProducto: $this->monto($producto),
            montoEstimado: $this->monto($total),
        );
    }

    private function porcentaje(BigDecimal $base, string $pct): BigDecimal
    {
        return $base->multipliedBy(BigDecimal::of($pct))->dividedBy(100, 6, RoundingMode::HalfUp);
    }

    private function monto(BigDecimal $valor): string
    {
        return $valor->toScale(2, RoundingMode::HalfUp)->__toString();
    }
}
