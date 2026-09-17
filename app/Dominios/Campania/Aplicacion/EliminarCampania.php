<?php

namespace App\Dominios\Campania\Aplicacion;

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una campaña (HU-46, ADR 0015 punto 1). Soft delete, nunca
 * físico (ADR 0007) — la fila queda para auditoría y para no romper el
 * historial de trabajos/sesiones que ya la referencian.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarCultivo`/`EliminarCliente`/`EliminarBase`.
 */
final class EliminarCampania
{
    public function ejecutar(Campania $campania): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $campania->updated_by = (int) $usuarioId;
            $campania->save();
        }

        $campania->delete();
    }
}
