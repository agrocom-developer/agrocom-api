<?php

namespace App\Dominios\Solicitudes\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * La comisión que Agrocom devenga por un servicio completado (política de
 * comisión, ADR 0034): un porcentaje sobre el monto final que se congela en la
 * solicitud. Se calcula, no se cobra: la versión 1 no procesa pagos. Pura, en
 * decimal exacto (invariante 6 de CLAUDE.md).
 */
final class ComisionDeServicio
{
    /**
     * Porcentaje según quién presta el servicio: el proveedor propio no paga
     * comisión (el ingreso es de Agrocom); el dron vendido por Agrocom tiene una
     * tasa preferencial del grupo; independientes y terceros pagan la tasa base.
     *
     * @param  array{propio: string, independiente: string, tercero: string, dron_vendido: string}  $tasas  porcentajes (0 a 100).
     */
    public static function porcentaje(string $tipoProveedor, bool $dronVendidoPorAgrocom, array $tasas): string
    {
        if ($tipoProveedor === 'propio') {
            return self::normalizar($tasas['propio']);
        }

        if ($dronVendidoPorAgrocom) {
            return self::normalizar($tasas['dron_vendido']);
        }

        return self::normalizar($tipoProveedor === 'independiente' ? $tasas['independiente'] : $tasas['tercero']);
    }

    /** Monto de la comisión: `monto × porcentaje / 100`, a dos decimales, redondeo comercial. */
    public static function monto(string $montoFinal, string $porcentaje): string
    {
        return (string) BigDecimal::of($montoFinal)
            ->multipliedBy(BigDecimal::of($porcentaje))
            ->dividedBy(100, 2, RoundingMode::HalfUp);
    }

    /** Lo que cobra el proveedor, ya descontada la comisión. */
    public static function neto(string $montoFinal, string $comisionMonto): string
    {
        return (string) BigDecimal::of($montoFinal)->minus(BigDecimal::of($comisionMonto))->toScale(2, RoundingMode::HalfUp);
    }

    private static function normalizar(string $porcentaje): string
    {
        return (string) BigDecimal::of($porcentaje)->toScale(2, RoundingMode::HalfUp);
    }
}
