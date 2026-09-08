<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda central de HU-46 (ADR 0015 punto 6, corregido el 8/9/2026): ningún
 * gasto nuevo puede imputarse a una campaña `cerrada` — sin esto el costo de
 * un ciclo productivo ya liquidado sigue creciendo. La verifica
 * `Aplicacion/CrearGasto` vía `Campania\Contratos\LecturaCampania` (ADR 0003
 * regla 2: la lógica de qué estado admite imputaciones es de `Campania`,
 * esta excepción es la reacción de `Finanzas` ante un `false`) — mismo
 * criterio que su guarda hermana {@see \App\Dominios\Comercial\Dominio\Excepciones\CampaniaCerrada}
 * en `Comercial`.
 */
final class CampaniaCerrada extends RuntimeException
{
    public static function paraCampania(string $codigo): self
    {
        return new self("La campaña '{$codigo}' está cerrada: no admite nuevas imputaciones.");
    }
}
