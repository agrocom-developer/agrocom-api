<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use Illuminate\Database\Eloquent\Builder;

/**
 * Caso de uso de LECTURA: cuántas órdenes de mantenimiento tiene un equipo y
 * cuántas siguen abiertas, para el resumen relacionado de su ficha (vehículo
 * o ficha de dron).
 *
 * `equipo_tipo` + `equipo_id` es una referencia polimórfica sin FK (ver el
 * docblock de {@see OrdenMantenimiento}): apunta a `man_vehiculos.id` o a
 * `ope_drones.id` según el tipo, así que la consulta recibe los dos datos. El
 * soft delete de `ModeloDominio` deja afuera las órdenes dadas de baja.
 */
final class ContarOrdenesDeEquipo
{
    public const TIPO_VEHICULO = 'vehiculo';

    public const TIPO_DRON = 'dron';

    /**
     * @param  self::TIPO_*  $equipoTipo
     * @return array{abiertas: int, total: int}
     */
    public function ejecutar(string $equipoTipo, int $equipoId): array
    {
        return [
            'abiertas' => $this->ordenesDe($equipoTipo, $equipoId)->where('estado', EstadoOrdenMantenimiento::Abierta)->count(),
            'total' => $this->ordenesDe($equipoTipo, $equipoId)->count(),
        ];
    }

    /** @return Builder<OrdenMantenimiento> */
    private function ordenesDe(string $equipoTipo, int $equipoId): Builder
    {
        return OrdenMantenimiento::query()
            ->where('equipo_tipo', $equipoTipo)
            ->where('equipo_id', $equipoId);
    }
}
