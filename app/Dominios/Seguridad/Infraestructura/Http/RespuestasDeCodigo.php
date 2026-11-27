<?php

namespace App\Dominios\Seguridad\Infraestructura\Http;

use App\Dominios\Seguridad\Dominio\Excepciones\CanalNoDisponible;
use App\Dominios\Seguridad\Dominio\Excepciones\CodigoInvalido;
use App\Dominios\Seguridad\Dominio\Excepciones\DemasiadosCodigos;
use Illuminate\Http\JsonResponse;

/** Cómo responde la API a las fallas de los códigos de un solo uso: el mismo cuerpo para cada caso (ADR 0046, punto 27). */
final class RespuestasDeCodigo
{
    public static function de(CanalNoDisponible|CodigoInvalido|DemasiadosCodigos $falla): JsonResponse
    {
        return match (true) {
            $falla instanceof CanalNoDisponible => response()->json($falla->cuerpo(), 503),
            $falla instanceof CodigoInvalido => response()->json($falla->cuerpo(), 422),
            default => response()->json(['message' => $falla->getMessage(), 'codigo' => 'demasiados_codigos'], 429, ['Retry-After' => (string) $falla->segundos]),
        };
    }

    public static function sinDestino(string $medio): JsonResponse
    {
        return response()->json(['message' => __('seguridad.codigos.sin_destino', ['medio' => $medio]), 'codigo' => 'sin_destino'], 422);
    }
}
