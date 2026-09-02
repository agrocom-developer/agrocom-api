<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosAlerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;

/**
 * Caso de uso "el encargado marca una alerta como atendida" (HU-19, tarea
 * 26): delgado a propósito — la mutación de `estado` (con su idempotencia)
 * vive en {@see MaquinaEstadosAlerta::atender()} (invariante 7 de
 * CLAUDE.md), nunca acá.
 */
final class AtenderAlerta
{
    public function __construct(private readonly MaquinaEstadosAlerta $maquina) {}

    public function ejecutar(Alerta $alerta, int $personaId): Alerta
    {
        return $this->maquina->atender($alerta, $personaId);
    }
}
