<?php

namespace App\Dominios\Compartido\Infraestructura\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a entornos no productivos (ADR 0014): la documentación
 * Swagger (`/api/documentation`) existe en local/staging y desaparece en
 * producción. Responde 404 — no 403 — para no revelar que la ruta existe.
 * `testing` está en la lista para que la suite pueda ejercitar la UI.
 */
class SoloEntornosNoProductivos
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            abort(404);
        }

        return $next($request);
    }
}
