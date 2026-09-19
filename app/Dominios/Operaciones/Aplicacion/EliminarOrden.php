<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoEliminable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Baja lógica de una orden de aplicación (HU-25, tarea 38). Soft delete,
 * nunca físico (ADR 0007).
 *
 * Reforma 19/9/2026 (ADR 0022): solo una orden `emitida` — todavía sin
 * publicar al catálogo de campo — se da de baja. Cualquier otra ya tiene
 * historia (trabajos, pausas, un cierre, una cancelación con su motivo), y
 * borrarla liberaría su número correlativo: si una aplicación no se llevó
 * adelante, se CANCELA, no se elimina. Eliminar una `emitida` libera la
 * "aplicación abierta" del contrato, así se puede emitir otra.
 *
 * La copia de lotes de la orden (`ope_orden_lotes`) se da de baja junto con
 * ella: sin esto quedaría huérfana y seguiría contando como "lote con orden"
 * (`LecturaLotesConOrdenPorContrato`).
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarDron`.
 */
final class EliminarOrden
{
    /** @throws OrdenNoEliminable si `$orden` no está `emitida`. */
    public function ejecutar(OrdenAplicacion $orden): void
    {
        DB::transaction(function () use ($orden): void {
            $actual = OrdenAplicacion::query()->lockForUpdate()->findOrFail($orden->id);

            if ($actual->estado !== EstadoOrdenAplicacion::Emitida) {
                throw OrdenNoEliminable::porEstado($actual->estado->value);
            }

            $usuarioId = Auth::id();

            if ($usuarioId !== null) {
                $actual->updated_by = (int) $usuarioId;
                $actual->save();
            }

            $actual->ordenLotes()->delete();
            $actual->delete();
        });
    }
}
