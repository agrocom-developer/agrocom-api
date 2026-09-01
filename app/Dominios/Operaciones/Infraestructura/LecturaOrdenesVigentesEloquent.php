<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaOrdenesVigentes;
use App\Dominios\Operaciones\Contratos\OrdenAplicacionCatalogo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent del contrato de lectura de Operaciones. Vive fuera
 * de `Infraestructura/Eloquent/` a propósito: esa subcarpeta está reservada a
 * modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige con `toExtend`), y esta
 * clase no es un modelo — es el adaptador que el `ServiceProvider` del
 * módulo liga a {@see LecturaOrdenesVigentes}.
 */
final class LecturaOrdenesVigentesEloquent implements LecturaOrdenesVigentes
{
    public function listarModificadosDesde(?string $cursorActualizadoEn, ?int $cursorId, int $limite): array
    {
        return OrdenAplicacion::query()
            ->where('estado', EstadoOrdenAplicacion::Vigente)
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
            ->map(fn (OrdenAplicacion $orden): OrdenAplicacionCatalogo => new OrdenAplicacionCatalogo(
                id: $orden->id,
                contratoId: $orden->contrato_id,
                loteId: $orden->lote_id,
                nroAplicacion: $orden->nro_aplicacion,
                litrosHa: $orden->litros_ha,
                humedadMinPct: $orden->humedad_min_pct,
                vientoMaxKmh: $orden->viento_max_kmh,
                temperaturaMaxC: $orden->temperatura_max_c,
                humedadMaxPct: $orden->humedad_max_pct,
                velocidadMaxKmh: $orden->velocidad_max_kmh,
                alturaVueloM: $orden->altura_vuelo_m,
                velocidadVueloKmh: $orden->velocidad_vuelo_kmh,
                anchoPasadaM: $orden->ancho_pasada_m,
                observaciones: $orden->observaciones,
                emitidaPorContactoId: $orden->emitida_por_contacto_id,
                fechaEmision: $orden->fecha_emision->toDateString(),
                estado: $orden->estado->value,
                updatedAt: $orden->updated_at->toIso8601String(),
            ))
            ->all();
    }
}
