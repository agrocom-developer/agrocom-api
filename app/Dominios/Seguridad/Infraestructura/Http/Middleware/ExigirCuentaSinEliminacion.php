<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Middleware;

use App\Dominios\Seguridad\Dominio\EstadoDeEliminacion;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecEliminacion;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una cuenta con la eliminación pedida (ADR 0047, punto 5) puede iniciar sesión, pero la sesión solo ve el estado, cancela la
 * eliminación y cierra sesión: todo lo demás responde `403 cuenta_en_eliminacion`. La app muestra el aviso y «Conservar mi cuenta».
 */
final class ExigirCuentaSinEliminacion
{
    /** Rutas que siguen disponibles mientras la eliminación está vigente. */
    private const PERMITIDAS = [
        'api.auth.sesion',
        'api.auth.token.revocar',
        'api.cuenta.eliminacion.estado',
        'api.cuenta.eliminacion.cancelar',
    ];

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var SecUser|null $usuario */
        $usuario = $request->user();

        if ($usuario === null || in_array($request->route()?->getName(), self::PERMITIDAS, true)) {
            return $next($request);
        }

        $enEliminacion = SecEliminacion::query()
            ->where('usuario_id', $usuario->id)
            ->whereIn('estado', [EstadoDeEliminacion::Solicitada->value, EstadoDeEliminacion::Pospuesta->value])
            ->exists();

        if ($enEliminacion) {
            return response()->json(['message' => __('seguridad.eliminacion.cuenta_en_eliminacion'), 'codigo' => 'cuenta_en_eliminacion'], 403);
        }

        return $next($request);
    }
}
