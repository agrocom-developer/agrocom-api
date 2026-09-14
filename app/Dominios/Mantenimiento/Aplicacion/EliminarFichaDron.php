<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una ficha de inventario de dron (HU-82, tarea 97). Soft
 * delete, nunca físico (ADR 0007) — la fila queda para auditoría con quién
 * la dio de baja. No toca `ope_drones`: la única correlación con esa tabla
 * es de texto, sin FK.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarBateria`.
 */
final class EliminarFichaDron
{
    public function ejecutar(FichaDron $ficha): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $ficha->updated_by = (int) $usuarioId;
            $ficha->save();
        }

        $ficha->delete();
    }
}
