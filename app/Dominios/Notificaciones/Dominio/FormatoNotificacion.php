<?php

namespace App\Dominios\Notificaciones\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Formato de las cifras que entran al texto de un aviso. Las hectáreas llegan
 * en el evento como decimal exacto (`'1250.00'`, invariante 6: nunca float), se
 * redondean al centésimo con `Brick\Math` y se agrupan de a tres con «.» y el
 * decimal con «,» sobre el texto — sin pasar por `float` en ningún momento.
 * El aviso guarda la cifra ya presentable (es-AR); el texto que la rodea se
 * traduce al mirar.
 */
final class FormatoNotificacion
{
    public static function hectareas(string $decimal): string
    {
        [$entera, $decimales] = explode('.', (string) BigDecimal::of($decimal)->toScale(2, RoundingMode::HalfUp));

        $signo = str_starts_with($entera, '-') ? '-' : '';
        $entera = ltrim($entera, '-');

        return $signo.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $entera).','.$decimales;
    }
}
