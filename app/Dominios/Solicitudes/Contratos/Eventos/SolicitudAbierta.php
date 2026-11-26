<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: una solicitud quedó `abierta` (o se renovó) y es visible para
 * los pilotos. La negociación avisa a los pilotos cuyas preferencias coinciden con
 * la zona y las hectáreas. DTO de primitivos.
 *
 * `tipoAplicacion` y `departamentoCodigo` alimentan los filtros del proveedor (ADR 0045); el
 * departamento llega `null` hasta que la solicitud lo guarde (ubicación por departamento).
 */
final readonly class SolicitudAbierta implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public int $clienteId,
        public int $clienteUsuarioId,
        public ?int $zonaId,
        public ?string $zona,
        public ?string $macrorregion,
        public string $hectareas,
        public string $precioHa,
        public bool $renovada,
        public ?string $tipoAplicacion = null,
        public ?string $departamentoCodigo = null,
    ) {}
}
