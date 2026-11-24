<?php

namespace App\Dominios\Negociacion\Dominio;

/**
 * Estado de una oferta (`negociacion-contrato.md` §2). Solo `MaquinaEstadosOferta`
 * lo cambia, consultando {@see TransicionesOferta}.
 */
enum EstadoOferta: string
{
    case Vigente = 'vigente';
    case Aceptada = 'aceptada';
    case Reemplazada = 'reemplazada';
    case Rechazada = 'rechazada';
    case Retirada = 'retirada';
    case Vencida = 'vencida';
    case Cerrada = 'cerrada';

    /** Espera respuesta de la contraparte. */
    public function estaVigente(): bool
    {
        return $this === self::Vigente;
    }
}
