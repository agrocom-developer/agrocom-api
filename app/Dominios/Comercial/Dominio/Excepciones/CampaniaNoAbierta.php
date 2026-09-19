<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda central de HU-46 (ADR 0015 punto 1, corregido el 8/9/2026 y
 * ampliado el 19/9/2026): un contrato solo se asigna a una campaña `abierta`
 * — una `planificada` todavía no arrancó y una `cerrada` ya se liquidó, y
 * seguir cargando compromisos sobre un ciclo productivo así descuadra su
 * cierre. La verifican `Aplicacion/CrearContrato` y
 * `Aplicacion/ActualizarContrato` vía `Campania\Contratos\LecturaCampania`
 * (ADR 0003 regla 2: la lógica de qué estado admite imputaciones es de
 * `Campania` — `DatosCampania::admiteImputaciones()` —, esta excepción es la
 * reacción de `Comercial` ante un `false`) — mismo criterio que su guarda
 * hermana {@see CampaniaDeOtroCliente}.
 *
 * El mensaje distingue los dos casos porque la salida es distinta: a una
 * `planificada` se le dice que la abra; una `cerrada` no tiene vuelta.
 * Antes de la adenda del 19/9/2026 esta clase se llamaba `CampaniaCerrada` y
 * solo rechazaba la cerrada.
 */
final class CampaniaNoAbierta extends RuntimeException
{
    public static function paraCampania(string $codigo, bool $cerrada): self
    {
        return new self($cerrada
            ? Texto::de('comercial.errores.campania_cerrada', ['codigo' => $codigo])
            : Texto::de('comercial.errores.campania_no_abierta', ['codigo' => $codigo]));
    }
}
