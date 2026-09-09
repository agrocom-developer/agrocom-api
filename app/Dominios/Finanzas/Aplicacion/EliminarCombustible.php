<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una carga de combustible registrada por error (HU-35,
 * tarea 49). Soft delete, nunca físico (ADR 0007), mismo criterio que
 * `EliminarGasto`.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarGasto`/`EliminarAnticipo`.
 */
final class EliminarCombustible
{
    public function ejecutar(Combustible $combustible): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $combustible->updated_by = (int) $usuarioId;
            $combustible->save();
        }

        $combustible->delete();
    }
}
