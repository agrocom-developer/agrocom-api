<?php

namespace App\Dominios\Seguridad\Infraestructura\Http;

use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Illuminate\Http\Request;

/**
 * Implementación Eloquent de {@see AutorizacionPanelWeb}, sobre la sesión de
 * panel (guard `interno`). Fail-closed: sin un `SecUser` autenticado, ningún
 * permiso se concede.
 */
final class AutorizacionPanelWebSesion implements AutorizacionPanelWeb
{
    public function __construct(private readonly CascaraPanel $cascaraPanel) {}

    public function tienePermiso(Request $request, string $codigoPermiso): bool
    {
        $usuario = $request->user('interno');

        if (! $usuario instanceof SecUser) {
            return false;
        }

        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        return $usuario->tienePermisoEnRol($codigoPermiso, $idRolActivo);
    }

    /** @return array<string, mixed> */
    public function cascara(Request $request): array
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        return $this->cascaraPanel->para($usuario, $idRolActivo);
    }

    public function personaId(Request $request): ?int
    {
        /** @var SecUser|null $usuario */
        $usuario = $request->user('interno');

        return $usuario?->persona_id;
    }
}
