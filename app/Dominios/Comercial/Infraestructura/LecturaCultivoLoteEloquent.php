<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Contratos\CultivoLotePorCampania;
use App\Dominios\Comercial\Contratos\LecturaCultivoLote;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent del contrato de lectura de siembra por campaña.
 * Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaLotesEloquent`: esa subcarpeta está reservada a modelos que
 * extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige), y esta clase no es un modelo — es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaCultivoLote}.
 */
final class LecturaCultivoLoteEloquent implements LecturaCultivoLote
{
    public function porCampania(int $campaniaId): array
    {
        return $this->comoDatos(LoteCampania::query()->where('campania_id', $campaniaId));
    }

    public function deLotes(array $loteIds, array $campaniaIds): array
    {
        if ($loteIds === [] || $campaniaIds === []) {
            return [];
        }

        return $this->comoDatos(LoteCampania::query()->whereIn('lote_id', $loteIds)->whereIn('campania_id', $campaniaIds));
    }

    /**
     * @param  Builder<LoteCampania>  $consulta
     * @return list<CultivoLotePorCampania>
     */
    private function comoDatos(Builder $consulta): array
    {
        return $consulta
            ->with(['lote', 'cultivo'])
            ->get()
            ->map(fn (LoteCampania $siembra): CultivoLotePorCampania => new CultivoLotePorCampania(
                loteId: $siembra->lote_id,
                loteCodigo: $siembra->lote->codigo,
                cultivoId: $siembra->cultivo_id,
                cultivoNombre: $siembra->cultivo->nombre_comun,
                hectareasSembradas: (string) $siembra->hectareas_sembradas,
                campaniaId: $siembra->campania_id,
                etapa: $siembra->etapa_cultivo?->value,
            ))
            ->values()
            ->all();
    }
}
