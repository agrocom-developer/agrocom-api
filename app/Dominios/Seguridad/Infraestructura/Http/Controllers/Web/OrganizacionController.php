<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/organizacion` (`panel.organizacion.index`): pantalla de PRESENTACIÓN
 * del "Registro de la compañía" — mockup visual para una conversación sobre un pivot
 * SaaS multi-tenant que NO está decidido, sin ADR, sin tabla `tenant`/`organizacion`,
 * sin guard nuevo.
 *
 * Deliberadamente GET/solo-lectura: NO hay ningún endpoint POST que "guarde" nada —
 * sería una mutación fantasma sin persistencia real y sin bitácora (rompe el invariante 9
 * de `CLAUDE.md`). El botón "Guardar" está deshabilitado con un help text tipo
 * "Vista previa — sin guardado real en esta versión".
 *
 * Autorización: visible sin permiso propio (mismo patrón que "Inicio"), para cualquier
 * usuario autenticado del panel. El ítem de menú vive en `sec_menu` con `permission_id = null`
 * ({@see Database\Seeders\Catalogo\SecMenuSeeder}).
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se ejecuta,
 * `session('sec_rol_activo_id')` ya es un rol vivo válido de este usuario.
 *
 * Adaptador delgado (ADR 0008): resuelve el árbol de menú del ROL ACTIVO (nunca la unión
 * de todos los roles del usuario, invariante 10 de `CLAUDE.md`) y datos de cáscara que
 * `templates/panel-layout` espera — el contenido es MOCK (datos prellenados realistas)
 * para demostración visual.
 *
 * MOCK (este es el estado final, no hay persistencia real):
 * - Nombre/rubro/logo de empresa.
 * - Datos de contacto (email, teléfono, dirección).
 * - 3 plan-card (Básico/Profesional/Enterprise).
 * - Switch de "multi-sucursal".
 */
final class OrganizacionController
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

        return view('seguridad::pages.organizacion.index', [
            'menu' => $obtenerMenu->ejecutar($usuario, $idRolActivo),
            'roles' => $roles,
            'rolActivoId' => $idRolActivo,
            'activeRoleLabel' => $roles->firstWhere('id', $idRolActivo)?->name,
            'userName' => $usuario->name,
        ]);
    }
}
