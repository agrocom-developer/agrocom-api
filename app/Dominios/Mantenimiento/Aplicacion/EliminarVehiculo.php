<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un vehículo (HU-40, tarea 50). Soft delete, nunca físico
 * (ADR 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarDron`.
 */
final class EliminarVehiculo
{
    public function ejecutar(Vehiculo $vehiculo): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $vehiculo->updated_by = (int) $usuarioId;
            $vehiculo->save();
        }

        $vehiculo->delete();
    }
}
