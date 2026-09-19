<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\CrearLotesMasivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\GenerarLotesRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\LotesBloqueRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST /panel/propiedades/{propiedad}/lotes/generar` (HU-72
 * reconstruida, 16/9/2026 — la original vivía en `CrearCampo`/
 * `CamposController`, borrados enteros al colapsar `Campo`, ADR 0020).
 * "Crear Lotes" con un solo botón: cuántos, con qué prefijo de código, con
 * cuántas hectáreas y con qué atributos de terreno (los mismos para todos) —
 * SIN cultivo/campaña (eso es siembra, vive en `propiedades/siembra`, no
 * acá; ver docblock de `CrearLotesMasivo`). Corregir después lo de todos los
 * lotes de una vez es `EditarLotesBloqueController`. Entra desde la ficha de la propiedad,
 * mismo molde que `SiembraController`/`PropiedadMapaController` —
 * pantalla propia sin listado, sin ABM propio.
 *
 * Reusa el permiso `comercial.lote.crear` (de Lote, no de Propiedad): esta
 * pantalla ES un alta de lotes, aunque se entre desde la ficha de la
 * propiedad — mismo criterio que la tarjeta "Lotes" del aside.
 */
final class GenerarLotesController
{
    private const PERMISO = 'comercial.lote.crear';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function mostrar(Request $request, Propiedad $propiedad): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return view('comercial::pages.propiedades.lotes-generar', [
            ...$this->autorizacion->cascara($request),
            'propiedad' => $propiedad->load('cliente'),
            'lotesExistentes' => $propiedad->lotes()->count(),
            // Lo que ya tienen los lotes de esta propiedad: la sugerencia de
            // hectáreas de una tanda nueva reparte lo que FALTA, no toda la
            // superficie (con tandas sucesivas, la segunda ya no parte de cero).
            'hectareasAsignadas' => (string) $propiedad->lotes()
                ->pluck('hectareas')
                ->reduce(fn (BigDecimal $suma, mixed $hectareas): BigDecimal => $suma->plus((string) $hectareas), BigDecimal::zero()),
            'lotesPorTanda' => LotesBloqueRequest::LOTES_MAXIMOS_POR_TANDA,
        ]);
    }

    public function guardar(GenerarLotesRequest $request, Propiedad $propiedad, CrearLotesMasivo $crearLotesMasivo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();

        $lotes = $crearLotesMasivo->ejecutar(
            $propiedad,
            $datos['prefijo'],
            (int) $datos['cantidad'],
            (string) $datos['hectareas'],
            $request->atributosTerreno(),
        );

        return redirect()
            ->route('panel.lotes.index', ['propiedad_id' => $propiedad->id])
            ->with('estado', __('comercial.propiedades.lotes_generados', ['cantidad' => count($lotes)]));
    }
}
