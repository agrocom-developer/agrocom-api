<?php

namespace App\Dominios\Portal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Contratos\LecturaAvanceComercial;
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
 * siempre "mi avance". Consume {@see LecturaAvanceComercial} (Comercial,
 * `Contratos/`, tarea 68) en vez de `Aplicacion\ObtenerAvanceComercial`
 * directo — `Portal` no importa clases de `Aplicacion/` ajenas (ADR 0003,
 * regla 2); la fórmula del avance no se toca, solo el camino para llegar a
 * ella.
 */
final class AvancePortalController
{
    public function __construct(private readonly AutorizacionPortalCliente $autorizacion) {}

    public function index(Request $request, LecturaAvanceComercial $lecturaAvance): View
    {
        $contratoId = $this->autorizacion->contratoId($request);

        abort_if($contratoId === null, 404);

        return view('portal::pages.avance.index', [
            ...$this->autorizacion->cascara($request),
            'avance' => $lecturaAvance->porContrato($contratoId),
        ]);
    }
}
