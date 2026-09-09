<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un anticipo registrado por error (HU-29, tarea 41). Soft
 * delete, nunca físico (ADR 0007) — el dinero ya se entregó, la fila queda
 * para auditoría con quién la dio de baja. Sin caso de uso de edición: un
 * anticipo, una vez creado, es inmutable salvo esta baja (ver
 * `Aplicacion/RegistrarAnticipo`).
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarBase`.
 */
final class EliminarAnticipo
{
    public function ejecutar(Anticipo $anticipo): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $anticipo->updated_by = (int) $usuarioId;
            $anticipo->save();
        }

        $anticipo->delete();
    }
}
