<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenVigenteNoEliminable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una orden de aplicación (HU-25, tarea 38). Soft delete,
 * nunca físico (ADR 0007).
 *
 * Decisión de esta tarea: una orden `vigente` NO se puede eliminar
 * directamente — ver `Dominio/Excepciones/OrdenVigenteNoEliminable`. Una
 * orden `emitida` (todavía no publicada al catálogo) sí se da de baja
 * libremente.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarDron`.
 */
final class EliminarOrden
{
    /** @throws OrdenVigenteNoEliminable si `$orden` está `vigente`. */
    public function ejecutar(OrdenAplicacion $orden): void
    {
        if ($orden->estado === EstadoOrdenAplicacion::Vigente) {
            throw OrdenVigenteNoEliminable::porId((int) $orden->id);
        }

        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $orden->updated_by = (int) $usuarioId;
            $orden->save();
        }

        $orden->delete();
    }
}
