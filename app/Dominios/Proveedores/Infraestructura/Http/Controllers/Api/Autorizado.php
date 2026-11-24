<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Api;

use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Acceso común de los controladores de la API del marketplace: quién es la
 * cuenta del token y si el rol del token tiene el permiso de la puerta (el
 * alcance sobre cada registro lo hacen los casos de uso). Sin permiso, 403.
 */
trait Autorizado
{
    private function usuarioId(Request $request): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigirPermiso(IdentidadOperarioToken $identidad, Request $request, string $codigo): void
    {
        abort_unless($identidad->tienePermiso($request, $codigo), 403, __('aprobacion.errores.sin_permiso'));
    }
}
