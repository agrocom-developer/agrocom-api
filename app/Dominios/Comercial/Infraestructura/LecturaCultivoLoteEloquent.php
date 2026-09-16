<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Contratos\CultivoLotePorCampania;
use App\Dominios\Comercial\Contratos\LecturaCultivoLote;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;

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
        return LoteCampania::query()
            ->where('campania_id', $campaniaId)
            ->with(['lote', 'cultivo'])
            ->get()
            ->map(fn (LoteCampania $siembra): CultivoLotePorCampania => new CultivoLotePorCampania(
                loteId: $siembra->lote_id,
                loteCodigo: $siembra->lote->codigo,
                cultivoId: $siembra->cultivo_id,
                cultivoNombre: $siembra->cultivo->nombre_comun,
                hectareasSembradas: (string) $siembra->hectareas_sembradas,
            ))
            ->all();
    }
}
