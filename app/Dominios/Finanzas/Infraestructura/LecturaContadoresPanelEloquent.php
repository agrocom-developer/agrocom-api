<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Aplicacion\ListarDevengosPersona;
use App\Dominios\Finanzas\Contratos\LecturaContadoresPanel;

/**
 * Implementación del contrato de contadores de panel de Finanzas (TE-14,
 * tarea 60). Delega en {@see ListarDevengosPersona} — no reimplementa la
 * suma con `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md).
 */
final class LecturaContadoresPanelEloquent implements LecturaContadoresPanel
{
    public function __construct(private readonly ListarDevengosPersona $listarDevengos) {}

    public function devengadoDelMes(int $personaId): string
    {
        return $this->listarDevengos->ejecutar($personaId)['total'];
    }
}
