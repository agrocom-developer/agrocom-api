<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un generador (tarea 72, HU-49). Soft delete, nunca físico
 * (ADR 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarVehiculo`.
 */
final class EliminarGenerador
{
    public function ejecutar(Generador $generador): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $generador->updated_by = (int) $usuarioId;
            $generador->save();
        }

        $generador->delete();
    }
}
