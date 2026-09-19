<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda central de HU-46 (ADR 0015 punto 6, corregido el 8/9/2026 y
 * ampliado el 19/9/2026): un gasto o una carga de combustible solo se
 * imputa a una campaña `abierta` — sin esto el costo de un ciclo productivo
 * que todavía no arrancó, o que ya se liquidó, sigue creciendo. La verifican
 * `Aplicacion/CrearGasto` y `Aplicacion/CrearCombustible` vía
 * `Campania\Contratos\LecturaCampania` (ADR 0003 regla 2: la lógica de qué
 * estado admite imputaciones es de `Campania` —
 * `DatosCampania::admiteImputaciones()` —, esta excepción es la reacción de
 * `Finanzas` ante un `false`) — mismo criterio que su guarda hermana
 * `Comercial\Dominio\Excepciones\CampaniaNoAbierta`.
 *
 * El mensaje distingue los dos casos porque la salida es distinta: a una
 * `planificada` se le dice que la abra; una `cerrada` no tiene vuelta. Antes
 * de la adenda del 19/9/2026 esta clase se llamaba `CampaniaCerrada` y solo
 * rechazaba la cerrada.
 */
final class CampaniaNoAbierta extends RuntimeException
{
    public static function paraCampania(string $codigo, bool $cerrada): self
    {
        return new self($cerrada
            ? Texto::de('finanzas.errores.campania_cerrada', ['codigo' => $codigo])
            : Texto::de('finanzas.errores.campania_no_abierta', ['codigo' => $codigo]));
    }
}
