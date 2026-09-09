<?php

namespace App\Dominios\Operaciones\Dominio\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoActa;

/**
 * Tabla de transiciones permitidas para `acta` (invariante 7 de CLAUDE.md).
 * Reglas puras, sin Eloquent ni `Illuminate\Database` (verificado por
 * `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * Un único camino: `pendiente → firmada`, sin vuelta atrás. La generación
 * misma no es una "transición" en el sentido de esta tabla: es la creación
 * del registro con su estado inicial, que hace
 * `Aplicacion/MaquinaEstados/MaquinaEstadosActa::generar()` directamente
 * (mismo criterio que `TransicionesTrabajo`/`abrir()`).
 */
final class TransicionesActa
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'pendiente' => ['firmada'],
        'firmada' => [],
    ];

    public static function permitida(EstadoActa $desde, EstadoActa $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
