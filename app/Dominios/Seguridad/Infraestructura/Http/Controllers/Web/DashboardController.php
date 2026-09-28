<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ArmarDashboard;
use App\Dominios\Seguridad\Aplicacion\ListarCandidatosVerComo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET /panel/dashboard` (`panel.dashboard`): página de aterrizaje del panel
 * tras el login. Gateada por `seguridad.dashboard.ver` (tarea 62, fuga 2).
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se
 * ejecuta, `session('sec_rol_activo_id')` ya es un rol vivo válido de este
 * usuario ({@see ResolverRolActivo} lo garantiza) — acá no se vuelve a
 * revalidar esa pertenencia.
 *
 * Adaptador delgado (ADR 0008): la cáscara la resuelve {@see CascaraPanel} y
 * el contenido {@see ArmarDashboard}. Desde la tarea 67 no queda ningún dato
 * de maqueta: cada sección la sirve el módulo dueño por su contrato de
 * lectura, y qué secciones aparecen lo decide el ROL ACTIVO — un piloto y un
 * dueño abren la misma ruta y ven tableros distintos.
 */
final class DashboardController
{
    private const PERMISO_VER = 'seguridad.dashboard.ver';

    private const PERMISO_VER_COMO = 'seguridad.usuario.ver_como';

    public function index(
        Request $request,
        AutorizacionPanelWeb $autorizacion,
        CascaraPanel $cascara,
        ArmarDashboard $armarDashboard,
        ListarCandidatosVerComo $listarCandidatosVerComo,
    ): View {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        $datosCascara = $cascara->para($usuario, $idRolActivo);

        // La clave del rol activo (`dueno`, `piloto`...) ya la resolvió
        // `CascaraPanel::para()` para armar `activeRoleLabel` — se relee de
        // `roles` (ya en memoria, sin consulta nueva) en vez de duplicar esa
        // búsqueda. `tabsPara()` (tarea 135) decide el agrupamiento de tabs
        // por rol; sin rol resuelto, cae en el agrupamiento por defecto.
        /** @var Collection<int, SecRole> $roles */
        $roles = $datosCascara['roles'];
        $rolActivo = $roles->firstWhere('id', $idRolActivo);
        $rolClave = $rolActivo->name ?? '';

        // Acceso directo a «ver como» (botón junto al chip de rol activo del
        // encabezado): mismo permiso reservado que ya gatea el flujo desde
        // Seguridad → Usuarios, revalidado acá porque el chip es otra
        // pantalla — nunca se confía en el estado de una vista anterior.
        $puedeVerComo = $autorizacion->tienePermiso($request, self::PERMISO_VER_COMO);

        return view('seguridad::pages.dashboard', [
            ...$datosCascara,
            ...$armarDashboard->ejecutar($usuario, $idRolActivo),
            'tabs' => $armarDashboard->tabsPara($rolClave),
            'tecnico' => $armarDashboard->esTecnico($rolClave),
            'puedeVerComo' => $puedeVerComo,
            'candidatosVerComo' => $puedeVerComo ? $listarCandidatosVerComo->ejecutar($usuario) : null,
        ]);
    }
}
