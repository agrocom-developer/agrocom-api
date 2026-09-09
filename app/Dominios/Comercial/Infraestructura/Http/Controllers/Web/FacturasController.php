<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\EmitirFactura;
use App\Dominios\Comercial\Aplicacion\ListarActasFacturables;
use App\Dominios\Comercial\Aplicacion\ListarFacturas;
use App\Dominios\Comercial\Dominio\Excepciones\ActaNoFacturable;
use App\Dominios\Comercial\Infraestructura\Http\Requests\EmitirFacturaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST /panel/facturas*` (HU-31, tarea 45): "como encargado, quiero
 * emitir la factura de un trabajo desde su acta conformada, para cobrar
 * sobre hectáreas ya firmadas". Mismo molde que `AnticiposController` — ABM
 * acotado sin edición ni baja: una factura emitida es un snapshot
 * inmutable.
 *
 * Dos permisos de grano fino (`comercial.factura.ver`/`.crear`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}.
 * Ninguna regla de negocio acá: `Aplicacion/EmitirFactura` hace el trabajo,
 * incluido el cálculo de `monto` y la resolución del acta vía el contrato de
 * lectura de `Operaciones` (nunca un modelo `Acta` importado acá).
 */
final class FacturasController
{
    private const PERMISO_VER = 'comercial.factura.ver';

    private const PERMISO_CREAR = 'comercial.factura.crear';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarFacturas $listarFacturas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        return view('comercial::pages.facturas.index', [
            ...$this->autorizacion->cascara($request),
            'facturas' => $listarFacturas->ejecutar(),
        ]);
    }

    public function create(Request $request, ListarActasFacturables $listarActas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.facturas.create', [
            ...$this->autorizacion->cascara($request),
            'actasDisponibles' => $listarActas->ejecutar(),
        ]);
    }

    public function store(EmitirFacturaRequest $request, EmitirFactura $emitirFactura): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $emitirFactura->ejecutar((int) $datos['acta_id']);
        } catch (ActaNoFacturable $excepcion) {
            return redirect()
                ->route('panel.facturas.create')
                ->withInput()
                ->withErrors(['acta_id' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.facturas.index')
            ->with('estado', __('comercial.facturas.creada'));
    }
}
