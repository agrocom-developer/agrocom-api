<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Los drones que una persona operó en el mes, con lo que hizo con cada uno
 * (ADR 0003, regla 2).
 *
 * No sale de una tabla de asignación —no existe: `ope_drones` no guarda
 * responsable— sino de las sesiones: el equipo del que alguien responde es
 * el que voló. Derivarlo así evita inventar una entidad de "asignación" que
 * el negocio todavía no tiene.
 */
final readonly class EquipoPersonaPanel
{
    public function __construct(
        public int $dronId,
        public string $identificador,
        public ?string $modelo,
        public int $sesiones,
        public string $hectareas,
        public int $minutosVuelo,
        public ?string $ultimoVuelo,
    ) {}
}
