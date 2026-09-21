<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use Illuminate\Database\Eloquent\Builder;

/**
 * Caso de uso de LECTURA: cuántas órdenes de mantenimiento acumulan VARIOS
 * drones y cuántas siguen abiertas, para el resumen relacionado de la ficha
 * de un plan de mantenimiento preventivo (tarea 116).
 *
 * Hermano de {@see ContarOrdenesDeEquipo}, que cuenta las de un equipo solo.
 * Acá los ids llegan de afuera —los drones cuyo modelo coincide con el del
 * plan, resueltos por el contrato de lectura de `Operaciones`— porque el
 * cruce plan↔dron es por IGUALDAD DE TEXTO del modelo y no por FK (ver el
 * docblock de `Operaciones\Contratos\LecturaHorasVueloPorModelo`).
 *
 * Sin ids no hay nada que contar: devuelve ceros sin consultar. El soft
 * delete de `ModeloDominio` deja afuera las órdenes dadas de baja.
 */
final class ContarOrdenesDeDrones
{
    /**
     * @param  list<int>  $dronIds
     * @return array{abiertas: int, total: int}
     */
    public function ejecutar(array $dronIds): array
    {
        if ($dronIds === []) {
            return ['abiertas' => 0, 'total' => 0];
        }

        return [
            'abiertas' => $this->ordenesDe($dronIds)->where('estado', EstadoOrdenMantenimiento::Abierta)->count(),
            'total' => $this->ordenesDe($dronIds)->count(),
        ];
    }

    /**
     * @param  list<int>  $dronIds
     * @return Builder<OrdenMantenimiento>
     */
    private function ordenesDe(array $dronIds): Builder
    {
        return OrdenMantenimiento::query()
            ->where('equipo_tipo', ContarOrdenesDeEquipo::TIPO_DRON)
            ->whereIn('equipo_id', $dronIds);
    }
}
