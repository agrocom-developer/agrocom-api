<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Por qué se canceló una aplicación, o quién la pidió (pedido del dueño,
 * 18/9/2026, ADR 0022; ampliado el 19/9/2026 con «dueño» y con «fuerza mayor»
 * llamada ahora «factor externo»). Catálogo cerrado, sin máquina de estados —
 * mismo criterio que {@see CausaPausa}.
 *
 * Decide si la aplicación cancelada CONSUME su número correlativo:
 * - `cliente`: decisión o incumplimiento del cliente (p. ej. falta de pago).
 *   Cuenta como realizada: la siguiente orden lleva el número que sigue.
 * - `dueno`: decisión de Agrocom, tomada por el dueño. También consume el
 *   número: la aplicación se decidió no hacer.
 * - `factor_externo`: algo ajeno a las partes (un dron caído, clima). No consume
 *   el número: la aplicación se rehace con el MISMO número.
 * El detalle de qué pasó va aparte, en el motivo escrito.
 */
enum CausaCancelacionOrden: string
{
    case Cliente = 'cliente';
    case Dueno = 'dueno';
    case FactorExterno = 'factor_externo';

    public function consumeNumero(): bool
    {
        return $this !== self::FactorExterno;
    }
}
