<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Contrato de escritura de `Finanzas` para el cierre de una orden de
 * mantenimiento (HU-37, tarea 53; ADR 0003 regla 2). Primer contrato de
 * ESCRITURA cross-módulo de `Finanzas` — hasta esta tarea nadie externo
 * escribía `fin_gastos`; `Mantenimiento` nunca hace `Gasto::create()`
 * directo, solo invoca este método.
 *
 * Imputa siempre al rubro "Mantenimiento de equipos" / subrubro "Repuestos"
 * (sembrados por `FinanzasRubrosSeeder`, tarea 47) — no es un alta de gasto
 * genérica, es específica del cierre de una orden: por eso el rubro no viaja
 * como parámetro.
 */
interface EscrituraGastoMantenimiento
{
    /**
     * Crea el gasto de cierre por `$montoTotal` (invariante 6 de CLAUDE.md:
     * string decimal, nunca float) y devuelve su id, para que
     * `MaquinaEstadosOrdenMantenimiento::cerrar()` lo guarde en
     * `man_ordenes_mantenimiento.gasto_id`.
     */
    public function registrarPorCierreDeOrden(string $fecha, string $montoTotal): int;
}
