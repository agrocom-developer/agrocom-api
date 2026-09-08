<?php

namespace App\Dominios\Campania\Infraestructura\Http\Middleware;

use App\Dominios\Campania\Aplicacion\ElegirCampaniaActiva;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve y revalida la campaña activa de la sesión del panel en cada
 * request autenticado (ADR 0015 punto 1, tarea 69) — espejo exacto de
 * `Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo`, con una
 * diferencia deliberada: **nunca bloquea el request**. "La campaña activa
 * filtra, no autoriza" (prompt de la tarea): a diferencia del rol activo, no
 * hay pantalla sin campaña activa — el chip del header simplemente no se
 * pinta y los listados que filtran por campaña muestran "todas".
 *
 * En cada request:
 * 1. Lee `session('cpn_campania_activa_id')`.
 * 2. Si el valor sigue apuntando a una campaña no borrada, deja pasar el
 *    request tal cual.
 * 3. Si no hay valor, o el valor ya no es válido (la campaña se borró a
 *    mitad de sesión), intenta el default automático
 *    ({@see ElegirCampaniaActiva::resolverPorDefecto()}). Si tampoco hay
 *    ninguna campaña `abierta`, limpia cualquier valor de sesión obsoleto.
 * 4. Deja pasar el request SIEMPRE — nunca redirige, nunca responde 409.
 */
final class ResolverCampaniaActiva
{
    private const CLAVE_SESION = 'cpn_campania_activa_id';

    public function __construct(private readonly ElegirCampaniaActiva $elegirCampaniaActiva) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('interno') === null) {
            // No debería ocurrir con `auth:interno` antes en la cadena, pero
            // este middleware no es responsable de exigir autenticación —
            // solo de resolver la campaña activa de quien ya está autenticado.
            return $next($request);
        }

        $idCampaniaActiva = $request->session()->get(self::CLAVE_SESION);

        if ($idCampaniaActiva !== null && Campania::query()->whereKey($idCampaniaActiva)->exists()) {
            return $next($request);
        }

        if ($this->elegirCampaniaActiva->resolverPorDefecto() === null) {
            $request->session()->forget(self::CLAVE_SESION);
        }

        return $next($request);
    }
}
