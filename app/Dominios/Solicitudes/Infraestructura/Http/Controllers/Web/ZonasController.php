<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\Macrorregion;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\ActualizarZonaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Zonas de Santa Cruz en el panel: `GET /panel/zonas` (cada zona con su
 * macrorregión, sus terrenos y la demanda abierta) y la edición de su nombre y
 * macrorregión. Las zonas alimentan los filtros del mapa de los pilotos y los
 * avisos de nuevas solicitudes; no se crean ni se borran desde acá.
 *
 * Gateada por `solicitudes.zona.ver`; editar, por `solicitudes.zona.editar`,
 * ambos contra el ROL ACTIVO (invariante 10).
 */
final class ZonasController
{
    private const PERMISO_VER = 'solicitudes.zona.ver';

    private const PERMISO_EDITAR = 'solicitudes.zona.editar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $zonas = Zona::query()->orderBy('macrorregion')->orderBy('nombre')->get();

        $terrenos = Terreno::query()->selectRaw('zona_id, COUNT(*) as cantidad')->whereNotNull('zona_id')->groupBy('zona_id')->pluck('cantidad', 'zona_id');
        $abiertas = SolicitudServicio::query()
            ->join('sol_terrenos as t', 't.id', '=', 'sol_solicitudes_servicio.terreno_id')
            ->whereIn('sol_solicitudes_servicio.estado', ['abierta', 'con_ofertas'])
            ->whereNotNull('t.zona_id')
            ->selectRaw('t.zona_id as zona_id, COUNT(*) as cantidad')
            ->groupBy('t.zona_id')
            ->pluck('cantidad', 'zona_id');

        return view('solicitudes::pages.zonas.index', [
            ...$this->autorizacion->cascara($request),
            'zonas' => $zonas,
            'terrenos' => $terrenos->map(fn ($n): int => (int) $n)->all(),
            'abiertas' => $abiertas->map(fn ($n): int => (int) $n)->all(),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
        ]);
    }

    public function edit(Request $request, Zona $zona): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('solicitudes::pages.zonas.edit', [
            ...$this->autorizacion->cascara($request),
            'zona' => $zona,
            'regiones' => Macrorregion::cases(),
        ]);
    }

    public function update(ActualizarZonaRequest $request, Zona $zona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $zona->fill([
            'nombre' => (string) $request->validated('nombre'),
            'macrorregion' => (string) $request->validated('macrorregion'),
        ])->save();

        return redirect()
            ->route('panel.zonas.index')
            ->with('estado', __('solicitudes.zonas.actualizada', ['zona' => $zona->nombre]));
    }
}
