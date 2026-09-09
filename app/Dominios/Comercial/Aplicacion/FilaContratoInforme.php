<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\TramoAvance;

/**
 * Una fila de contrato dentro de un grupo del informe de avance (HU-52,
 * tarea 75, espec §9.1). Todas las magnitudes son string decimal
 * (invariante 6). `hectareasAAplicar` es la resta literal (pactadas menos
 * aplicadas, tal cual la pide la espec) — puede dar negativa cuando se
 * aplicó de más, que es exactamente el caso que cae en `TramoAvance::MasDeCien`.
 */
final readonly class FilaContratoInforme
{
    public function __construct(
        public int $contratoId,
        public int $clienteId,
        public string $clienteNombre,
        public string $hectareasContratadas,
        public string $hectareasAplicadas,
        public string $hectareasAAplicar,
        public int $porcentaje,
        public TramoAvance $tramo,
        public ?string $fechaFin,
    ) {}
}
