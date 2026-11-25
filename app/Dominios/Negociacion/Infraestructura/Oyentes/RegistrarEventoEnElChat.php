<?php

namespace App\Dominios\Negociacion\Infraestructura\Oyentes;

use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;

/**
 * Cuando la negociación avisa de un hecho de la solicitud (adjudicación, inicio y fin del servicio,
 * precio final confirmado) deja la línea correspondiente en el chat: «El piloto inició el servicio».
 * Los demás avisos no tocan el chat.
 */
final class RegistrarEventoEnElChat
{
    public function __construct(private readonly Mensajeria $chat) {}

    public function handle(AvisoDeNegociacion $aviso): void
    {
        $evento = Mensajeria::EVENTOS_DE_SISTEMA[$aviso->tipo] ?? null;

        if ($evento === null) {
            return;
        }

        $this->chat->registrarEvento($aviso->solicitudId, $evento, $aviso->claveEvento, $aviso->parametros);
    }
}
