<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Una orden que dejó de estar `vigente` (sección `ordenes_retiradas` de
 * `GET /api/sync/catalogo`, opción B de la propuesta de #312): solo lo que la
 * app necesita para ocultarla — nunca borrarla, invariante 6 de
 * agrocom-field —. Si vuelve a `vigente` (`pausada → vigente`, ADR 0022),
 * reaparece en `ordenes[]` en el pull siguiente.
 */
final readonly class OrdenRetiradaCatalogo
{
    public function __construct(
        public int $id,
        public string $estado,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado,
            'updated_at' => $this->updatedAt,
        ];
    }
}
