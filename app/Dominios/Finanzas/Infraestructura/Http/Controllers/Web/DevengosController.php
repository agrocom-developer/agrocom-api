<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Aplicacion\ListarDevengosPersona;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/devengos*` (HU-28, tarea 40): "como piloto o auxiliar, quiero
 * ver mis devengos por período" — primer `Http/` del módulo `Finanzas`.
 *
 * Un único permiso (`finanzas.devengo.ver`) gatea ambas acciones. La
 * identidad se resuelve por PERSONA, no por rol/permiso (invariante distinta
 * de todo lo demás en el panel, que solo filtra por `tienePermiso()`): `show()`
 * hace `abort(404)` si la `persona_id` pedida no es la del usuario
 * autenticado, sin importar qué permiso tenga el actor — "lo suyo", no "lo
 * suyo y lo de quien tenga más permiso".
 *
 * Depende de {@see AutorizacionPanelWeb} (contrato de Seguridad, ADR 0003
 * regla 2), nunca de `SecUser` directo.
 */
final class DevengosController
{
    private const PERMISO_VER = 'finanzas.devengo.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $personaId = $this->autorizacion->personaId($request);

        abort_if($personaId === null, 404);

        return redirect()->route('panel.devengos.show', $personaId);
    }

    public function show(Request $request, int $persona, ListarDevengosPersona $listarDevengos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $personaId = $this->autorizacion->personaId($request);

        abort_if($personaId === null || $persona !== $personaId, 404);

        $periodo = $request->string('periodo')->toString();
        $resultado = $listarDevengos->ejecutar($persona, $periodo !== '' ? $periodo : null);

        return view('finanzas::pages.devengos.show', [
            ...$this->autorizacion->cascara($request),
            'personaId' => $persona,
            'devengos' => $resultado['devengos'],
            'total' => $resultado['total'],
            'periodoFiltro' => $resultado['periodo'],
        ]);
    }
}
