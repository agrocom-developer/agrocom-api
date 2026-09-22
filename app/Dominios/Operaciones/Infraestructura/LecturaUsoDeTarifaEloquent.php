<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaUsoDeTarifa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajoEquipo;

final class LecturaUsoDeTarifaEloquent implements LecturaUsoDeTarifa
{
    public function resumenDe(int $tarifaId): array
    {
        $consulta = OrdenTrabajoEquipo::query()->where('tarifa_id', $tarifaId);

        return [
            'equipos' => (clone $consulta)->count(),
            'ordenes' => (clone $consulta)->distinct()->count('orden_trabajo_id'),
            'negociados' => (clone $consulta)->where('negociado', true)->count(),
        ];
    }
}
