<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoMapaOperativo;
use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoPanel;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/dashboard` (`panel.dashboard`): página de aterrizaje del panel
 * tras el login — el "Operación de hoy", panel visual/estadístico con
 * pestañas Resumen / Mapa / Resumen por lote / Multimedia. Sin permiso
 * propio: visible para cualquier usuario autenticado con rol activo
 * resuelto (`sec_menu` la siembra sin `permission_id`, {@see SecMenuSeeder}).
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se
 * ejecuta, `session('sec_rol_activo_id')` ya es un rol vivo válido de este
 * usuario ({@see ResolverRolActivo} lo garantiza) — acá no se vuelve a
 * revalidar esa pertenencia.
 *
 * Adaptador delgado (ADR 0008): la cáscara (menú del rol activo, roles,
 * tema, chrome) la resuelve {@see CascaraPanel}; el contenido del dashboard
 * es íntegramente DEMO ({@see DatosDemoPanel} — sesiones, pausas, stock,
 * ventana volable), a reemplazar por los casos de uso reales de cada módulo
 * cuando existan.
 */
final class DashboardController
{
    public function index(
        Request $request,
        CascaraPanel $cascara,
        DatosDemoPanel $demo,
        DatosDemoMapaOperativo $demoMapa,
    ): View {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        return view('seguridad::pages.dashboard', [
            ...$cascara->para($usuario, $idRolActivo),
            'fechaBajada' => $demo->fechaBajada(),
            'ventana' => $demo->ventanaVolable(),
            'distribucion' => $demo->distribucionSesiones(),
            'hectareasPorDia' => $demo->hectareasPorDia(),
            'avanceMeta' => $demo->avanceMeta(),
            'detalleClientes' => $demo->detalleClientes(),
            'mapaLotes' => $demoMapa->lotes(),
            'mapaSesiones' => $demoMapa->sesionesGeo(),
            'resumenMapa' => $demoMapa->resumenMapa(),
            'sesiones' => $demo->sesiones(),
            'pausas' => $demo->pausas(),
            'stock' => $demo->stockBajoMinimo(),
            'alertaRc' => $demo->alertaRc(),
        ]);
    }
}
