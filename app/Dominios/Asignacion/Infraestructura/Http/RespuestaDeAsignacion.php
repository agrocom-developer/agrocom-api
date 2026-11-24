<?php

namespace App\Dominios\Asignacion\Infraestructura\Http;

use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use Illuminate\Http\JsonResponse;

/**
 * Traduce un rechazo de la asignación a la respuesta de la API: un registro
 * ajeno es 404 (invariante 5), una oferta que ya no está disponible es 409
 * (otro la ganó, venció o se retiró) y el resto es 422 con el campo.
 */
final class RespuestaDeAsignacion
{
    public static function de(AsignacionInvalida $excepcion): JsonResponse
    {
        if ($excepcion->esConflicto) {
            return response()->json(['message' => $excepcion->getMessage()], 409);
        }

        return RechazoDeDominio::respuesta($excepcion);
    }
}
