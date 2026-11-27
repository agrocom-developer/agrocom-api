<?php

namespace App\Dominios\Reclamos\Infraestructura\Http;

use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use Illuminate\Http\JsonResponse;

/**
 * Traduce un rechazo de los reclamos a la respuesta de la API: un registro ajeno es
 * 404 (invariante 5), un paso que el estado del reclamo no admite es 409 y el
 * resto es 422 con el campo.
 */
final class RespuestaDeReclamo
{
    public static function de(ReclamoInvalido $excepcion): JsonResponse
    {
        if ($excepcion->esConflicto) {
            return response()->json(['message' => $excepcion->getMessage()], 409);
        }

        return RechazoDeDominio::respuesta($excepcion);
    }
}
