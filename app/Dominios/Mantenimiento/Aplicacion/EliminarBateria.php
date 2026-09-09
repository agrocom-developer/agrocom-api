<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una batería (HU-39, tarea 51). Soft delete, nunca físico
 * (ADR 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarVehiculo`.
 */
final class EliminarBateria
{
    public function ejecutar(Bateria $bateria): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $bateria->updated_by = (int) $usuarioId;
            $bateria->save();
        }

        $bateria->delete();
    }
}
