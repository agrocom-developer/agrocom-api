<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Controllers\Web;

use App\Dominios\Asignacion\Aplicacion\LevantarSancion;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Sancion;
use App\Dominios\Asignacion\Infraestructura\Http\Requests\LevantarSancionRequest;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sanciones de los proveedores (ADR 0038): `GET /panel/sanciones` (las que
 * siguen vigentes primero) y `POST /panel/sanciones/{sancion}/levantar` (la
 * apelación o la decisión de una revisión de suspensión, con motivo). Ninguna
 * sanción suspende la cuenta: eso sigue siendo del panel de proveedores.
 *
 * Gateada por `asignacion.sancion.ver` (y `.levantar` para la acción),
 * verificados DENTRO del controlador contra el ROL ACTIVO (invariante 10).
 */
final class SancionesController
{
    private const PERMISO_VER = 'asignacion.sancion.ver';

    private const PERMISO_LEVANTAR = 'asignacion.sancion.levantar';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaProveedores $proveedores,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $sanciones = Sancion::query()
            ->orderByRaw('CASE WHEN levantada_en IS NULL AND (hasta IS NULL OR hasta > ?) THEN 0 ELSE 1 END', [now()])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $nombres = [];
        foreach ($sanciones->getCollection()->pluck('proveedor_id')->unique() as $proveedorId) {
            $nombres[$proveedorId] = $this->proveedores->proveedor((int) $proveedorId)->razonSocial ?? "#{$proveedorId}";
        }

        return view('asignacion::pages.sanciones.index', [
            ...$this->autorizacion->cascara($request),
            'sanciones' => $sanciones,
            'nombres' => $nombres,
            'puedeLevantar' => $this->autorizacion->tienePermiso($request, self::PERMISO_LEVANTAR),
        ]);
    }

    public function levantar(LevantarSancionRequest $request, int $sancion, LevantarSancion $levantar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_LEVANTAR), 403);

        try {
            $levantar->ejecutar((int) $request->user('interno')->getAuthIdentifier(), $sancion, (string) $request->validated('motivo'));
        } catch (AsignacionInvalida $excepcion) {
            return redirect()->route('panel.sanciones.index')->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.sanciones.index')->with('estado', __('asignacion.panel.sancion_levantada'));
    }
}
