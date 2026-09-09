<?php

namespace App\Dominios\Campania\Dominio\MaquinaEstados;

use App\Dominios\Campania\Dominio\EstadoCampania;

/**
 * Tabla de transiciones permitidas para `campania` (invariante 7 de
 * CLAUDE.md; ADR 0015 punto 1): `planificada → abierta → cerrada`, sin
 * vuelta atrás desde `cerrada` — reabrir una campaña cerrada es el agujero
 * por el que se cuelan gastos e imputaciones retroactivas que descuadran un
 * cierre ya presentado (ADR 0015). Reglas puras, sin Eloquent ni
 * `Illuminate\Database` (verificado por `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * `planificada` es el único estado de alta — lo fija
 * `Aplicacion/MaquinaEstados/MaquinaEstadosCampania::crear()` directamente,
 * nunca es destino de una transición, mismo criterio que `TransicionesContrato`
 * con `borrador`. No hay salto directo `planificada → cerrada`: el ADR
 * describe la cadena como secuencial, y saltarse `abierta` dejaría cerrar una
 * campaña que nunca imputó nada.
 */
final class TransicionesCampania
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'planificada' => ['abierta'],
        'abierta' => ['cerrada'],
        'cerrada' => [],
    ];

    public static function permitida(EstadoCampania $desde, EstadoCampania $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
