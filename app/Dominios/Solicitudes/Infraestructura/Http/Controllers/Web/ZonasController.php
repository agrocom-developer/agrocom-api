<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Solicitudes\Aplicacion\GestionarZonas;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\GuardarZonaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * El catálogo de zonas de Santa Cruz en el panel (`/panel/zonas`): listado con
 * búsqueda por región, alta, edición y baja. Las zonas agrupan el mapa de los
 * pilotos, los avisos y el tablero.
 *
 * Gateada por `solicitudes.zona.ver`; crear, editar y dar de baja exigen además
 * `solicitudes.zona.gestionar`, verificados DENTRO del controlador contra el ROL
 * ACTIVO (invariante 10).
 */
final class ZonasController
{
    private const PERMISO_VER = 'solicitudes.zona.ver';

    private const PERMISO_GESTIONAR = 'solicitudes.zona.gestionar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, GestionarZonas $zonas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $region = in_array(TextoDeFiltro::de($request, 'region'), GuardarZonaRequest::REGIONES, true) ? TextoDeFiltro::de($request, 'region') : null;

        $listado = Zona::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['nombre'], $busqueda))
            ->when($region !== null, fn ($consulta) => $consulta->where('macrorregion', $region))
            ->orderBy('macrorregion')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        $ids = $listado->getCollection()->pluck('id')->all();
        $terrenos = DB::table('sol_terrenos')->whereIn('zona_id', $ids)->whereNull('deleted_at')->selectRaw('zona_id, COUNT(*) as n')->groupBy('zona_id')->pluck('n', 'zona_id');
        $proveedores = DB::table('prv_proveedores')->whereIn('zona_base_id', $ids)->whereNull('deleted_at')->selectRaw('zona_base_id, COUNT(*) as n')->groupBy('zona_base_id')->pluck('n', 'zona_base_id');

        return view('solicitudes::pages.zonas.index', [
            ...$this->autorizacion->cascara($request),
            'zonas' => $listado,
            'terrenos' => $terrenos,
            'proveedores' => $proveedores,
            'filtros' => ['q' => $busqueda, 'region' => $region],
            'regiones' => GuardarZonaRequest::REGIONES,
            'puedeGestionar' => $this->autorizacion->tienePermiso($request, self::PERMISO_GESTIONAR),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_GESTIONAR), 403);

        return view('solicitudes::pages.zonas.create', [
            ...$this->autorizacion->cascara($request),
            'zona' => null,
            'regiones' => GuardarZonaRequest::REGIONES,
        ]);
    }

    public function store(GuardarZonaRequest $request, GestionarZonas $zonas): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_GESTIONAR), 403);

        $zonas->crear($request->validated());

        return redirect()->route('panel.zonas.index')->with('estado', __('solicitudes.zonas.creada'));
    }

    public function edit(Request $request, Zona $zona, GestionarZonas $zonas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_GESTIONAR), 403);

        return view('solicitudes::pages.zonas.edit', [
            ...$this->autorizacion->cascara($request),
            'zona' => $zona,
            'regiones' => GuardarZonaRequest::REGIONES,
            'usos' => $zonas->usos($zona->id),
        ]);
    }

    public function update(GuardarZonaRequest $request, Zona $zona, GestionarZonas $zonas): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_GESTIONAR), 403);

        $zonas->actualizar($zona, $request->validated());

        return redirect()->route('panel.zonas.index')->with('estado', __('solicitudes.zonas.actualizada'));
    }

    public function destroy(Request $request, Zona $zona, GestionarZonas $zonas): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_GESTIONAR), 403);

        try {
            $zonas->eliminar($zona);
        } catch (SolicitudInvalida $excepcion) {
            return redirect()->route('panel.zonas.index')->withErrors(['zona' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.zonas.index')->with('estado', __('solicitudes.zonas.eliminada'));
    }
}
