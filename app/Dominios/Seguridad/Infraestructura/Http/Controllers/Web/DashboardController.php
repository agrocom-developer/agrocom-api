<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Metricas\Contratos\LecturaTablero;
use App\Dominios\Seguridad\Aplicacion\ListarCandidatosVerComo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/dashboard` (`panel.dashboard`): el tablero de la torre de control
 * del marketplace y página de aterrizaje del panel tras el login. Gateada por
 * `seguridad.dashboard.ver`, verificado contra el ROL ACTIVO (invariante 10).
 *
 * Adaptador delgado (ADR 0008): la cáscara la resuelve {@see CascaraPanel} y los
 * números los entrega `Metricas` por su contrato {@see LecturaTablero}. Qué
 * accesos a colas aparecen lo decide el permiso de cada pantalla de destino: el
 * tablero nunca enseña un enlace que el rol activo no pueda abrir.
 */
final class DashboardController
{
    private const PERMISO_VER = 'seguridad.dashboard.ver';

    private const PERMISO_VER_COMO = 'seguridad.usuario.ver_como';

    /** Cola de pendientes → ruta y permiso de la pantalla que la atiende. */
    private const COLAS = [
        'proveedores' => ['panel.proveedores.index', 'proveedores.proveedor.ver', ['estado' => 'pendiente']],
        'pilotos' => ['panel.pilotos.index', 'proveedores.piloto.ver', ['estado' => 'pendiente']],
        'auxiliares' => ['panel.auxiliares.index', 'proveedores.auxiliar.ver', ['estado' => 'pendiente']],
        'drones' => ['panel.drones-flota.index', 'flota.dron.ver', ['estado' => 'pendiente']],
        'fotos_dron' => ['panel.drones-flota.index', 'flota.dron.ver', ['fotos' => 'en_revision']],
        'documentos' => ['panel.documentos.index', 'proveedores.documento.ver', []],
        'calificaciones' => ['panel.calificaciones.index', 'calificaciones.calificacion.ver', []],
        'disputas' => ['panel.disputas.index', 'disputas.disputa.ver', []],
    ];

    public function index(
        Request $request,
        AutorizacionPanelWeb $autorizacion,
        CascaraPanel $cascara,
        LecturaTablero $lectura,
        ListarCandidatosVerComo $listarCandidatosVerComo,
    ): View {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        $colas = [];
        foreach (self::COLAS as $clave => [$ruta, $permiso, $filtros]) {
            if ($autorizacion->tienePermiso($request, $permiso)) {
                $colas[$clave] = route($ruta, $filtros);
            }
        }

        // Acceso directo a «ver como» (botón junto al chip de rol activo del
        // encabezado): mismo permiso reservado que ya gatea el flujo desde
        // Seguridad → Usuarios, revalidado acá porque el chip es otra pantalla.
        $puedeVerComo = $autorizacion->tienePermiso($request, self::PERMISO_VER_COMO);
        $datosCascara = $cascara->para($usuario, $idRolActivo);

        return view('seguridad::pages.dashboard', [
            ...$datosCascara,
            'tablero' => $lectura->tablero(is_string($request->query('dias')) ? $request->query('dias') : null),
            'diasValidos' => $lectura->diasValidos(),
            'colas' => $colas,
            'puedeVerSolicitudes' => $autorizacion->tienePermiso($request, 'solicitudes.solicitud.ver'),
            'puedeVerMetricas' => $autorizacion->tienePermiso($request, 'metricas.panel.ver'),
            'puedeVerComo' => $puedeVerComo,
            'candidatosVerComo' => $puedeVerComo ? $listarCandidatosVerComo->ejecutar($usuario) : null,
        ]);
    }
}
