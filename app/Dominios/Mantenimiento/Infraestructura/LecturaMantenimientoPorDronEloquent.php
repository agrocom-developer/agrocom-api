<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\DatosMantenimientoDron;
use App\Dominios\Mantenimiento\Contratos\LecturaMantenimientoPorDron;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaMantenimientoPorDron}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaEquipamientoPorBaseEloquent`: esa subcarpeta es solo para modelos.
 *
 * Solo cuenta filas: el soft delete de `ModeloDominio` ya deja afuera lo que
 * se dio de baja. El índice único parcial de `man_drones` garantiza a lo sumo
 * una ficha viva por identificador.
 */
final class LecturaMantenimientoPorDronEloquent implements LecturaMantenimientoPorDron
{
    private const TIPO_EQUIPO_DRON = 'dron';

    public function deDron(int $dronId, string $identificador): DatosMantenimientoDron
    {
        $ficha = FichaDron::query()->where('identificador_dron', $identificador)->first();

        return new DatosMantenimientoDron(
            fichaId: $ficha?->id,
            numeroSerie: $ficha?->numero_serie,
            versionSoftware: $ficha?->version_software,
            ordenesAbiertas: $this->ordenesDe($dronId)->where('estado', EstadoOrdenMantenimiento::Abierta)->count(),
            ordenesTotal: $this->ordenesDe($dronId)->count(),
        );
    }

    /** @return Builder<OrdenMantenimiento> */
    private function ordenesDe(int $dronId): Builder
    {
        return OrdenMantenimiento::query()
            ->where('equipo_tipo', self::TIPO_EQUIPO_DRON)
            ->where('equipo_id', $dronId);
    }
}
