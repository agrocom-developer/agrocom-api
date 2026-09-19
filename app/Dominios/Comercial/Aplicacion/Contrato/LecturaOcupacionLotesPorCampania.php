<?php

namespace App\Dominios\Comercial\Aplicacion\Contrato;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoLote;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lectura pura sobre qué lotes ya están retenidos por OTROS contratos
 * (`vigente` o `pausado`, ver `EstadoContrato::retieneLotes()`; pedido del
 * dueño, tarea "contrato-lotes-conflicto", 18/9/2026) — para pintar la UI
 * (excluir del modal de selección, marcar en alert dentro del contrato en
 * edición), nunca para decidir si un guardado se bloquea: esa guarda es,
 * únicamente, {@see VerificadorLotesDelContrato::lotesOcupados()} (ADR 0021).
 * Las dos leen el MISMO criterio de "retenido", así que el modal nunca
 * ofrece un lote que el servidor va a rechazar.
 *
 * Clase hermana de `VerificadorLotesDelContrato`, no una ampliación de ella:
 * esa gobierna el guardado, esta es de solo lectura para la UI — separarlas
 * evita acoplar el path crítico de guardado a una necesidad de pantalla.
 */
final class LecturaOcupacionLotesPorCampania
{
    /**
     * Por cada lote, en qué campañas ya está retenido por OTRO contrato
     * `vigente` o `pausado` (excluyendo `$contratoIdExcluido` — el propio contrato en
     * edición, o `null` en un alta).
     *
     * @return array<int, list<int>> lote_id => campania_ids
     */
    public static function porCampania(?int $contratoIdExcluido): array
    {
        $filas = ContratoLote::query()
            ->whereHas('contrato', function (Builder $query) use ($contratoIdExcluido): void {
                $query->whereIn('estado', EstadoContrato::valoresQueRetienenLotes());

                if ($contratoIdExcluido !== null) {
                    $query->whereKeyNot($contratoIdExcluido);
                }
            })
            ->with(['contrato:id,campania_id'])
            ->get(['id', 'contrato_id', 'lote_id']);

        $resultado = [];
        foreach ($filas as $fila) {
            $campaniaId = $fila->contrato->campania_id;

            if ($campaniaId === null) {
                continue;
            }

            $resultado[$fila->lote_id][] = $campaniaId;
        }

        foreach ($resultado as $loteId => $campaniaIds) {
            $resultado[$loteId] = array_values(array_unique($campaniaIds));
        }

        return $resultado;
    }

    /**
     * Para los lotes que YA están en el contrato que se edita: el OTRO
     * contrato que los retiene (`vigente` o `pausado`) en la misma campaña,
     * con sus relaciones cargadas para armar el modal informativo de
     * conflicto. Si un lote choca con más de un contrato ajeno (caso raro:
     * datos previos al ADR 0021), se queda con el primero.
     *
     * @param  list<int>  $loteIds
     * @return array<int, Contrato> lote_id => el otro contrato en conflicto
     *                              (con `cliente` y `lotes.lote.propiedad` cargados)
     */
    public static function contratosEnConflicto(array $loteIds, int $campaniaId, int $contratoIdExcluido): array
    {
        if ($loteIds === []) {
            return [];
        }

        $filas = ContratoLote::query()
            ->whereIn('lote_id', $loteIds)
            ->whereHas('contrato', function (Builder $query) use ($campaniaId, $contratoIdExcluido): void {
                $query->whereIn('estado', EstadoContrato::valoresQueRetienenLotes())
                    ->where('campania_id', $campaniaId)
                    ->whereKeyNot($contratoIdExcluido);
            })
            ->with(['contrato.cliente', 'contrato.lotes.lote.propiedad'])
            ->get(['id', 'contrato_id', 'lote_id']);

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[$fila->lote_id] ??= $fila->contrato;
        }

        return $resultado;
    }
}
