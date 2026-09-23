<?php

namespace App\Dominios\Notificaciones\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Formato de las cifras que entran al texto de un aviso. Las hectáreas llegan
 * en el evento como decimal exacto (`'1250.00'`, invariante 6: nunca float) y
 * se redondean al centésimo con `Brick\Math`; el `number_format` final es solo
 * de presentación, sobre un valor que ya no se recalcula.
 */
final class FormatoNotificacion
{
    public static function hectareas(string $decimal): string
    {
        $centesimos = BigDecimal::of($decimal)->toScale(2, RoundingMode::HalfUp);

        return number_format((float) (string) $centesimos, 2, ',', '.');
    }
}
