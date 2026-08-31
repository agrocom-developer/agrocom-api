<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\SeguridadServiceProvider;
use Illuminate\Support\Facades\Auth;

/**
 * Punto único desde el que una vista Blade decide si mostrar un control
 * (botón, link, acción de fila) — respaldo de la directiva `@puede` que
 * registra {@see SeguridadServiceProvider}.
 *
 * El menú ya llega filtrado desde `ObtenerMenuPorRolActivo`, pero los botones
 * de acción DENTRO de una pantalla no pasan por ahí: sin esto, cada vista
 * tendría que resolver por su cuenta el usuario y el rol activo de sesión, y
 * la primera que lo resolviera con `tienePermiso()` (unión) rompería la
 * invariante 10 de `CLAUDE.md` sin que nada lo notara.
 *
 * Fail-closed en los tres casos en que no hay contexto suficiente para
 * afirmar el permiso — sin usuario del guard `interno`, sin rol activo en
 * sesión, o con un rol activo que no es un entero: se oculta. Un `@puede`
 * que en la duda muestra el botón es exactamente el fail-open que el ADR
 * 0004 (extensión 27/8/2026, punto 5) descartó al separar
 * `tienePermisoEnRol()` de `tienePermiso()`.
 *
 * Ocultar el control NO reemplaza la autorización del servidor: la pantalla
 * detrás sigue validando (p. ej. `UsuariosController::index()` con
 * `abort_unless`). Esto es presentación, no una compuerta de seguridad.
 */
final class PermisoVista
{
    public static function puede(string $codigo): bool
    {
        $usuario = Auth::guard('interno')->user();

        if (! $usuario instanceof SecUser) {
            return false;
        }

        $idRolActivo = session('sec_rol_activo_id');

        if (! is_int($idRolActivo)) {
            return false;
        }

        return $usuario->tienePermisoEnRol($codigo, $idRolActivo);
    }
}
