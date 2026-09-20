<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una estadía en hacienda (reforma 19/9/2026). Soft delete,
 * nunca físico (ADR 0007) — sin guarda de negocio: se puede dar de baja tanto
 * una estadía en curso (cargada por error) como una finalizada (es la única
 * forma de corregirla, ver docblock de `Aplicacion/ActualizarEstadiaHacienda`).
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarDron`/`EliminarCliente`.
 */
final class EliminarEstadiaHacienda
{
    public function ejecutar(EstadiaHacienda $estadia): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $estadia->updated_by = (int) $usuarioId;
            $estadia->save();
        }

        $estadia->delete();
    }
}
