<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva de un lote para el pull de catálogo (ADR 0003,
 * regla 2): ningún módulo consumidor recibe el modelo Eloquent `Lote`, solo
 * estos campos planos. `hectareas` viaja como string (invariante 6).
 *
 * `toArray()` sigue devolviendo la clave `campo_id` a propósito (ADR 0020):
 * es el contrato externo real de `GET /api/sync/catalogo` con `agrocom-field`
 * — a diferencia de `LotePanel` (puramente interno, panel/dashboard), este
 * JSON lo consume la app de campo ya distribuida. Renombrar la clave acá
 * rompería el catálogo para cualquier APK que no se haya actualizado todavía;
 * el rename real queda para una rama coordinada con una versión nueva del
 * APK (`feature/sync-propiedad`), igual que `AperturaEstadiaHacienda` en el
 * sentido inverso (payload de entrada).
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
            // Ver docblock de la clase: clave de wire sin renombrar a propósito.
            'campo_id' => $this->propiedadId,
            'codigo' => $this->codigo,
            'hectareas' => $this->hectareas,
            'geometria' => $this->geometria,
            'restricciones' => $this->restricciones,
            'updated_at' => $this->updatedAt,
        ];
    }
}
