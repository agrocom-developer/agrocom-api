<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Carga actual de un equipo de trabajo: los trabajos que tiene abiertos
 * ahora mismo (ADR 0003, regla 2; tarea 135). `equipoTrabajoId` referencia
 * `per_equipos_trabajo` (otro módulo, FK plana) — el nombre del equipo lo
 * resuelve quien llama vía `Personal\Contratos\LecturaEquipoTrabajo`, mismo
 * criterio que `Seguridad\Aplicacion\ArmarDashboard::diasEnHacienda()`.
 *
 * `loteIds` son los lotes con algún trabajo abierto de este equipo, sin
 * repetir — el nombre de cada uno lo resuelve `Comercial` por su contrato.
 *
 * `hectareasDeclaradas` es string decimal (invariante 6): la suma de
 * `Trabajo::hectareas_declaradas` de los trabajos abiertos, acumulada con
 * `BigDecimal` en PHP.
 */
final readonly class ResumenEquipoTrabajoPanel
{
    /** @param  list<int>  $loteIds */
    public function __construct(
        public int $equipoTrabajoId,
        public int $trabajosAbiertos,
        public string $hectareasDeclaradas,
        public array $loteIds,
        public ?string $ultimoInicio,
    ) {}
}
