<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Un servicio que el equipo tuvo asignado y ya no debe llevar al lote (ADR
 * 0037): la app lo oculta, no lo borra, porque puede tener trabajo ya
 * capturado sin sincronizar. `motivo`: `cancelada`, `reasignada` (volvió a
 * asignación: el proveedor canceló o la reasignó soporte) o `ejecutada` (ya
 * cerrado por el servidor, a la espera de conformidad o terminado).
 */
final readonly class ServicioRetiradoCatalogo
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
