<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Contratos\LecturaContadoresPanel;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;

/**
 * Implementación Eloquent del contrato de contadores del panel. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaContratoEloquent`: esa subcarpeta está reservada a modelos que extienden
 * `ModeloDominio`, y esta clase es el adaptador que el `ServiceProvider` liga a
 * {@see LecturaContadoresPanel}. El soft delete queda fuera por el global scope.
 */
final class LecturaContadoresPanelEloquent implements LecturaContadoresPanel
{
    public function contratosEnEjecucion(): int
    {
        return Contrato::query()
            ->where('estado', EstadoContrato::Vigente)
            ->count();
    }
}
