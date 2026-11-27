<?php

namespace App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use Brick\Math\BigDecimal;
use Throwable;

/** «Menos de N hectáreas no»: no se avisa de solicitudes por debajo del mínimo. Decimal exacto, nunca `float`. */
final class HectareasMinimas implements FiltroDeProveedor
{
    public const string CLAVE = 'hectareas_minimas';

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function tipo(): string
    {
        return 'decimal';
    }

    public function porDefecto(): mixed
    {
        return null;
    }

    public function normalizar(mixed $valor): mixed
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (! is_string($valor) && ! is_int($valor)) {
            throw NegociacionInvalida::dato('filtro_invalido', 'nuevas_solicitudes.'.self::CLAVE);
        }

        try {
            $decimal = BigDecimal::of((string) $valor)->toScale(4);
        } catch (Throwable) {
            // No es un número, o trae más de cuatro decimales (`RoundingNecessaryException`).
            throw NegociacionInvalida::dato('filtro_invalido', 'nuevas_solicitudes.'.self::CLAVE);
        }

        if ($decimal->isNegative()) {
            throw NegociacionInvalida::dato('filtro_invalido', 'nuevas_solicitudes.'.self::CLAVE);
        }

        return (string) $decimal;
    }

    public function admite(mixed $valor, SolicitudParaAviso $solicitud): bool
    {
        return BigDecimal::of($solicitud->hectareas)->isGreaterThanOrEqualTo(BigDecimal::of((string) $valor));
    }
}
