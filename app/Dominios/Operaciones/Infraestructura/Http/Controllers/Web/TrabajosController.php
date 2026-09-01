<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/trabajos` (HU-05, tarea 13): pantalla mínima de Operaciones —
 * lista de trabajos con su estado y sus sesiones, para que el jefe vea que
 * algo se cerró. Sin filtros ni detalle de evidencias (eso es HU-15).
 *
 * Mismo patrón que `VersionesApkController` (HU-20): un único permiso
 * (`operaciones.trabajo.ver`) gatea toda la pantalla, verificado DENTRO del
 * controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} — nunca
 * `SecUser`/`CascaraPanel` directos (ADR 0003, regla 2).
 */
final class TrabajosController
{
    private const PERMISO = 'operaciones.trabajo.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return view('operaciones::pages.trabajos.index', [
            ...$this->autorizacion->cascara($request),
            'trabajos' => Trabajo::query()
                ->with('sesiones')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
