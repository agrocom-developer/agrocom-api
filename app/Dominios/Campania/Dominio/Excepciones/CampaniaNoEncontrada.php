<?php

namespace App\Dominios\Campania\Dominio\Excepciones;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * La campaña que se intenta activar (cambio de campaña activa sin volver a
 * loguearse) no existe o está borrada (soft delete) — ADR 0015 punto 1, tarea
 * 69. Extiende `AuthorizationException` (mismo patrón que `RolNoAsignado` en
 * Seguridad) para que el manejador de excepciones del framework la traduzca a
 * 403 sin mapeo adicional en el controlador: nunca se confía en que el
 * `<select>` del panel solo ofrecía campañas legítimas.
 */
final class CampaniaNoEncontrada extends AuthorizationException
{
    public static function paraId(int $idCampania): self
    {
        return new self("La campaña #{$idCampania} no existe o está dada de baja.");
    }
}
