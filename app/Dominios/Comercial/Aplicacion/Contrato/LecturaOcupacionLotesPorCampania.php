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
     * Los contratos que pasarían a `conflicto` si `$contrato` se aprobara ahora
     * (ADR 0021): los `borrador` de su misma campaña que comparten al menos un
     * lote con él, cada uno con los códigos de los lotes que comparten. Es lo
     * que el panel muestra ANTES de confirmar la aprobación, para que quien
     * aprueba sepa qué contratos van a quedar en conflicto y decida después si
     * los cancela o les quita esos lotes. Solo lectura: quien los mueve de
     * estado, al aprobar, es `MaquinaEstadosContrato::reconciliarConflictos()`.
     *
     * Los `conflicto` que ya chocan con otro contrato aprobado no se listan:
     * ya están marcados y no cambian por esta aprobación.
     *
     * @return list<array{contrato: Contrato, lotes: list<string>}>
     */
    public static function contratosQueEntranEnConflicto(Contrato $contrato): array
    {
        if ($contrato->campania_id === null) {
            return [];
        }

        $propios = $contrato->lotes()->pluck('lote_id')->all();

        if ($propios === []) {
            return [];
        }

        $filas = ContratoLote::query()
            ->whereIn('lote_id', $propios)
            ->whereHas('contrato', function (Builder $query) use ($contrato): void {
                $query->where('campania_id', $contrato->campania_id)
                    ->where('estado', EstadoContrato::Borrador->value)
                    ->whereKeyNot($contrato->id);
            })
            ->with(['contrato.cliente', 'lote:id,codigo'])
            ->orderBy('contrato_id')
            ->get(['id', 'contrato_id', 'lote_id']);

        $porContrato = [];
        foreach ($filas as $fila) {
            $porContrato[$fila->contrato_id] ??= ['contrato' => $fila->contrato, 'lotes' => []];
            $porContrato[$fila->contrato_id]['lotes'][] = (string) $fila->lote?->codigo;
        }

        return array_values($porContrato);
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
