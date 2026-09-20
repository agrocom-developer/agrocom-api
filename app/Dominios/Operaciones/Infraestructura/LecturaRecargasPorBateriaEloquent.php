<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosRecargasBateria;
use App\Dominios\Operaciones\Contratos\LecturaRecargasPorBateria;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Recarga;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaRecargasPorBateria}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaAlertasTemperaturaBateriaEloquent`: esa subcarpeta es solo para
 * modelos. Solo cuenta filas: el soft delete de `ModeloDominio` deja afuera
 * las recargas dadas de baja.
 */
final class LecturaRecargasPorBateriaEloquent implements LecturaRecargasPorBateria
{
    public function deBateria(string $identificador): DatosRecargasBateria
    {
        return new DatosRecargasBateria(
            total: $this->recargasDe($identificador)->count(),
            conAlertaTemperatura: $this->recargasDe($identificador)->where('alerta_temperatura', true)->count(),
        );
    }

    /** @return Builder<Recarga> */
    private function recargasDe(string $identificador): Builder
    {
        return Recarga::query()->where('bateria_saliente_id', $identificador);
    }
}
