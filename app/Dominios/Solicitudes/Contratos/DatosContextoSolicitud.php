<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Lo que otro módulo muestra de una solicitud cuando solo conoce su id: de qué
 * terreno y de qué cliente es, y qué se aplicó. DTO de primitivos, sin modelos
 * Eloquent (ADR 0003). Hectáreas como string (invariante 6 de CLAUDE.md).
 */
final readonly class DatosContextoSolicitud
{
    public function __construct(
        public int $id,
        public string $terrenoNombre,
        public ?string $cultivo,
        public string $tipoAplicacion,
        public string $hectareasSolicitadas,
        public string $estado,
        public int $clienteId,
        public string $clienteNombre,
        public ?int $proveedorId,
    ) {}

    /** Etiqueta corta para tablas y listas: «#31 · Lote Norte». */
    public function etiqueta(): string
    {
        return "#{$this->id} · {$this->terrenoNombre}";
    }
}
