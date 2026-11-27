<?php

namespace App\Dominios\Reclamos\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: una de las partes apeló la resolución con pruebas nuevas (una
 * sola vez, dentro de 7 días) y el reclamo vuelve a revisión. Avisa a la
 * contraparte y a soporte. DTO de primitivos.
 */
final readonly class ReclamoApelado implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosContraparte  cuentas de la contraparte a las que se avisa.
     */
    public function __construct(
        public int $reclamoId,
        public int $solicitudId,
        public string $apeladaPor,
        public array $usuariosContraparte,
    ) {}
}
