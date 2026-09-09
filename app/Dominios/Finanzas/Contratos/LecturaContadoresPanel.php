<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Frontera de lectura de Finanzas hacia el panel (ADR 0003, regla 2; TE-14,
 * tarea 60): el dato real que alimenta el badge `menu.financiero.items.devengos`
 * (`CascaraPanel`, módulo `Seguridad`) — nunca el modelo Eloquent
 * `DevengoPersonal` cruzando la frontera.
 */
interface LecturaContadoresPanel
{
    /**
     * Total devengado de `$personaId` en el mes calendario en curso (misma
     * fuente que `DevengosController::show` — invariante de CLAUDE.md: "lo
     * suyo", no el total de la empresa, porque hoy no existe una pantalla de
     * devengos por fuera del alcance de una persona).
     *
     * DECIMAL como string (invariante 6 de CLAUDE.md).
     */
    public function devengadoDelMes(int $personaId): string;
}
