<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Invariante 4 de CLAUDE.md: "validador ≠ piloto de esa sesión, a nivel de
 * persona — no de rol" (ADR 0004, sección SEC-multirol). Un jefe de campo
 * que también vuela no puede aprobar NI rechazar sus propias sesiones —
 * ambas son la misma clase de decisión, solo con signo distinto.
 *
 * Extiende `AuthorizationException` (mismo patrón que `PermisoDenegado` y
 * `RolNoAsignado` de Seguridad) para que el manejador de excepciones del
 * framework la traduzca a 403 sin mapeo adicional en el controlador.
 */
final class PilotoNoPuedeDecidirSuPropiaSesion extends AuthorizationException
{
    public static function paraSesion(int $sesionId): self
    {
        return new self(Texto::de('operaciones.errores.piloto_no_puede_decidir_su_propia_sesion', ['id' => $sesionId]));
    }
}
