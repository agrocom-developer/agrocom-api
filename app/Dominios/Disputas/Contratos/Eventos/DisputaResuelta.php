<?php

namespace App\Dominios\Disputas\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): un administrador resolvió la disputa o quien
 * la abrió la retiró. Avisa a ambas partes con el resultado y el reembolso. DTO de
 * primitivos.
 */
final readonly class DisputaResuelta implements ShouldDispatchAfterCommit
{
    /**
     * @param  string  $resultado  `a_favor_cliente`, `a_favor_proveedor`, `parcial`, `sin_lugar` o `retirada`.
     * @param  list<int>  $usuariosDestinatarios  cuentas de ambas partes.
     */
    public function __construct(
        public int $disputaId,
        public int $solicitudId,
        public string $resultado,
        public string $montoReembolso,
        public int $ronda,
        public array $usuariosDestinatarios,
    ) {}
}
