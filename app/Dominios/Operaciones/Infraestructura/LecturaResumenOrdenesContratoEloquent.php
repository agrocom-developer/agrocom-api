<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaResumenOrdenesContrato;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;

/**
 * Implementación Eloquent del contrato de resumen de Órdenes de Aplicación
 * por contrato. Vive fuera de `Infraestructura/Eloquent/` por el mismo
 * motivo que {@see LecturaContadoresPanelEloquent}: no es un modelo, es el
 * adaptador que el `ServiceProvider` liga al contrato.
 */
final class LecturaResumenOrdenesContratoEloquent implements LecturaResumenOrdenesContrato
{
    public function resumen(array $contratoIds): array
    {
        if ($contratoIds === []) {
            return ['total' => 0, 'vigentes' => 0];
        }

        $consulta = OrdenAplicacion::query()->whereIn('contrato_id', $contratoIds);

        return [
            'total' => (clone $consulta)->count(),
            'vigentes' => (clone $consulta)->where('estado', EstadoOrdenAplicacion::Vigente)->count(),
        ];
    }
}
