<?php

namespace App\Dominios\Proveedores\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: un proveedor independiente o tercero se registró y espera
 * revisión (ADR 0032, punto 1). Avisa a los administradores que aprueban.
 * DTO de primitivos.
 */
final readonly class ProveedorRegistrado implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $proveedorId,
        public string $razonSocial,
        public string $tipo,
    ) {}
}
