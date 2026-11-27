<?php

namespace App\Dominios\Reclamos\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Lo que un resultado permite reembolsar (política de reclamos, «Reembolsos»), en
 * decimal exacto (invariante 6 de CLAUDE.md). A favor del proveedor o sin lugar no
 * reembolsan nada; a favor del cliente reembolsa hasta el total; parcial, algo
 * entre cero y el total. Un reembolso total deja la solicitud cancelada. El ajuste
 * de comisión sigue la misma proporción que el reembolso. Pura.
 */
final class ReglaDeResolucion
{
    /**
     * @param  string  $referencia  monto sobre el que se reembolsa (el final o, sin él, el estimado).
     * @return string|null el motivo del error si el reembolso no corresponde al resultado, o `null` si es válido.
     */
    public static function error(ResultadoReclamo $resultado, string $referencia, string $reembolso): ?string
    {
        $reembolso = BigDecimal::of($reembolso);
        $referencia = BigDecimal::of($referencia);

        if ($reembolso->isNegative()) {
            return 'negativo';
        }

        return match ($resultado) {
            ResultadoReclamo::AFavorProveedor, ResultadoReclamo::SinLugar => $reembolso->isZero() ? null : 'sin_reembolso',
            ResultadoReclamo::AFavorCliente => $reembolso->isZero() ? 'requiere_reembolso' : ($reembolso->isGreaterThan($referencia) ? 'excede_monto' : null),
            ResultadoReclamo::Parcial => $reembolso->isZero() ? 'requiere_reembolso' : ($reembolso->isGreaterThanOrEqualTo($referencia) ? 'parcial_no_total' : null),
        };
    }

    /** Un reembolso del monto completo a favor del cliente cancela la solicitud (especificación §5.1). */
    public static function esReembolsoTotal(ResultadoReclamo $resultado, string $referencia, string $reembolso): bool
    {
        return $resultado === ResultadoReclamo::AFavorCliente
            && BigDecimal::of($reembolso)->isGreaterThanOrEqualTo(BigDecimal::of($referencia));
    }

    /** Lo que Agrocom resta a su comisión: `comisión × reembolso / monto final`, a dos decimales. */
    public static function ajusteDeComision(?string $comision, ?string $montoFinal, string $reembolso): string
    {
        if ($comision === null || $montoFinal === null || BigDecimal::of($montoFinal)->isZero()) {
            return '0.00';
        }

        $ajuste = BigDecimal::of($comision)->multipliedBy(BigDecimal::of($reembolso))->dividedBy(BigDecimal::of($montoFinal), 2, RoundingMode::HalfUp);
        $tope = BigDecimal::of($comision)->toScale(2, RoundingMode::HalfUp);

        return (string) ($ajuste->isGreaterThan($tope) ? $tope : $ajuste);
    }
}
