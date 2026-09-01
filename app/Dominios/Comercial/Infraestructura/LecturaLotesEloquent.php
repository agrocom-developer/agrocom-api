<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Comercial\Contratos\LoteCatalogo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent del contrato de lectura de Comercial. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito: esa subcarpeta está reservada a
 * modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige con `toExtend`), y esta
 * clase no es un modelo — es el adaptador que el `ServiceProvider` del
 * módulo liga a {@see LecturaLotes}.
 */
final class LecturaLotesEloquent implements LecturaLotes
{
    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array
    {
        return Lote::query()
            ->when(
                $cursorActualizadoEn !== null && $cursorId !== null,
                fn (Builder $consulta) => $consulta->where(
                    fn (Builder $consulta) => $consulta
                        ->where('updated_at', '>', Carbon::parse($cursorActualizadoEn))
                        ->orWhere(
                            fn (Builder $consulta) => $consulta
                                ->where('updated_at', '=', Carbon::parse($cursorActualizadoEn))
                                ->where('id', '>', $cursorId),
                        ),
                ),
            )
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limite)
            ->get()
            ->map(fn (Lote $lote): LoteCatalogo => new LoteCatalogo(
                id: $lote->id,
                campoId: $lote->campo_id,
                codigo: $lote->codigo,
                hectareas: $lote->hectareas,
                geometria: $lote->geometria,
                restricciones: $lote->restricciones,
                updatedAt: $lote->updated_at->toIso8601String(),
            ))
            ->all();
    }
}
