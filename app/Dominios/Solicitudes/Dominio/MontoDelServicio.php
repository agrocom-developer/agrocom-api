<?php

namespace App\Dominios\Solicitudes\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * El monto final de un servicio (política de comisión, «Tarifa al cliente»): las
 * hectáreas facturadas son las confirmadas, con una tolerancia sobre las
 * solicitadas, y el monto escala sobre el estimado congelado al publicar (que ya
 * lleva los recargos). Sin hectáreas confirmadas se factura lo solicitado. Pura,
 * en decimal exacto (invariante 6 de CLAUDE.md).
 */
final class MontoDelServicio
{
    /**
     * Hectáreas que se facturan: las confirmadas, sin pasar de las solicitadas
     * más la tolerancia (en porcentaje).
     */
    public static function hectareasFacturadas(string $solicitadas, ?string $confirmadas, string $toleranciaPct): string
    {
        $solicitadas = BigDecimal::of($solicitadas);

        if ($confirmadas === null) {
            return (string) $solicitadas->toScale(4, RoundingMode::HalfUp);
        }

        $tope = $solicitadas->multipliedBy(BigDecimal::of('100')->plus(BigDecimal::of($toleranciaPct)))->dividedBy(100, 4, RoundingMode::HalfUp);
        $confirmadas = BigDecimal::of($confirmadas);

        return (string) ($confirmadas->isGreaterThan($tope) ? $tope : $confirmadas)->toScale(4, RoundingMode::HalfUp);
    }

    /** `monto estimado × hectáreas facturadas / hectáreas solicitadas`, a dos decimales. */
    public static function montoFinal(string $montoEstimado, string $solicitadas, string $facturadas): string
    {
        $solicitadas = BigDecimal::of($solicitadas);

        if ($solicitadas->isZero()) {
            return (string) BigDecimal::of($montoEstimado)->toScale(2, RoundingMode::HalfUp);
        }

        return (string) BigDecimal::of($montoEstimado)
            ->multipliedBy(BigDecimal::of($facturadas))
            ->dividedBy($solicitadas, 2, RoundingMode::HalfUp);
    }
}
