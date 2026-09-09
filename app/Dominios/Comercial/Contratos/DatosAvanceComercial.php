<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva del avance de UN contrato — misma fila que agrega
 * `Aplicacion\ObtenerAvanceComercial` (HU-32, tarea 46), tipada para quien no
 * puede depender de esa clase de `Aplicacion/` (ADR 0003, regla 2): hoy,
 * `Portal` (HU-41, tarea 55, vía {@see LecturaAvanceComercial}), que solo
 * necesita el avance del contrato de la sesión, nunca el reporte completo.
 *
 * Todas las magnitudes son string decimal (invariante 6 de CLAUDE.md).
 */
final readonly class DatosAvanceComercial
{
    public function __construct(
        public int $contratoId,
        public string $clienteNombre,
        public string $hectareasContratadas,
        public string $hectareasAplicadas,
        public string $hectareasFacturadas,
        public string $montoFacturado,
    ) {}
}
