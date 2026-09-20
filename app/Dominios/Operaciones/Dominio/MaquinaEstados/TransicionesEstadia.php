<?php

namespace App\Dominios\Operaciones\Dominio\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoEstadia;

/**
 * Tabla de transiciones permitidas de una estadía en hacienda (invariante 7
 * de CLAUDE.md), mismo molde que `TransicionesOrden`/`TransicionesTrabajo`.
 * Reglas puras, sin Eloquent ni `Illuminate\Database` (verificado por
 * `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * Un solo camino: `en_curso → finalizada`, sin vuelta atrás — una estadía ya
 * finalizada cuenta días para justificar gastos, no se reabre (ver docblock
 * de `Aplicacion/ActualizarEstadiaHacienda`).
 */
final class TransicionesEstadia
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'en_curso' => ['finalizada'],
        'finalizada' => [],
    ];

    public static function permitida(EstadoEstadia $desde, EstadoEstadia $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
