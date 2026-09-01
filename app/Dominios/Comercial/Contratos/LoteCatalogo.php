<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Forma de dato primitiva de un lote para el pull de catálogo (ADR 0003,
 * regla 2): ningún módulo consumidor recibe el modelo Eloquent `Lote`, solo
 * estos campos planos. `hectareas` viaja como string (invariante 6).
 */
final readonly class LoteCatalogo
{
    /** @param array<string, mixed>|null $geometria */
    public function __construct(
        public int $id,
        public int $campoId,
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
            'campo_id' => $this->campoId,
            'codigo' => $this->codigo,
            'hectareas' => $this->hectareas,
            'geometria' => $this->geometria,
            'restricciones' => $this->restricciones,
            'updated_at' => $this->updatedAt,
        ];
    }
}
