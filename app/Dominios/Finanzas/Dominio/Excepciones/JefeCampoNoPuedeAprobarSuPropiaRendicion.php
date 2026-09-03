<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Invariante 4 de CLAUDE.md: "el aprobador nunca puede ser la misma persona
 * que la rindió, a nivel de persona — no de rol". Un jefe de campo que
 * también operara como encargado (misma persona, dos roles) no puede aprobar
 * su propia rendición.
 *
 * Extiende `AuthorizationException` (mismo patrón que
 * `PilotoNoPuedeDecidirSuPropiaSesion` de `Operaciones`) para que el manejador
 * de excepciones del framework la traduzca a 403 sin mapeo adicional en el
 * controlador.
 */
final class JefeCampoNoPuedeAprobarSuPropiaRendicion extends AuthorizationException
{
    public static function paraRendicion(int $rendicionId): self
    {
        return new self("El jefe de campo de la rendición #{$rendicionId} no puede aprobar su propia rendición.");
    }
}
