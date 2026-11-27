<?php

namespace App\Dominios\Disputas\Infraestructura\Http;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use Illuminate\Http\JsonResponse;

/**
 * Traduce un rechazo de las disputas a la respuesta de la API: un registro ajeno es
 * 404 (invariante 5), un paso que el estado de la disputa no admite es 409 y el
 * resto es 422 con el campo.
 */
final class RespuestaDeDisputa
{
    public static function de(DisputaInvalida $excepcion): JsonResponse
    {
        if ($excepcion->esConflicto) {
            return response()->json(['message' => $excepcion->getMessage()], 409);
        }

        return RechazoDeDominio::respuesta($excepcion);
    }
}
