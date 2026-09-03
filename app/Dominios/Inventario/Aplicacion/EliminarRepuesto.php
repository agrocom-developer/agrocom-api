<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un repuesto (HU-36, tarea 52). Soft delete, nunca físico
 * (ADR 0007) — la fila queda para auditoría con quién la dio de baja. No
 * borra ni valida el stock existente: un repuesto dado de baja puede seguir
 * teniendo filas de `inv_stock` con cantidad > 0 (deja de estar disponible
 * para altas nuevas, no se fuerza a "vaciarlo" antes).
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarBateria`.
 */
final class EliminarRepuesto
{
    public function ejecutar(Repuesto $repuesto): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $repuesto->updated_by = (int) $usuarioId;
            $repuesto->save();
        }

        $repuesto->delete();
    }
}
