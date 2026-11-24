<?php

namespace App\Dominios\Negociacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de la negociación que le importa a una o más cuentas: una oferta nueva,
 * la adjudicación, el avance, un mensaje. Un solo evento genérico lleva el tipo de
 * aviso (valor de `TipoNotificacion`), la clave que lo hace idempotente y los
 * parámetros de su texto; la regla de `Notificaciones` lo traduce a avisos. Los
 * destinatarios ya vienen filtrados por las preferencias de cada cuenta. DTO de primitivos.
 */
final readonly class AvisoDeNegociacion implements ShouldDispatchAfterCommit
{
    /**
     * @param  array<string, string|int>  $parametros
     * @param  list<int>  $usuarios
     */
    public function __construct(
        public string $tipo,
        public string $claveEvento,
        public array $parametros,
        public int $solicitudId,
        public array $usuarios,
    ) {}
}
