<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaLotesConOrdenPorContrato;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote;

/**
 * Implementación Eloquent de "lotes con orden de aplicación, por contrato".
 * Vive fuera de `Infraestructura/Eloquent/` por el mismo motivo que
 * {@see LecturaTrabajosPorContratoEloquent}: no es un modelo, es el
 * adaptador que el `ServiceProvider` liga al contrato.
 */
final class LecturaLotesConOrdenPorContratoEloquent implements LecturaLotesConOrdenPorContrato
{
    public function loteIds(array $contratoIds): array
    {
        if ($contratoIds === []) {
            return [];
        }

        $filas = OrdenLote::query()
            ->whereHas('orden', fn ($query) => $query->whereIn('contrato_id', $contratoIds))
            ->with(['orden:id,contrato_id'])
            ->get(['id', 'orden_id', 'lote_id']);

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[$fila->orden->contrato_id][] = $fila->lote_id;
        }

        foreach ($resultado as $contratoId => $loteIds) {
            $resultado[$contratoId] = array_values(array_unique($loteIds));
        }

        return $resultado;
    }
}
