<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Personal\Contratos\PersonaCatalogo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent del contrato de lectura de Personal. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito: esa subcarpeta está reservada a
 * modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige con `toExtend`), y esta
 * clase no es un modelo — es el adaptador que el `ServiceProvider` del
 * módulo liga a {@see LecturaPersonas}.
 */
final class LecturaPersonasEloquent implements LecturaPersonas
{
    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array
    {
        return PerPersona::query()
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
            ->map(fn (PerPersona $persona): PersonaCatalogo => new PersonaCatalogo(
                id: $persona->id,
                nombre: $persona->nombre,
                rol: $persona->rol->value,
                baseId: $persona->base_id,
                activo: $persona->activo,
                updatedAt: $persona->updated_at->toIso8601String(),
            ))
            ->all();
    }
}
