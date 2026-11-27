<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Foto de una solicitud para los módulos que actúan después de la ejecución
 * (calificaciones, reclamos, métricas): quién la pidió, quién la prestó, cuánto
 * vale y en qué momento se ejecutó y se completó. Primitivos puros: ningún
 * módulo importa `SolicitudServicio` (ADR 0003). Importes en decimal exacto.
 */
final readonly class DatosServicio
{
    public function __construct(
        public int $id,
        public string $estado,
        public int $clienteId,
        public int $clienteUsuarioId,
        public string $clienteNombre,
        public ?int $proveedorId,
        public ?int $equipoId,
        public string $hectareasSolicitadas,
        public ?string $montoEstimado,
        public ?string $montoFinal,
        public ?string $precioHaAcordado,
        public ?string $hectareasEjecutadas,
        public ?string $finalizadaEn,
    ) {}

    /** Importe sobre el que se calculan reembolsos: el monto final si ya existe y, si no, el estimado congelado. */
    public function montoDeReferencia(): string
    {
        return $this->montoFinal ?? $this->montoEstimado ?? '0.00';
    }
}
