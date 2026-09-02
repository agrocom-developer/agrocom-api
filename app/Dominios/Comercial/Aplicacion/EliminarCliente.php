<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un cliente (HU-22, tarea 33). Soft delete, nunca físico
 * (ADR 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `RevocarTokenDispositivo`.
 */
final class EliminarCliente
{
    public function ejecutar(Cliente $cliente): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $cliente->updated_by = (int) $usuarioId;
            $cliente->save();
        }

        $cliente->delete();
    }
}
