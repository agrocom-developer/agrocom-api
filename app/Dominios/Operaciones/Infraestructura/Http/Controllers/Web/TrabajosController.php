<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\ListarTrabajos;
use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * `GET /panel/trabajos` (HU-05, tarea 13; extendida en HU-15, tarea 15):
 * tablero de Operaciones — trabajos con filtros por estado de tablero, lote
 * y orden de aplicación, paginado. `GET /panel/trabajos/{trabajo}`: detalle
 * de un trabajo con sus sesiones. Ambas de solo lectura — no mutan estado
 * ni dinero, y no tocan la cola de validación de la tarea 14 (pantalla
 * distinta, aunque lea las mismas tablas).
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

    public function index(Request $request, ListarTrabajos $listarTrabajos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoTableroTrabajo::tryFrom($estadoQuery) : null;
        $loteId = $request->filled('lote_id') ? $request->integer('lote_id') : null;
        $ordenId = $request->filled('orden_id') ? $request->integer('orden_id') : null;

        return view('operaciones::pages.trabajos.index', [
            ...$this->autorizacion->cascara($request),
            'trabajos' => $listarTrabajos->ejecutar($estado, $loteId, $ordenId),
            'filtros' => [
                'estado' => $estado?->value,
                'lote_id' => $loteId,
                'orden_id' => $ordenId,
            ],
            'lotesDisponibles' => Trabajo::query()->select('lote_id')->distinct()->orderBy('lote_id')->pluck('lote_id'),
            'ordenesDisponibles' => Trabajo::query()->select('orden_id', 'nro_aplicacion')->distinct()->orderBy('orden_id')->get(),
        ]);
    }

    public function show(Request $request, Trabajo $trabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return view('operaciones::pages.trabajos.show', [
            ...$this->autorizacion->cascara($request),
            'trabajo' => $trabajo->load(['sesiones.rechazo', 'acta']),
        ]);
    }

    /**
     * `GET /panel/trabajos/{trabajo}/acta/pdf` (HU-17, tarea 24): solo
     * lectura, mismo permiso que `show()` — generar/firmar el acta es de
     * `agrocom-field` (`ActaController`, API), no del panel.
     */
    public function actaPdf(Request $request, Trabajo $trabajo): Response
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $acta = $trabajo->acta;

        abort_if($acta === null || $acta->pdf_path === null || ! Storage::disk('r2')->exists($acta->pdf_path), 404);

        return response(Storage::disk('r2')->get($acta->pdf_path), 200, ['Content-Type' => 'application/pdf']);
    }
}
