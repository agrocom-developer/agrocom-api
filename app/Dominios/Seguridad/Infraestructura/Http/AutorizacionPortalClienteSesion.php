<?php

namespace App\Dominios\Seguridad\Infraestructura\Http;

use App\Dominios\Seguridad\Contratos\AutorizacionPortalCliente;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Http\Request;

/**
 * Implementación Eloquent de {@see AutorizacionPortalCliente}, sobre la
 * sesión de portal (guard `cliente`). Fail-closed: sin una cuenta de portal
 * autenticada, `contratoId()` devuelve `null` — nunca un contrato por
 * default.
 */
final class AutorizacionPortalClienteSesion implements AutorizacionPortalCliente
{
    public function contratoId(Request $request): ?int
    {
        /** @var SecUser|null $usuario */
        $usuario = $request->user('cliente');

        return $usuario?->contrato_id;
    }

    /** @return array<string, mixed> */
    public function cascara(Request $request): array
    {
        /** @var SecUser|null $usuario */
        $usuario = $request->user('cliente');

        $tema = TemaPreferencia::Claro;

        if ($usuario !== null) {
            $preferencia = SecUserPreferencia::query()->where('user_id', $usuario->id)->first();
            $tema = $preferencia->tema ?? TemaPreferencia::Claro;
        }

        return [
            'userName' => $usuario?->name,
            'tema' => $tema->atributoBootstrap(),
        ];
    }
}
