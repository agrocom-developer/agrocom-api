<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia el panel (ADR 0003, regla 2;
 * TE-14, tarea 60): los contadores reales que alimentan los badges del menú
 * lateral (`CascaraPanel`, módulo `Seguridad`) para los tres ítems que este
 * módulo posee — nunca el modelo Eloquent cruzando la frontera.
 */
interface LecturaContadoresPanel
{
    /** Órdenes de aplicación en estado `vigente` (mismo filtro que `ListarOrdenesAplicacion::$soloVigentes`). */
    public function ordenesVigentes(): int;

    /** Sesiones `cerrado` sin anular, pendientes en la cola de `ValidacionSesionesController`. */
    public function sesionesPendientesValidacion(): int;

    /**
     * Pausas registradas en el mes calendario en curso.
     *
     * @return array{cantidad: int, totalMinutos: int}
     */
    public function pausasDelMes(): array;
}
