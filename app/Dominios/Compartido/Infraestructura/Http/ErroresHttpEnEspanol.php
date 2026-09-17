<?php

namespace App\Dominios\Compartido\Infraestructura\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Traduce los errores que arma el FRAMEWORK cuando responde JSON (la API de
 * las apps de campo y los `fetch` del panel). Sin esto, quien consume la API
 * lee `"Unauthenticated."`, `"The route api/x could not be found."` o `"No
 * query results for model [App\...\Trabajo] 12"` — inglés, y en el último
 * caso además el nombre de una clase interna.
 *
 * Qué NO toca:
 * - `ValidationException`: sus mensajes ya salen de `lang/es/` (los propios de
 *   cada FormRequest y el genérico de `validation.php`).
 * - Los errores PROPIOS del dominio. `PermisoDenegado`, `RolNoAsignado`,
 *   `DispositivoNoEncontrado` y compañía ya traen su mensaje en español, más
 *   útil que uno genérico: si en la cadena de la excepción hay una clase de
 *   `App\`, el mensaje se respeta tal cual.
 * - Con `APP_DEBUG=true`, un error que no es HTTP (un 500 de verdad) sigue
 *   saliendo con su traza: en desarrollo eso es lo que se necesita ver.
 *
 * Se engancha en `bootstrap/app.php` (`withExceptions`). Devolver `null` es
 * "no es asunto mío": el framework sigue con su respuesta de siempre — la
 * forma del JSON (`{"message": ...}`) es la misma que él produce.
 */
final class ErroresHttpEnEspanol
{
    /** Código de estado → clave de `lang/es/http.php`. */
    private const CLAVES = [
        401 => 'http.no_autenticado',
        403 => 'http.no_autorizado',
        404 => 'http.no_encontrado',
        405 => 'http.metodo_no_permitido',
        419 => 'http.pagina_vencida',
        429 => 'http.demasiados_intentos',
        500 => 'http.error_servidor',
        503 => 'http.en_mantenimiento',
    ];

    public static function responder(Throwable $excepcion, Request $request): ?JsonResponse
    {
        if (! ($request->is('api/*') || $request->expectsJson()) || $excepcion instanceof ValidationException) {
            return null;
        }

        if ($excepcion instanceof AuthenticationException) {
            return response()->json(['message' => __(self::CLAVES[401])], 401);
        }

        if (! $excepcion instanceof HttpExceptionInterface) {
            return config('app.debug')
                ? null
                : response()->json(['message' => __(self::CLAVES[500])], 500);
        }

        $estado = $excepcion->getStatusCode();

        $mensaje = self::traeMensajePropio($excepcion)
            ? $excepcion->getMessage()
            : __(self::CLAVES[$estado] ?? ($estado >= 500 ? self::CLAVES[500] : 'http.solicitud_invalida'));

        return response()->json(['message' => $mensaje], $estado, $excepcion->getHeaders());
    }

    /**
     * ¿El mensaje lo escribió este sistema? Sí, cuando la excepción —o la
     * que la originó, porque el framework envuelve una `AuthorizationException`
     * en una `AccessDeniedHttpException`— es una clase de `App\`.
     */
    private static function traeMensajePropio(Throwable $excepcion): bool
    {
        for ($actual = $excepcion; $actual !== null; $actual = $actual->getPrevious()) {
            if (str_starts_with($actual::class, 'App\\') && $actual->getMessage() !== '') {
                return true;
            }
        }

        return false;
    }
}
