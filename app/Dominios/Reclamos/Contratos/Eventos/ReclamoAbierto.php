<?php

namespace App\Dominios\Reclamos\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): una de las partes abrió un reclamo sobre
 * una solicitud. Avisa a la contraparte, que responde en 48 horas, y a soporte.
 * DTO de primitivos.
 */
final readonly class ReclamoAbierto implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosContraparte  cuentas de la contraparte a las que se avisa.
     */
    public function __construct(
        public int $reclamoId,
        public int $solicitudId,
        public string $abiertaPor,
        public string $motivo,
        public array $usuariosContraparte,
    ) {}
}
