<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Forma de dato primitiva de un trabajo asignado desde el panel, para el pull
 * de catálogo (ADR 0003, regla 2; HU-70, tarea 85): ningún módulo consumidor
 * recibe el modelo Eloquent `Trabajo`, solo estos campos planos.
 * `hectareas_declaradas` viaja como string (invariante 6 de CLAUDE.md).
 *
 * `uuid_cliente` es el que generó el panel al confirmar la asignación (ver
 * `Aplicacion/AsignarEquiposOrden`) — la app de campo lo lee de acá y lo usa
 * TAL CUAL para abrir sesiones sobre este trabajo (`abrirSesion` lo resuelve
 * por `trabajo_uuid_cliente`), sin generar uno propio.
 */
final readonly class TrabajoAsignadoCatalogo
{
    public function __construct(
        public int $id,
        public string $uuidCliente,
        public int $ordenId,
        public int $loteId,
        public string $hectareasDeclaradas,
        public int $equipoTrabajoId,
        public string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid_cliente' => $this->uuidCliente,
            'orden_id' => $this->ordenId,
            'lote_id' => $this->loteId,
            'hectareas_declaradas' => $this->hectareasDeclaradas,
            'equipo_trabajo_id' => $this->equipoTrabajoId,
            'updated_at' => $this->updatedAt,
        ];
    }
}
