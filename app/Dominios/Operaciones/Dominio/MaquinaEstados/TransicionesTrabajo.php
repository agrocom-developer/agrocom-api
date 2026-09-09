<?php

namespace App\Dominios\Operaciones\Dominio\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoTrabajo;

/**
 * Tabla de transiciones permitidas para `trabajo` (invariante 7 de
 * CLAUDE.md). Reglas puras, sin Eloquent ni `Illuminate\Database`
 * (verificado por `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * Hoy solo hay un camino: `abierto → cerrado` (HU-05, todavía sin
 * implementar) — no hay vuelta atrás. La apertura misma no es una
 * "transición" en el sentido de esta tabla: es la creación del registro con
 * su estado inicial, que hace
 * `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo::abrir()` directamente.
 */
final class TransicionesTrabajo
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'abierto' => ['cerrado'],
        'cerrado' => [],
    ];

    public static function permitida(EstadoTrabajo $desde, EstadoTrabajo $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
