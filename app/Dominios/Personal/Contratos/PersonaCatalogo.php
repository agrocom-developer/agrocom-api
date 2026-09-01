<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Forma de dato primitiva de una persona operativa para el pull de catálogo
 * (ADR 0003, regla 2): ningún módulo consumidor recibe el modelo Eloquent
 * `PerPersona`, solo estos campos planos.
 */
final readonly class PersonaCatalogo
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $rol,
        public ?int $baseId,
        public bool $activo,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'rol' => $this->rol,
            'base_id' => $this->baseId,
            'activo' => $this->activo,
            'updated_at' => $this->updatedAt,
        ];
    }
}
