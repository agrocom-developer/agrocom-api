<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Invariante 4 de CLAUDE.md: "validador ≠ piloto de esa sesión, a nivel de
 * persona — no de rol" (ADR 0004, SEC-multirol). Regla pura, sin Eloquent
 * ni `Illuminate\Database` (verificado por `tests/Unit/ArquitecturaModulosTest.php`),
 * comparando `per_personas.id` planos — nunca `sec_user.id` ni el rol con el
 * que alguien entró al panel: un jefe de campo que TAMBIÉN es piloto (misma
 * persona, dos roles) sigue sin poder decidir sobre su propio vuelo.
 *
 * Se aplica igual a aprobar y a rechazar (`Aplicacion/ValidarSesion.php`,
 * `Aplicacion/RechazarSesion.php`): ambas son la misma clase de decisión del
 * jefe sobre la sesión, solo con signo distinto — la espec (§5) solo nombra
 * "validada" pero la razón de fondo (que un piloto no se autoapruebe) es
 * exactamente la misma para el rechazo.
 */
final class PoliticaValidacionSesion
{
    public static function puedeDecidir(int $pilotoPersonaId, int $decisorPersonaId): bool
    {
        return $pilotoPersonaId !== $decisorPersonaId;
    }
}
