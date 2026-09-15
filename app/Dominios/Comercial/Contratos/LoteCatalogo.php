<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva de un lote para el pull de catálogo (ADR 0003,
 * regla 2): ningún módulo consumidor recibe el modelo Eloquent `Lote`, solo
 * estos campos planos. `hectareas` viaja como string (invariante 6).
 *
 * `toArray()` devuelve `propiedad_id` (ADR 0020): contrato externo real de
 * `GET /api/sync/catalogo` con `agrocom-field`, renombrado en conjunto con
 * esa app (rama `feature/sync-propiedad`) — sin APK distribuido todavía, sin
 * ventana de compatibilidad que cuidar.
 */
final readonly class LoteCatalogo
{
    /** @param array<string, mixed>|null $geometria */
    public function __construct(
        public int $id,
        public int $propiedadId,
        public string $codigo,
        public string $hectareas,
        public ?array $geometria,
        public ?string $restricciones,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'propiedad_id' => $this->propiedadId,
            'codigo' => $this->codigo,
            'hectareas' => $this->hectareas,
            'geometria' => $this->geometria,
            'restricciones' => $this->restricciones,
            'updated_at' => $this->updatedAt,
        ];
    }
}
