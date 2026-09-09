<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un equipo de trabajo (tarea 72, HU-49). Soft delete, nunca
 * físico (ADR 0007) — la fila queda para auditoría, y sus integrantes y
 * recursos (que llevan FK real hacia ella, `restrictOnDelete`) no se ven
 * afectados: la baja del equipo no borra su historial de vigencias.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarGenerador`.
 */
final class EliminarEquipoTrabajo
{
    public function ejecutar(EquipoTrabajo $equipo): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $equipo->updated_by = (int) $usuarioId;
            $equipo->save();
        }

        $equipo->delete();
    }
}
