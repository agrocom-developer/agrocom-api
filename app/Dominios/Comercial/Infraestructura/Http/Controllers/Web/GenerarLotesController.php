<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\CrearLotesMasivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\GenerarLotesRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST /panel/propiedades/{propiedad}/lotes/generar` (HU-72
 * reconstruida, 16/9/2026 — la original vivía en `CrearCampo`/
 * `CamposController`, borrados enteros al colapsar `Campo`, ADR 0020).
 * "Crear Lotes" con un solo botón: cuántos, con qué prefijo de código y
 * con qué atributos de terreno (los mismos para todos) — SIN cultivo/
 * campaña (eso es siembra, vive en `propiedades/siembra`, no acá; ver
 * docblock de `CrearLotesMasivo`). Entra desde la ficha de la propiedad,
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
        ]);
    }

    public function guardar(GenerarLotesRequest $request, Propiedad $propiedad, CrearLotesMasivo $crearLotesMasivo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();
        $terreno = $datos['terreno'] ?? [];

        $lotes = $crearLotesMasivo->ejecutar(
            $propiedad,
            $datos['prefijo'],
            (int) $datos['cantidad'],
            [
                'desnivel' => $this->cadenaONull($terreno['desnivel'] ?? null),
                'limpieza' => ! empty($terreno['limpio']) ? 'limpio' : $this->cadenaONull($terreno['grado_obstaculos'] ?? null),
                'restricciones' => $this->cadenaONull($terreno['restricciones'] ?? null),
            ],
        );

        return redirect()
            ->route('panel.lotes.index', ['propiedad_id' => $propiedad->id])
            ->with('estado', __('comercial.propiedades.lotes_generados', ['cantidad' => count($lotes)]));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
