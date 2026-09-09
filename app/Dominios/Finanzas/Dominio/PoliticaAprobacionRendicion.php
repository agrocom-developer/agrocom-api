<?php

namespace App\Dominios\Finanzas\Dominio;

/**
 * Invariante 4 de CLAUDE.md: "el aprobador nunca puede ser la misma persona
 * que la rindió, a nivel de persona — no de rol" (mismo mecanismo que
 * `docs/decisiones/0004-modelo-seguridad-sec-multirol.md` aplica a
 * "validador ≠ piloto de esa sesión"). Regla pura, sin Eloquent ni
 * `Illuminate\Database` (verificado por `tests/Unit/ArquitecturaModulosTest.php`),
 * comparando `per_personas.id` planos — nunca `sec_user.id` ni el rol con el
 * que alguien entró al panel: un encargado que también fuera, en algún caso,
 * el jefe de campo que rindió (misma persona, dos roles) seguiría sin poder
 * aprobar su propia rendición.
 *
 * Clon deliberado de `Operaciones/Dominio/PoliticaValidacionSesion` (regla de
 * frontera modular, ADR 0003 regla 2): esta clase NO se importa
 * cross-módulo, se declara de nuevo acá porque la regla de negocio es la
 * misma pero la entidad («rendición» en vez de «sesión») pertenece a otro
 * módulo.
 */
final class PoliticaAprobacionRendicion
{
    public static function puedeDecidir(int $jefeCampoPersonaId, int $aprobadorPersonaId): bool
    {
        return $jefeCampoPersonaId !== $aprobadorPersonaId;
    }
}
