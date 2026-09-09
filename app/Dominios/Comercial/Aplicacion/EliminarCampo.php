<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un campo (HU-24, tarea 35). Soft delete, nunca físico (ADR
 * 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * No propaga la baja a sus lotes (mismo criterio que `EliminarCliente` con
 * sus contactos): un campo eliminado deja de listarse y editarse desde el
 * panel, pero sus lotes no se tocan — si más adelante hiciera falta impedir
 * la baja de un campo con lotes que tienen historial de negocio, es la
 * misma decisión que ya toma `ActualizarCampo` al sincronizar lotes, así que
 * extenderla a este caso de uso no requeriría un mecanismo nuevo.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarCliente`.
 */
final class EliminarCampo
{
    public function ejecutar(Campo $campo): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $campo->updated_by = (int) $usuarioId;
            $campo->save();
        }

        $campo->delete();
    }
}
