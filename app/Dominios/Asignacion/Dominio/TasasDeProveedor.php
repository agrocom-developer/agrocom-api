<?php

namespace App\Dominios\Asignacion\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Tasas de aceptación y de cancelación de un proveedor (especificación §4.3 y
 * política de calificación), en la ventana que el llamador elija. Decimal
 * exacto con cuatro decimales, como la columna que las guarda.
 */
final class TasasDeProveedor
{
    /**
     * Ofertas aceptadas sobre las que el proveedor tuvo que responder. Las
     * retiradas no cuentan: no dependieron de él. Sin ofertas, 1 (no hay
     * evidencia en contra).
     */
    public static function aceptacion(int $aceptadas, int $rechazadas, int $vencidas): string
    {
        $respondidas = $aceptadas + $rechazadas + $vencidas;

        if ($respondidas === 0) {
            return '1.0000';
        }

        return BigDecimal::of($aceptadas)->dividedBy($respondidas, 4, RoundingMode::HalfUp)->__toString();
    }

    /**
     * Peso de las cancelaciones sobre los servicios aceptados en la ventana.
     * Una cancelación tardía pesa el doble; la tasa nunca pasa de 1.
     */
    public static function cancelacion(int $pesoDeCancelaciones, int $aceptadas): string
    {
        if ($pesoDeCancelaciones <= 0) {
            return '0.0000';
        }

        $base = max($aceptadas, 1);
        $tasa = BigDecimal::of($pesoDeCancelaciones)->dividedBy($base, 4, RoundingMode::HalfUp);

        return $tasa->isGreaterThan(BigDecimal::one()) ? '1.0000' : $tasa->__toString();
    }

    /** Peso con que una cancelación cuenta, según causa y anticipación (política de cancelación). */
    public static function pesoDeCancelacion(CausaCancelacion $causa, float $horasDeAnticipacion, int $horasTardia, int $pesoTardia): int
    {
        if ($causa === CausaCancelacion::Emergencia) {
            return 0;
        }

        if ($causa === CausaCancelacion::FallaDeEquipo && $horasDeAnticipacion >= $horasTardia) {
            return 0;
        }

        return $horasDeAnticipacion < $horasTardia ? $pesoTardia : 1;
    }
}
