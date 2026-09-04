<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoCapturasRc;
use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoMapaOperativo;
use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoPanel;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/dashboard` (`panel.dashboard`): página de aterrizaje del panel
 * tras el login — el "Operación de hoy", panel visual/estadístico con
 * pestañas Resumen / Mapa / Resumen por lote / Multimedia. Gateada por
 * `seguridad.dashboard.ver` (tarea 62, fuga 2: antes era visible para
 * cualquier usuario autenticado con rol activo resuelto, sin permiso propio
 * — un `auxiliar` veía el tablero completo). Un rol sin este permiso nunca
 * aterriza acá tras el login: {@see AutorizacionPanelWeb::primerDestinoVisible()}
 * resuelve el primer ítem de menú que sí puede ver.
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
    private const PERMISO_VER = 'seguridad.dashboard.ver';

    public function index(
        Request $request,
        AutorizacionPanelWeb $autorizacion,
        CascaraPanel $cascara,
        DatosDemoPanel $demo,
        DatosDemoMapaOperativo $demoMapa,
        DatosDemoCapturasRc $demoCapturas,
    ): View {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

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
            'resumenPorLote' => $demoMapa->resumenPorLote(),
            'capturasRc' => $demoCapturas->sesiones(),
            'sesiones' => $demo->sesiones(),
            'pausas' => $demo->pausas(),
            'stock' => $demo->stockBajoMinimo(),
            'alertaRc' => $demo->alertaRc(),
        ]);
    }
}
