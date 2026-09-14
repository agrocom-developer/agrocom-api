<?php

namespace App\Dominios\Comercial\Dominio;

/**
 * Estados del contrato (espec §4.1; CHECK en com_contratos). Solo nombra los
 * estados: las transiciones permitidas y sus guardas irán en el servicio de
 * dominio de la máquina de estados cuando se implemente (invariante 7) —
 * nunca en un `estado = ...` suelto.
 */
enum EstadoContrato: string
{
    case Borrador = 'borrador';
    case Vigente = 'vigente';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';
    case Pausado = 'pausado';
}
