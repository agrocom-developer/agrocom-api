<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\VerificadorHistorialLote;
use App\Dominios\Comercial\Dominio\Excepciones\LoteConHistorialAsociado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un lote suelto (tarea 77, HU-54, etapa 2). Soft delete,
 * nunca físico (ADR 0007).
 *
 * Un lote con órdenes de aplicación o trabajos ejecutados en `Operaciones` no
 * se puede dar de baja — mismo criterio y mismo colaborador
 * ({@see VerificadorHistorialLote}) que usa `ActualizarCampo` cuando un lote
 * se quita del formulario de propiedad.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: mismo criterio que
 * `EliminarCampo` — el soft delete de Eloquent hace un `UPDATE` por query
 * builder que no dispara el evento `updating`.
 */
final class EliminarLote
{
    /** @throws LoteConHistorialAsociado si el lote tiene órdenes o trabajos asociados. */
    public function ejecutar(Lote $lote): void
    {
        if (VerificadorHistorialLote::tiene($lote)) {
            throw LoteConHistorialAsociado::paraLote($lote->codigo);
        }

        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $lote->updated_by = (int) $usuarioId;
            $lote->save();
        }

        $lote->delete();
    }
}
