<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaTrabajosPorContrato;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;

/**
 * Implementación Eloquent del total de trabajos por contrato. Vive fuera de
 * `Infraestructura/Eloquent/` por el mismo motivo que
 * {@see LecturaContadoresPanelEloquent}: no es un modelo, es el adaptador
 * que el `ServiceProvider` liga al contrato.
 */
final class LecturaTrabajosPorContratoEloquent implements LecturaTrabajosPorContrato
{
    public function total(array $contratoIds): int
    {
        if ($contratoIds === []) {
            return 0;
        }

        $ordenIds = OrdenAplicacion::query()->whereIn('contrato_id', $contratoIds)->pluck('id');

        return Trabajo::query()->whereIn('orden_id', $ordenIds)->count();
    }
}
