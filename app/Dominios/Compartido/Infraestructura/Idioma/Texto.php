<?php

namespace App\Dominios\Compartido\Infraestructura\Idioma;

/**
 * Puerta única al catálogo de idioma (`lang/es/*.php`, ADR 0013) para el
 * código que NO es presentación: excepciones de dominio, casos de uso y
 * servicios de infraestructura que arman un mensaje que alguien va a leer.
 * Un controlador, un FormRequest o una vista usan `__()` directo; esto
 * existe para las capas de adentro.
 *
 * Hace lo mismo que `__()` con una sola diferencia: si el framework no está
 * levantado —un test unitario puro (`tests/Unit`), que no arma contenedor—
 * devuelve la clave en vez de reventar buscando el traductor. Una excepción
 * de dominio tiene que poder construirse sin Laravel alrededor.
 *
 * Convención de claves: `<modulo>.errores.*` (excepciones), `.validacion.*`
 * (FormRequests), `.respuestas.*` (controladores y API), `.pdf.*`
 * (documentos). Ver el skill `redaccion-neutra`.
 */
final class Texto
{
    /**
     * @param  array<string, string|int|float>  $reemplazos  valores de los
     *                                                       `:marcadores`
     *                                                       de la clave.
     */
    public static function de(string $clave, array $reemplazos = []): string
    {
        if (! function_exists('app') || ! app()->bound('translator')) {
            return $clave;
        }

        $texto = __($clave, $reemplazos);

        return is_string($texto) ? $texto : $clave;
    }
}
