<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Metricas\Contratos\LecturaTableroDeMercado;
use App\Dominios\Seguridad\Aplicacion\ListarCandidatosVerComo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/dashboard` (`panel.dashboard`): página de aterrizaje del panel
 * tras el login. Gateada por `seguridad.dashboard.ver`.
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se
 * ejecuta, `session('sec_rol_activo_id')` ya es un rol vivo válido de este
 * usuario ({@see ResolverRolActivo} lo garantiza).
 *
 * Adaptador delgado (ADR 0008): la cáscara la resuelve {@see CascaraPanel} y
 * los datos del mercado los entrega el contrato {@see LecturaTableroDeMercado}.
 * De la cola de pendientes solo se muestran los tipos cuya pantalla puede abrir
 * el rol activo: el tablero nunca abre una ventana a lo que el menú no deja ver.
 */
final class DashboardController
{
    private const PERMISO_VER = 'seguridad.dashboard.ver';

    private const PERMISO_VER_COMO = 'seguridad.usuario.ver_como';

    /** Cada tipo de pendiente, su pantalla y el permiso que la abre. */
    private const PENDIENTES = [
        'proveedores' => ['panel.proveedores.index', 'proveedores.proveedor.ver'],
        'pilotos' => ['panel.pilotos.index', 'proveedores.piloto.ver'],
        'drones' => ['panel.drones-flota.index', 'flota.dron.ver'],
        'fotos_de_dron' => ['panel.drones-flota.index', 'flota.dron.ver'],
        'documentos' => ['panel.documentos.index', 'proveedores.documento.ver'],
        'calificaciones' => ['panel.calificaciones.index', 'calificaciones.calificacion.ver'],
        'reclamos' => ['panel.reclamos.index', 'reclamos.reclamo.ver'],
    ];

    public function index(
        Request $request,
        AutorizacionPanelWeb $autorizacion,
        CascaraPanel $cascara,
        LecturaTableroDeMercado $mercado,
        ListarCandidatosVerComo $listarCandidatosVerComo,
    ): View {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        $tablero = $mercado->tablero();

        $pendientes = [];
        foreach (self::PENDIENTES as $clave => [$ruta, $permiso]) {
            if ($autorizacion->tienePermiso($request, $permiso)) {
                $pendientes[] = ['clave' => $clave, 'cantidad' => $tablero['pendientes'][$clave] ?? 0, 'ruta' => $ruta];
            }
        }

        // Acceso directo a «ver como» (botón junto al chip de rol activo del
        // encabezado): mismo permiso reservado que ya gatea el flujo desde
        // Seguridad → Usuarios, revalidado acá porque el chip es otra
        // pantalla — nunca se confía en el estado de una vista anterior.
        $puedeVerComo = $autorizacion->tienePermiso($request, self::PERMISO_VER_COMO);

        return view('seguridad::pages.dashboard', [
            ...$cascara->para($usuario, $idRolActivo),
            'tablero' => $tablero,
            'pendientes' => $pendientes,
            'puedeVerSolicitudes' => $autorizacion->tienePermiso($request, 'solicitudes.solicitud.ver'),
            'puedeVerComo' => $puedeVerComo,
            'candidatosVerComo' => $puedeVerComo ? $listarCandidatosVerComo->ejecutar($usuario) : null,
        ]);
    }
}
