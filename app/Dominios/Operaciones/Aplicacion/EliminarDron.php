<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un dron (HU-27, tarea 36). Soft delete, nunca físico (ADR
 * 0007) — la fila queda para auditoría con quién la dio de baja. Sin guarda
 * adicional: `ope_sesiones.dron_id`/`ope_alertas.dron_id` son
 * `restrictOnDelete()`, pero eso solo dispara con un `DELETE` físico, que
 * este caso de uso no hace.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarCliente`.
 */
final class EliminarDron
{
    public function ejecutar(Dron $dron): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $dron->updated_by = (int) $usuarioId;
            $dron->save();
        }

        $dron->delete();
    }
}
