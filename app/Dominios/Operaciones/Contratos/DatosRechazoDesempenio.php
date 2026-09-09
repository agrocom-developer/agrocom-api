<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Una sesión de la persona consultada que fue RECHAZADA (tarea 81, HU-58;
 * invariante 2 de CLAUDE.md: el rechazo es un registro nuevo —
 * `ope_sesion_rechazos` — no un `UPDATE` sobre la sesión original). Misma
 * resolución de cliente/campaña/lote que {@see DatosSesionDesempenio}, a
 * propósito: la ficha muestra el mismo contexto en ambas listas.
 *
 * `rechazadoPorPersonaId` queda sin resolver a nombre acá: `per_personas` es
 * de `Personal` (ADR 0003, regla 3) — el consumidor, que YA es `Personal`, lo
 * resuelve con su propio modelo sin cruzar ninguna frontera.
 */
final readonly class DatosRechazoDesempenio
{
    public function __construct(
        public int $sesionId,
        public string $fecha,
        public string $rol,
        public int $loteId,
        public string $loteCodigo,
        public string $campoNombre,
        public int $clienteId,
        public string $clienteNombre,
        public ?int $campaniaId,
        public ?string $campaniaCodigo,
        public string $hectareasDeclaradas,
        public string $motivo,
        public int $rechazadoPorPersonaId,
    ) {}
}
