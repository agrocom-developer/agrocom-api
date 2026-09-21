<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;

/**
 * Caso de uso de LECTURA: cuántos planes de mantenimiento preventivo aplican a
 * un modelo de dron, para el resumen relacionado de la ficha de inventario de
 * un dron.
 *
 * El plan se cruza con el dron por IGUALDAD DE TEXTO entre
 * `man_planes_mantenimiento.modelo` y `ope_drones.modelo` (ver el docblock de
 * {@see PlanMantenimiento}), nunca por FK: por eso recibe el modelo y no un id.
 * Un dron sin modelo no tiene planes que le apliquen. El soft delete de
 * `ModeloDominio` deja afuera los planes dados de baja.
 */
final class ContarPlanesDeModelo
{
    public function ejecutar(?string $modelo): int
    {
        if ($modelo === null || $modelo === '') {
            return 0;
        }

        return PlanMantenimiento::query()->where('modelo', $modelo)->count();
    }
}
