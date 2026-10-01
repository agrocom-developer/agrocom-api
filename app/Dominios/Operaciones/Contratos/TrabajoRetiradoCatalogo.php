<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Un trabajo que el dispositivo ya no debe tener como asignado (sección
 * `trabajos_retirados` de `GET /api/sync/catalogo`, opción B de la propuesta
 * de #312). `motivo` es un valor de `Dominio\MotivoRetiroTrabajo`;
 * `uuid_cliente` es el que la app usa para abrir sesiones sobre ese trabajo.
 */
final readonly class TrabajoRetiradoCatalogo
{
    public function __construct(
        public int $id,
        public string $uuidCliente,
        public string $motivo,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid_cliente' => $this->uuidCliente,
            'motivo' => $this->motivo,
            'updated_at' => $this->updatedAt,
        ];
    }
}
