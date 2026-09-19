<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Por qué se canceló una aplicación (pedido del dueño, 18/9/2026, ADR 0022).
 * Catálogo cerrado, sin máquina de estados — mismo criterio que
 * {@see CausaPausa}.
 *
 * Decide si la aplicación cancelada CONSUME su número correlativo:
 * - `cliente`: decisión o incumplimiento del cliente (p. ej. falta de pago).
 *   Cuenta como realizada: la siguiente orden lleva el número que sigue.
 * - `fuerza_mayor`: algo ajeno a las partes (un dron caído, clima). No consume
 *   el número: la aplicación se rehace con el MISMO número.
 * El detalle de qué pasó va aparte, en el motivo escrito.
 */
enum CausaCancelacionOrden: string
{
    case Cliente = 'cliente';
    case FuerzaMayor = 'fuerza_mayor';

    public function consumeNumero(): bool
    {
        return $this === self::Cliente;
    }
}
