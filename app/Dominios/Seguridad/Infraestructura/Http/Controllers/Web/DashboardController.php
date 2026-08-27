<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/dashboard` (`panel.dashboard`): página de aterrizaje del panel
 * tras el login, sin permiso propio — visible para cualquier usuario
 * autenticado con rol activo resuelto (`sec_menu` la siembra sin
 * `permission_id`, {@see SecMenuSeeder}).
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se
 * ejecuta, `session('sec_rol_activo_id')` ya es un rol vivo válido de este
 * usuario ({@see ResolverRolActivo} lo garantiza) — acá no se vuelve a
 * revalidar esa pertenencia.
 *
 * Adaptador delgado (ADR 0008): resuelve el árbol de menú del ROL ACTIVO vía
 * {@see ObtenerMenuPorRolActivo} (nunca la unión de todos los roles del
 * usuario, invariante 10 de `CLAUDE.md`) y los datos de cáscara que
 * `templates/panel-layout` espera (roles para el selector de cambio de rol,
 * nombre del rol activo, nombre de usuario) — el contenido real del
 * dashboard lo ensambla `frontend` sobre esta vista.
 */
final class DashboardController
{
    public function index(
        Request $request,
        ObtenerMenuPorRolActivo $obtenerMenu,
        ListarRolesDisponibles $listarRolesDisponibles,
    ): View {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        $roles = $listarRolesDisponibles->ejecutar($usuario);

        return view('seguridad::pages.dashboard', [
            'menu' => $obtenerMenu->ejecutar($usuario, $idRolActivo),
            'roles' => $roles,
            'rolActivoId' => $idRolActivo,
            'activeRoleLabel' => $roles->firstWhere('id', $idRolActivo)?->name,
            'userName' => $usuario->name,
        ]);
    }
}
