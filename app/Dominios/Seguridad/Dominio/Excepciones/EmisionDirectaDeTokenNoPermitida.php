<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use LogicException;

/**
 * `SecUser::createToken()` (el método del trait `HasApiTokens` de Sanctum)
 * está sellado: no conoce el dispositivo ni el rol activo, que son
 * obligatorios en `sec_token_dispositivo` (HU-03, invariantes 1 y 10 de
 * CLAUDE.md). Emitir por ahí dejaría un token sin dueño de dispositivo y sin
 * rol — o directamente un `TypeError`, porque el `NewAccessToken` de Sanctum
 * exige una instancia de su propio modelo de token.
 *
 * Existe para que ese error salga con nombre y con la salida correcta escrita
 * en el mensaje, en vez de un fallo críptico del paquete.
 */
final class EmisionDirectaDeTokenNoPermitida extends LogicException
{
    public static function usar(string $casoDeUso): self
    {
        return new self(
            "Los tokens de dispositivo no se emiten con createToken(): usá {$casoDeUso}, "
            .'que registra el dispositivo y el rol activo con el que opera.',
        );
    }
}
