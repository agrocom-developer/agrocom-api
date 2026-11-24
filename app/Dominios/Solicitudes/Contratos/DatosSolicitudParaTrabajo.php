<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Lo que `Operaciones` necesita saber de una solicitud para abrirle un
 * trabajo: si admite ejecución y qué equipo la lleva. DTO de primitivos, sin
 * modelos Eloquent (ADR 0003).
 */
final readonly class DatosSolicitudParaTrabajo
{
    public function __construct(
        public int $id,
        public string $estado,
        /** Si un proveedor ya aceptó y la solicitud puede ejecutarse en el lote. */
        public bool $admiteTrabajo,
        public ?int $equipoId,
        public string $hectareasSolicitadas,
    ) {}
}
