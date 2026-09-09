<?php

namespace App\Dominios\Comercial\Dominio\MaquinaEstados;

use App\Dominios\Comercial\Dominio\EstadoContrato;

/**
 * Tabla de transiciones permitidas para `contrato` (invariante 7 de
 * CLAUDE.md; espec §4.1, HU-23, tarea 34). Reglas puras, sin Eloquent ni
 * `Illuminate\Database` (verificado por `tests/Unit/ArquitecturaModulosTest.php`).
 *
 * `borrador` es el único estado de alta — lo fija
 * `Aplicacion/MaquinaEstados/MaquinaEstadosContrato::crear()` directamente,
 * nunca es destino de una transición (mismo criterio que `TransicionesActa`
 * con `pendiente`). Desde `borrador`: a `vigente` (con guarda de negocio, ver
 * `MaquinaEstadosContrato::activar()`) o a `cancelado` (baja anticipada antes
 * de vigenciar). Desde `vigente`: a `finalizado` (sin guarda de esta tarea —
 * el cierre real por consumo de hectáreas es de `Operaciones`) o a
 * `cancelado` (baja anticipada). Los dos estados terminales (`finalizado`,
 * `cancelado`) no admiten ninguna salida.
 */
final class TransicionesContrato
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'borrador' => ['vigente', 'cancelado'],
        'vigente' => ['finalizado', 'cancelado'],
        'finalizado' => [],
        'cancelado' => [],
    ];

    public static function permitida(EstadoContrato $desde, EstadoContrato $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
