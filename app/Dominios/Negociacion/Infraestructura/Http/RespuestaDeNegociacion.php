<?php

namespace App\Dominios\Negociacion\Infraestructura\Http;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Traduce una regla de negociación incumplida a su respuesta HTTP: 404 si el recurso no es
 * de la cuenta (invariante 5), 403 si la cuenta no está habilitada, 409 con `codigo` si el
 * estado ya no admite la acción (carreras, vencimientos) y 422 con el campo si el dato es inválido.
 */
#[OA\Schema(
    schema: 'ErrorConflicto',
    title: 'Conflicto de estado',
    description: 'La acción ya no es posible por el estado actual (otra parte decidió antes, venció, etc.). `codigo` es estable y es lo que la app traduce a su pantalla.',
    required: ['message', 'codigo'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'La oferta ya no está vigente.'),
        new OA\Property(property: 'codigo', type: 'string', example: 'oferta_no_vigente'),
    ],
    type: 'object',
)]
final class RespuestaDeNegociacion
{
    public static function de(NegociacionInvalida $excepcion): JsonResponse
    {
        if ($excepcion->estatus === 422) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => $excepcion->codigo,
                'errors' => [($excepcion->campo ?? 'base') => [$excepcion->getMessage()]],
            ], 422);
        }

        return response()->json(['message' => $excepcion->getMessage(), 'codigo' => $excepcion->codigo], $excepcion->estatus);
    }
}
