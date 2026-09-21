<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\PropiedadConLotesAsociados;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una propiedad (ADR 0020). Soft delete, nunca físico (ADR
 * 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * A diferencia de `EliminarCliente` (que no propaga la baja a sus
 * contactos), acá SÍ se guarda una guarda explícita: una propiedad con
 * lotes activos no se puede eliminar (ver el docblock de
 * `PropiedadConLotesAsociados` para el porqué).
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarCliente`.
 */
final class EliminarPropiedad
{
    /**
     * @throws PropiedadConLotesAsociados si la propiedad tiene lotes
     *                                    activos asociados.
     */
    public function ejecutar(Propiedad $propiedad): void
    {
        if ($propiedad->lotes()->exists()) {
            throw PropiedadConLotesAsociados::paraPropiedad($propiedad->nombre);
        }

        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $propiedad->updated_by = (int) $usuarioId;
            $propiedad->save();
        }

        $propiedad->delete();
    }
}
