<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma primitiva de una evidencia para la galería multimedia del dashboard
 * (ADR 0003, regla 2).
 *
 * `url` viene resuelta como la ruta de streaming del panel
 * (`panel.evidencias.archivo`), nunca como `archivo_url`: esa columna es una
 * ruta privada del disco `r2`, no una URL pública — mismo criterio que
 * `TrabajosController::evidenciaArchivo()`. Resolverla acá y no en la vista
 * mantiene el conocimiento de ese disco dentro de Operaciones.
 *
 * `loteId` viaja pelado por el mismo motivo que en {@see SesionPanel}.
 */
final readonly class EvidenciaPanel
{
    public function __construct(
        public int $id,
        public string $tipo,
        public string $url,
        public string $fecha,
        public ?int $loteId,
        public ?int $trabajoId,
    ) {}
}
