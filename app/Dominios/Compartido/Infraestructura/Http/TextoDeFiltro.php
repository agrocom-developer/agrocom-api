<?php

namespace App\Dominios\Compartido\Infraestructura\Http;

use Illuminate\Http\Request;

/**
 * Lectura segura de un filtro de texto de listado (`?q=`, `?estado=`, etc.):
 * `Request::string()` hace `(string) $valor`, y un arreglo en la query
 * (`?q[]=x`, un reintento del mismo enlace con otro parámetro repetido) dispara
 * "Array to string conversion", que este proyecto eleva a 500 — no es un
 * texto de búsqueda, así que se descarta en vez de convertirlo (tarea 121,
 * extraído a plataforma en la tarea 128 para no repetir el mismo
 * `is_string(...) ? ... : ''` en cada controlador).
 */
final class TextoDeFiltro
{
    public static function de(Request $request, string $clave, int $largoMaximo = 200): string
    {
        $valor = $request->query($clave);

        if (! is_string($valor)) {
            return '';
        }

        return mb_substr($valor, 0, $largoMaximo);
    }
}
