<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma primitiva de un lote para el panel (ADR 0003, regla 2), con lo que
 * el dashboard necesita para nombrarlo y dibujarlo: su código, de qué campo
 * y cliente es, cuántas hectáreas tiene y su perímetro.
 *
 * Se diferencia de {@see LoteCatalogo} (pull de catálogo hacia la app de
 * campo) en que trae el campo y el cliente ya resueltos: son relaciones
 * INTERNAS de este módulo, así que resolverlas acá no cruza ninguna
 * frontera y le ahorra al consumidor dos consultas más.
 *
 * `geometria` es el GeoJSON crudo de `com_lotes.geometria` (`Polygon`), o
 * `null` si el lote todavía no lo tiene cargado: el mapa dibuja los que sí
 * y no inventa los que no.
 */
final readonly class LotePanel
{
    /** @param array<string, mixed>|null $geometria */
    public function __construct(
        public int $id,
        public string $codigo,
        public string $hectareas,
        public int $campoId,
        public string $campoNombre,
        public int $clienteId,
        public string $clienteNombre,
        public ?array $geometria,
        public ?string $restricciones,
    ) {}
}
