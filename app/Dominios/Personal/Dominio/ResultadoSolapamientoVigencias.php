<?php

namespace App\Dominios\Personal\Dominio;

/**
 * Resultado de {@see ValidadorSolapamientoVigencias::evaluar()}: o la
 * vigencia nueva se rechaza (misma persona o mismo recurso dentro del MISMO
 * equipo, con vigencias que se pisan — es la misma fila dos veces), o se
 * guarda con cero o más equipos para avisar (el mismo sujeto vigente en
 * OTRO equipo en fechas que se pisan — préstamo real entre cuadrillas, ADR
 * 0015 punto 3).
 */
final readonly class ResultadoSolapamientoVigencias
{
    /** @param list<int> $equiposEnAviso */
    public function __construct(
        public bool $rechazada,
        public array $equiposEnAviso,
    ) {}

    public function tieneAviso(): bool
    {
        return $this->equiposEnAviso !== [];
    }
}
