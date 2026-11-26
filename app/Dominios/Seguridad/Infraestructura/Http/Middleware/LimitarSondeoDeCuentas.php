<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Middleware;

use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;
use App\Dominios\Seguridad\Dominio\TipoDocumento;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tope de «ya tienes una cuenta» (ADR 0046, punto 29; plan de cuentas §1.2): el `409
 * cuenta_ya_registrada` confirma que un teléfono o un documento está registrado en Agrocom,
 * un dato de poco valor pero sondeable. Además del límite general del registro público
 * (`registro-publico`: 5 por minuto y 20 por hora por IP), cada `409` cuenta contra tres
 * cubos de una hora: la IP, el teléfono consultado y el documento consultado. Al pasar de
 * {@see self::MAXIMO} el registro responde `429` aunque el dato nuevo no exista, para que el
 * sondeo no distinga nada y un barrido de teléfonos desde una sola IP se corte rápido.
 *
 * Solo cuenta los `409`: un registro que sale bien o falla por validación no consume nada.
 */
final class LimitarSondeoDeCuentas
{
    /** `409` tolerados por hora y por cubo (IP, teléfono o documento). */
    public const MAXIMO = 5;

    private const SEGUNDOS = 3600;

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $claves = $this->claves($request);

        foreach ($claves as $clave) {
            if (RateLimiter::tooManyAttempts($clave, self::MAXIMO)) {
                throw new ThrottleRequestsException('', null, ['Retry-After' => (string) RateLimiter::availableIn($clave)]);
            }
        }

        $respuesta = $next($request);

        if ($respuesta->getStatusCode() === 409 && str_contains((string) $respuesta->getContent(), 'cuenta_ya_registrada')) {
            foreach ($claves as $clave) {
                RateLimiter::hit($clave, self::SEGUNDOS);
            }
        }

        return $respuesta;
    }

    /** @return list<string> */
    private function claves(Request $request): array
    {
        $claves = ['registro-409|ip|'.$request->ip()];

        $telefono = $request->input('telefono');
        $normalizado = is_string($telefono) ? NumeroDeTelefono::normalizar($telefono) : null;

        if ($normalizado !== null) {
            $claves[] = 'registro-409|telefono|'.sha1($normalizado);
        }

        $numero = $request->input('documento_numero', $request->input('documento_identidad'));

        if (is_string($numero) && trim($numero) !== '') {
            $tipo = is_string($request->input('documento_tipo')) ? $request->input('documento_tipo') : 'ci';
            $claves[] = 'registro-409|documento|'.sha1($tipo.'|'.TipoDocumento::normalizarNumero($numero));
        }

        return $claves;
    }
}
