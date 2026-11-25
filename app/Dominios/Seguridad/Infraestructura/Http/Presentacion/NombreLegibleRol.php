<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use Illuminate\Support\Facades\Lang;

/** Nombre mostrable de un rol a partir de su slug; sin metadata, el propio slug. */
final class NombreLegibleRol
{
    public static function de(string $slug): string
    {
        $clave = "seguridad.rol.meta.{$slug}.nombre";

        return Lang::has($clave) ? __($clave) : $slug;
    }
}
