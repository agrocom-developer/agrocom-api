<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un cultivo (HU-48, tarea 71). Soft delete, nunca físico
 * (ADR 0007) — la fila queda para auditoría y para no romper el historial de
 * siembras que ya lo referencian desde `com_lote_campania`.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarCliente`/`EliminarBase`.
 */
final class EliminarCultivo
{
    public function ejecutar(Cultivo $cultivo): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $cultivo->updated_by = (int) $usuarioId;
            $cultivo->save();
        }

        $cultivo->delete();
    }
}
