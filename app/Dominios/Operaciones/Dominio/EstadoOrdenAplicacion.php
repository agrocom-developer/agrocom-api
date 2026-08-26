<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Estados de la orden de aplicación (espec §5: emitida → vigente →
 * consumida | vencida; CHECK en ope_ordenes_aplicacion). Solo nombra los
 * estados: las transiciones permitidas y sus guardas irán en el servicio de
 * dominio de la máquina de estados cuando se implemente (invariante 7).
 */
enum EstadoOrdenAplicacion: string
{
    case Emitida = 'emitida';
    case Vigente = 'vigente';
    case Consumida = 'consumida';
    case Vencida = 'vencida';
}
