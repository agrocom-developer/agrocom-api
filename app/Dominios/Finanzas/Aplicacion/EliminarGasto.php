<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un gasto registrado por error (HU-33, tarea 47). Soft
 * delete, nunca físico (ADR 0007) — el comprobante ya se guardó, la fila
 * queda para auditoría con quién la dio de baja. Sin caso de uso de edición:
 * un gasto, una vez creado, es inmutable salvo esta baja (ver
 * `Aplicacion/CrearGasto`).
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarAnticipo`.
 */
final class EliminarGasto
{
    public function ejecutar(Gasto $gasto): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $gasto->updated_by = (int) $usuarioId;
            $gasto->save();
        }

        $gasto->delete();
    }
}
