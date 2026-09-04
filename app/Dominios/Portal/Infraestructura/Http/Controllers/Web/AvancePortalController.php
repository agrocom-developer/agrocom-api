<?php

namespace App\Dominios\Portal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;
use App\Dominios\Seguridad\Contratos\AutorizacionPortalCliente;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /portal/avance` (HU-41, tarea 55): hectáreas contratadas, aplicadas y
 * monto facturado del contrato del cliente autenticado — espec §13, "avance
 * comercial (hectáreas + monto facturado, sin desglose de costo)".
 *
 * `contratoId` se resuelve ACÁ, desde la sesión de portal vía
 * {@see AutorizacionPortalCliente} (invariante 5 de CLAUDE.md), nunca de un
 * parámetro de ruta: esta pantalla no tiene id en la URL a propósito, es
 * siempre "mi avance". Reusa `ObtenerAvanceComercial` tal cual (HU-32, tarea
 * 46) — sin tocar su lógica, solo pasándole el filtro que ya acepta.
 */
final class AvancePortalController
{
    public function __construct(private readonly AutorizacionPortalCliente $autorizacion) {}

    public function index(Request $request, ObtenerAvanceComercial $obtenerAvance): View
    {
        $contratoId = $this->autorizacion->contratoId($request);

        abort_if($contratoId === null, 404);

        $avance = $obtenerAvance->ejecutar(contratoId: $contratoId);

        return view('portal::pages.avance.index', [
            ...$this->autorizacion->cascara($request),
            'avance' => $avance[0] ?? null,
        ]);
    }
}
