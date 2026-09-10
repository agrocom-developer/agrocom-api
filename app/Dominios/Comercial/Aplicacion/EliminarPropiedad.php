<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\Excepciones\PropiedadConCamposAsociados;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de una propiedad (ADR 0018). Soft delete, nunca físico (ADR
 * 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * A diferencia de `EliminarCliente`/`EliminarCampo` (que no propagan la baja
 * a sus hijos), acá SÍ se guarda una guarda explícita: una propiedad con
 * campos activos no se puede eliminar (ver el docblock de
 * `PropiedadConCamposAsociados` para el porqué).
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarCliente`/`EliminarCampo`.
 */
final class EliminarPropiedad
{
    /**
     * @throws PropiedadConCamposAsociados si la propiedad tiene campos
     *                                     activos asociados.
     */
    public function ejecutar(Propiedad $propiedad): void
    {
        if ($propiedad->campos()->exists()) {
            throw PropiedadConCamposAsociados::paraPropiedad($propiedad->nombre);
        }

        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $propiedad->updated_by = (int) $usuarioId;
            $propiedad->save();
        }

        $propiedad->delete();
    }
}
