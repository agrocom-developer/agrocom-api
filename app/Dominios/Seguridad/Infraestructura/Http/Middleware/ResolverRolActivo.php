<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Middleware;

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\RolActivoController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve y revalida el rol activo de la sesión del panel en cada request
 * autenticado (ADR 0004, extensión 27/8/2026, punto 2). Corre después de
 * `auth:interno` — nunca antes, necesita `$request->user('interno')`
 * resuelto.
 *
 * En cada request:
 * 1. Lee `session('sec_rol_activo_id')`.
 * 2. Si el valor sigue entre los roles vivos del usuario
 *    (`sec_user_role.deleted_at IS NULL` + `sec_role.state = true`), deja
 *    pasar el request tal cual — el rol activo queda disponible en la
 *    sesión para el resto del pipeline (controladores, Blade, Livewire).
 * 3. Si no hay valor, o el valor ya no es válido (revocado a mitad de
 *    sesión — el caso que esta revalidación existe para cubrir) y el
 *    usuario tiene un único rol vivo, se lo fija automáticamente (mismo
 *    criterio que el login) y deja pasar.
 * 4. En cualquier otro caso (cero o dos+ roles vivos sin rol activo
 *    resoluble) no deja pasar: no hay panel sin rol activo. Con
 *    `Accept: application/json` responde 409 con los roles disponibles (para
 *    que el panel arme el selector sin recargar la página); si no, redirige
 *    a la pantalla de selección de rol.
 *
 * Se revalida contra la base en cada request (un `exists()`/`first()`
 * indexado por `(id_user, id_role)`) en vez de confiar en el valor de sesión
 * sin chequeo — una revocación de rol a mitad de sesión tiene efecto en el
 * siguiente request, no recién cuando el usuario intente cambiar de rol a
 * mano. Cachear esta revalidación queda explícitamente diferido (ADR 0004,
 * extensión 27/8/2026, alternativas descartadas) hasta que el volumen lo
 * justifique.
 */
final class ResolverRolActivo
{
    private const CLAVE_SESION = 'sec_rol_activo_id';

    /**
     * Nombre de ruta del selector de rol (`GET /panel/seleccionar-rol`,
     * {@see RolActivoController::create()}).
     * Se sigue verificando con `Route::has()` antes de redirigir — mismo
     * criterio defensivo que antes de que la ruta existiera, ahora cubre el
     * caso de que `routes/web.php` no la haya registrado en un entorno dado
     * (p. ej. un test que monta rutas ad-hoc sin cargar `routes/web.php`
     * completo) en vez de lanzar `RouteNotFoundException`.
     */
    private const RUTA_SELECTOR = 'panel.rol-activo.selector';

    public function __construct(
        private readonly ElegirRolActivo $elegirRolActivo,
        private readonly ListarRolesDisponibles $listarRolesDisponibles,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var SecUser|null $usuario */
        $usuario = $request->user('interno');

        if ($usuario === null) {
            // No debería ocurrir con `auth:interno` antes en la cadena, pero
            // este middleware no es responsable de exigir autenticación —
            // solo de resolver el rol activo de quien ya está autenticado.
            return $next($request);
        }

        $idsRolesVivos = $usuario->idsDeRolesActivos();
        $idRolActivo = $request->session()->get(self::CLAVE_SESION);

        if ($idRolActivo !== null && in_array((int) $idRolActivo, $idsRolesVivos, true)) {
            return $next($request);
        }

        if (count($idsRolesVivos) === 1) {
            $this->elegirRolActivo->ejecutar($usuario, $idsRolesVivos[0]);

            return $next($request);
        }

        // "Entrar siempre con este rol" (quinta vuelta, maqueta 5c): con
        // 2+ roles vivos y un preferido todavía vivo, se activa solo — mismo
        // criterio que el login. Si el preferido dejó de estar vivo, se
        // ignora (nunca fallback silencioso a otro rol) y se cae a la
        // selección explícita de siempre.
        $idRolPreferido = SecUserPreferencia::query()
            ->where('user_id', $usuario->id)
            ->value('rol_preferido_id');

        if ($idRolPreferido !== null && in_array((int) $idRolPreferido, $idsRolesVivos, true)) {
            $this->elegirRolActivo->ejecutar($usuario, (int) $idRolPreferido);

            return $next($request);
        }

        // Cero o dos+ roles vivos sin rol activo resoluble: ningún fallback
        // a "permitir todo" ni a la unión (ADR 0004, extensión 27/8/2026,
        // punto 3) — se limpia cualquier valor de sesión obsoleto y no se
        // deja pasar el request.
        $request->session()->forget(self::CLAVE_SESION);

        $rolesDisponibles = $this->listarRolesDisponibles->ejecutar($usuario);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Debe seleccionar un rol activo antes de continuar.',
                'roles' => $rolesDisponibles,
            ], 409);
        }

        return Route::has(self::RUTA_SELECTOR)
            ? redirect()->route(self::RUTA_SELECTOR)
            : redirect('/');
    }
}
