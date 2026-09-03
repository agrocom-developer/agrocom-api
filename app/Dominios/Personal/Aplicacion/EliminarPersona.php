<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una persona operativa (HU-26, tarea 37). Soft delete, nunca
 * físico (ADR 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarBase`.
 */
final class EliminarPersona
{
    public function ejecutar(PerPersona $persona): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $persona->updated_by = (int) $usuarioId;
            $persona->save();
        }

        $persona->delete();
    }
}
