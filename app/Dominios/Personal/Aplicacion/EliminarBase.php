<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una base (HU-26, tarea 37). Soft delete, nunca físico (ADR
 * 0007) — la fila queda para auditoría con quién la dio de baja. Sin guarda
 * adicional: `per_personas.base_id` es `restrictOnDelete()`, pero eso solo
 * dispara con un `DELETE` físico, que este caso de uso no hace.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarDron`.
 */
final class EliminarBase
{
    public function ejecutar(PerBase $base): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $base->updated_by = (int) $usuarioId;
            $base->save();
        }

        $base->delete();
    }
}
