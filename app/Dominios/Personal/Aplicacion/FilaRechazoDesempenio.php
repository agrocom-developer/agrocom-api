<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Operaciones\Contratos\DatosRechazoDesempenio;

/**
 * Una fila de la lista de rechazos de la ficha de desempeño (HU-58, tarea
 * 81): copia de {@see DatosRechazoDesempenio} con `rechazadoPorPersonaId` ya
 * resuelto a nombre — `ObtenerDesempenioPersona` es quien puede tocar
 * `PerPersona` (propio del módulo), el contrato de lectura de `Operaciones`
 * no.
 */
final readonly class FilaRechazoDesempenio
{
    public function __construct(
        public int $sesionId,
        public string $fecha,
        public string $rol,
        public string $loteCodigo,
        public string $campoNombre,
        public int $clienteId,
        public string $clienteNombre,
        public ?int $campaniaId,
        public ?string $campaniaCodigo,
        public string $hectareasDeclaradas,
        public string $motivo,
        public string $rechazadoPorNombre,
    ) {}
}
