<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http;

use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Compartido\Infraestructura\Http\RechazoDeDominio;
use Illuminate\Http\JsonResponse;

/**
 * Traduce un rechazo de las calificaciones a la respuesta de la API: un registro
 * ajeno es 404 (invariante 5), una calificación que ya existe es 409 y el resto
 * es 422 con el campo.
 */
final class RespuestaDeCalificacion
{
    public static function de(CalificacionInvalida $excepcion): JsonResponse
    {
        if ($excepcion->esConflicto) {
            return response()->json(['message' => $excepcion->getMessage()], 409);
        }

        return RechazoDeDominio::respuesta($excepcion);
    }
}
