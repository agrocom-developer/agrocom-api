<?php

namespace App\Dominios\Seguridad\Infraestructura\Http;

use App\Dominios\Seguridad\Contratos\IdentidadOperarioToken;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\Request;

/**
 * Implementación sobre el guard de token de dispositivo (HU-03). Mismo
 * criterio que {@see AutorizacionPanelWebSesion}: el consumidor de otro
 * módulo nunca recibe `SecUser`, solo el primitivo que necesita.
 */
final class IdentidadOperarioTokenSanctum implements IdentidadOperarioToken
{
    public function personaId(Request $request): ?int
    {
        /** @var SecUser|null $usuario */
        $usuario = $request->user();

        return $usuario?->persona_id;
    }

    /** Fail-closed: sin `SecUser` autenticado, ningún permiso se concede. */
    public function tienePermiso(Request $request, string $codigo): bool
    {
        /** @var SecUser|null $usuario */
        $usuario = $request->user();

        return $usuario?->tienePermiso($codigo) ?? false;
    }
}
