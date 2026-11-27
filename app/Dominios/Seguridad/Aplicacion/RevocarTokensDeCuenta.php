<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Revoca los tokens de dispositivo de una cuenta (borrado lógico, como {@see RevocarTokenDispositivo}): al cambiar la
 * contraseña (ADR 0046, punto 27) y al pedir la eliminación (ADR 0047). Con `$conservar` el dispositivo que hizo el
 * cambio sigue con su sesión.
 */
final class RevocarTokensDeCuenta
{
    public function ejecutar(SecUser $usuario, ?int $conservarTokenId = null): int
    {
        $revocados = 0;

        SecTokenDispositivo::query()
            ->where('user_id', $usuario->id)
            ->when($conservarTokenId !== null, fn ($consulta) => $consulta->where('id', '!=', $conservarTokenId))
            ->get()
            ->each(function (SecTokenDispositivo $token) use ($usuario, &$revocados): void {
                $token->updated_by = $usuario->id;
                $token->save();
                $token->delete();
                $revocados++;
            });

        return $revocados;
    }
}
