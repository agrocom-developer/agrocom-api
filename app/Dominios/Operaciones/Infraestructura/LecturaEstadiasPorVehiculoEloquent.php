<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosEstadiasVehiculo;
use App\Dominios\Operaciones\Contratos\LecturaEstadiasPorVehiculo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaEstadiasPorVehiculo}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaSesionesPorPersonaEloquent`: esa subcarpeta es solo para modelos.
 * El soft delete de `ModeloDominio` deja afuera las estadías dadas de baja.
 */
final class LecturaEstadiasPorVehiculoEloquent implements LecturaEstadiasPorVehiculo
{
    public function deVehiculo(int $vehiculoId): DatosEstadiasVehiculo
    {
        return new DatosEstadiasVehiculo(
            total: $this->estadiasDe($vehiculoId)->count(),
            enCurso: $this->estadiasDe($vehiculoId)->whereNull('salida')->count(),
        );
    }

    /** @return Builder<EstadiaHacienda> */
    private function estadiasDe(int $vehiculoId): Builder
    {
        return EstadiaHacienda::query()->where('vehiculo_id', $vehiculoId);
    }
}
