<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Api;

use App\Dominios\Negociacion\Aplicacion\Negociar;
use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Quién llama y desde qué lado: el permiso del rol activo del token es la puerta (el
 * alcance lo hacen los casos de uso, invariante 5 de CLAUDE.md). Un rol sin ninguno de
 * los permisos de la negociación recibe 403.
 *
 * @property IdentidadOperarioToken $identidad
 */
trait ActuaDeLaCuenta
{
    private function usuarioId(): int
    {
        return (int) Auth::guard('sanctum')->id();
    }

    private function exigirPermiso(Request $request, string $permiso): void
    {
        abort_unless($this->identidad->tienePermiso($request, $permiso), 403, __('aprobacion.errores.sin_permiso'));
    }

    /** El lado de la negociación del rol activo: `proveedor` o `cliente`. */
    private function lado(Request $request): string
    {
        if ($this->identidad->tienePermiso($request, 'negociacion.oferta.proveedor')) {
            return Negociar::LADO_PROVEEDOR;
        }

        abort_unless($this->identidad->tienePermiso($request, 'negociacion.oferta.cliente'), 403, __('aprobacion.errores.sin_permiso'));

        return Negociar::LADO_CLIENTE;
    }
}
