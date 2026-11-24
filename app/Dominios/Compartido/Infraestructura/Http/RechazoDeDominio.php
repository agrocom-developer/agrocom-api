<?php

namespace App\Dominios\Compartido\Infraestructura\Http;

use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Traduce un rechazo de dominio (una regla de negocio que no se cumple) a la
 * respuesta estándar de validación de la API: 422 con `message` y `errors`,
 * igual que un `FormRequest`, para que la app muestre el error junto al campo.
 * Un registro ajeno es 404 (invariante 5 de CLAUDE.md): nunca se confirma que
 * existe.
 */
final class RechazoDeDominio
{
    public static function respuesta(Throwable $excepcion, ?string $campo = null): JsonResponse
    {
        if (property_exists($excepcion, 'esAjeno') && $excepcion->esAjeno === true) {
            return response()->json(['message' => $excepcion->getMessage()], 404);
        }

        if ($campo === null && property_exists($excepcion, 'campo') && is_string($excepcion->campo) && $excepcion->campo !== '') {
            $campo = $excepcion->campo;
        }

        $campo ??= 'base';

        return response()->json([
            'message' => $excepcion->getMessage(),
            'errors' => [$campo => [$excepcion->getMessage()]],
        ], 422);
    }
}
