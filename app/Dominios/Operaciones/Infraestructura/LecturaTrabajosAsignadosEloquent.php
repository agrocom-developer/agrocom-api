<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaTrabajosAsignados;
use App\Dominios\Operaciones\Contratos\TrabajoAsignadoCatalogo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent del contrato de lectura de trabajos asignados
 * (HU-70, tarea 85). Vive fuera de `Infraestructura/Eloquent/` a propósito,
 * mismo criterio que `LecturaOrdenesVigentesEloquent`: esa subcarpeta está
 * reservada a modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige), y esta clase no es un
 * modelo — es el adaptador que el `ServiceProvider` del módulo liga a
 * {@see LecturaTrabajosAsignados}.
 */
final class LecturaTrabajosAsignadosEloquent implements LecturaTrabajosAsignados
{
    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array
    {
        return Trabajo::query()
            ->whereNotNull('equipo_trabajo_id')
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
            ->map(fn (Trabajo $trabajo): TrabajoAsignadoCatalogo => new TrabajoAsignadoCatalogo(
                id: $trabajo->id,
                uuidCliente: $trabajo->uuid_cliente,
                ordenId: $trabajo->orden_id,
                loteId: $trabajo->lote_id,
                hectareasDeclaradas: $trabajo->hectareas_declaradas,
                equipoTrabajoId: (int) $trabajo->equipo_trabajo_id,
                humedadMinPct: $trabajo->humedad_min_pct,
                vientoMaxKmh: $trabajo->viento_max_kmh,
                temperaturaMaxC: $trabajo->temperatura_max_c,
                humedadMaxPct: $trabajo->humedad_max_pct,
                velocidadMaxKmh: $trabajo->velocidad_max_kmh,
                alturaVueloM: $trabajo->altura_vuelo_m,
                velocidadVueloKmh: $trabajo->velocidad_vuelo_kmh,
                anchoPasadaM: $trabajo->ancho_pasada_m,
                updatedAt: $trabajo->updated_at->toIso8601String(),
            ))
            ->all();
    }
}
