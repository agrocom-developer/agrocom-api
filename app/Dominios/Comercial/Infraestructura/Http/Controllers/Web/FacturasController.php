<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\EmitirFactura;
use App\Dominios\Comercial\Aplicacion\ListarActasFacturables;
use App\Dominios\Comercial\Aplicacion\ListarFacturas;
use App\Dominios\Comercial\Dominio\Excepciones\ActaNoFacturable;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
use App\Dominios\Comercial\Infraestructura\Http\Requests\EmitirFacturaRequest;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
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

        $busqueda = TextoDeFiltro::de($request, 'q');
        $busqueda = $busqueda !== '' ? $busqueda : null;
        $clienteId = $request->integer('cliente_id') ?: null;
        $contratoId = $request->integer('contrato_id') ?: null;
        $desde = $this->fecha($request, 'desde');
        $hasta = $this->fecha($request, 'hasta');

        return view('comercial::pages.facturas.index', [
            ...$this->autorizacion->cascara($request),
            'facturas' => $listarFacturas->ejecutar($busqueda, $clienteId, $contratoId, $desde, $hasta),
            'resumen' => $listarFacturas->resumen($busqueda, $clienteId, $contratoId, $desde, $hasta),
            'filtros' => ['q' => $busqueda ?? '', 'cliente_id' => $clienteId, 'contrato_id' => $contratoId, 'desde' => $desde, 'hasta' => $hasta],
            'clientesDisponibles' => $this->clientesConFacturas(),
            'contratosDisponibles' => $this->contratosConFacturas(),
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

    /** Una fecha de filtro `Y-m-d` real (el 31/02 no lo es); cualquier otra cosa se ignora en vez de romper el listado. */
    private function fecha(Request $request, string $clave): ?string
    {
        $valor = TextoDeFiltro::de($request, $clave);
        $fecha = $valor !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;

        return $fecha !== false && $fecha->format('Y-m-d') === $valor ? $valor : null;
    }

    /**
     * Opciones del filtro «Cliente»: solo los que tienen alguna factura —uno
     * sin facturas devolvería siempre el listado vacío—.
     *
     * @return array<int, string> id => razón social
     */
    private function clientesConFacturas(): array
    {
        return Cliente::query()
            ->whereIn('id', Contrato::query()->whereIn('id', Factura::query()->select('contrato_id'))->select('cliente_id'))
            ->orderBy('razon_social')
            ->pluck('razon_social', 'id')
            ->all();
    }

    /**
     * Opciones del filtro «Contrato»: los que tienen alguna factura, rotulados
     * con su cliente (un contrato no tiene nombre propio).
     *
     * @return array<int, string> id => «Contrato #N — cliente»
     */
    private function contratosConFacturas(): array
    {
        return Contrato::query()
            ->with('cliente:id,razon_social')
            ->whereIn('id', Factura::query()->select('contrato_id'))
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Contrato $contrato): array => [
                $contrato->id => __('comercial.facturas.contrato_opcion', ['id' => $contrato->id, 'cliente' => $contrato->cliente->razon_social]),
            ])
            ->all();
    }
}
