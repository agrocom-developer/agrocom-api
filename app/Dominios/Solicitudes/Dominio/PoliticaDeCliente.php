<?php

namespace App\Dominios\Solicitudes\Dominio;

use Brick\Math\BigDecimal;

/**
 * La política de calificación aplicada al cliente (política de calificación,
 * «Promedio y umbrales»): bajo el umbral, con el mínimo de calificaciones, el
 * cliente recibe un aviso y, si sigue bajo el umbral pasado el plazo, se limita
 * su publicación hasta una revisión. Un promedio que se recupera retira la
 * limitación. Pura: decide, no escribe.
 */
final class PoliticaDeCliente
{
    public const NADA = 'nada';

    public const AVISAR = 'avisar';

    public const LIMITAR = 'limitar';

    public const RESTABLECER = 'restablecer';

    /**
     * @param  int|null  $diasDesdeAviso  días desde el aviso vigente, o `null` si no hay aviso.
     * @return self::NADA|self::AVISAR|self::LIMITAR|self::RESTABLECER
     */
    public static function evaluar(
        string $promedio,
        int $conteo,
        bool $limitado,
        ?int $diasDesdeAviso,
        string $umbral,
        int $minimoConteo,
        int $plazoDias,
    ): string {
        $bajo = $conteo >= $minimoConteo && BigDecimal::of($promedio)->isLessThan(BigDecimal::of($umbral));

        if (! $bajo) {
            return $limitado || $diasDesdeAviso !== null ? self::RESTABLECER : self::NADA;
        }

        if ($limitado) {
            return self::NADA;
        }

        if ($diasDesdeAviso === null) {
            return self::AVISAR;
        }

        return $diasDesdeAviso >= $plazoDias ? self::LIMITAR : self::NADA;
    }
}
