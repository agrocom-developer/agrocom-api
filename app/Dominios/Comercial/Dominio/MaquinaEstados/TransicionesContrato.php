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
 * el cierre real por consumo de hectáreas es de `Operaciones`), a
 * `cancelado` (baja anticipada) o a `pausado` (HU-71, tarea 87: interrupción
 * del contrato vigente, no una cancelación). Los dos estados terminales
 * (`finalizado`, `cancelado`) no admiten ninguna salida. `pausado` solo
 * vuelve a `vigente` — el CA de HU-71 pide únicamente el ida y vuelta con
 * `vigente`, no `pausado → cancelado` ni `pausado → finalizado`.
 */
final class TransicionesContrato
{
    /** @var array<string, list<string>> */
    private const array PERMITIDAS = [
        'borrador' => ['vigente', 'cancelado'],
        'vigente' => ['finalizado', 'cancelado', 'pausado'],
        'finalizado' => [],
        'cancelado' => [],
        'pausado' => ['vigente'],
    ];

    public static function permitida(EstadoContrato $desde, EstadoContrato $hacia): bool
    {
        return in_array($hacia->value, self::PERMITIDAS[$desde->value], true);
    }
}
