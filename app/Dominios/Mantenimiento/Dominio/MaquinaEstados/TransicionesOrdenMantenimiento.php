<?php

namespace App\Dominios\Mantenimiento\Dominio\MaquinaEstados;

use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;

/**
 * Tabla de transiciones permitidas para `man_ordenes_mantenimiento.estado`
 * (invariante 7 de CLAUDE.md; HU-37, tarea 53). Reglas puras, sin Eloquent
 * ni `Illuminate\Database` (verificado por
 * `tests/Unit/ArquitecturaModulosTest.php`), mismo criterio que
 * `TransicionesOrden` en Operaciones.
 *
 * `abierta` es el único estado de alta — lo fija
 * `Aplicacion/MaquinaEstados/MaquinaEstadosOrdenMantenimiento::abrir()`
 * directamente, nunca es destino de una transición. Desde `abierta`, la
 * única salida es `cerrada` (con la guarda de "repuestos disponibles", ver
 * `MaquinaEstadosOrdenMantenimiento::cerrar()`). `cerrada` no tiene salida:
 * no existe una transición de reapertura en el alcance de esta HU.
 */
final class TransicionesOrdenMantenimiento
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'abierta' => ['cerrada'],
        'cerrada' => [],
    ];

    public static function permitida(EstadoOrdenMantenimiento $desde, EstadoOrdenMantenimiento $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
