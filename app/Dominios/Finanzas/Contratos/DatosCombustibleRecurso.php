<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Lo que Finanzas sabe del combustible cargado a un recurso (vehículo o
 * generador), para el resumen relacionado de su ficha en `Mantenimiento` (ADR
 * 0003, regla 2): cuántas cargas se le registraron y cuántos litros suman. No
 * trae montos: la ficha de un equipo no es un reporte de gasto.
 */
final readonly class DatosCombustibleRecurso
{
    /** @param string $litros DECIMAL como `string`, con dos decimales (invariante 6 de CLAUDE.md). */
    public function __construct(
        public int $cargas,
        public string $litros,
    ) {}
}
