<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Solicitudes\Aplicacion\CambiarEstadoDeCliente;
use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\SuspenderClienteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Los clientes del marketplace en el panel: `GET /panel/clientes` (cola y búsqueda),
 * `GET /panel/clientes/{cliente}` (ficha con su reputación y sus solicitudes) y la
 * suspensión o reactivación con motivo. El cliente se registra solo desde la app;
 * el panel no lo crea ni lo edita.
 *
 * Gateada por `solicitudes.cliente.ver`; suspender y reactivar exigen además
 * `solicitudes.cliente.suspender`, verificados DENTRO del controlador contra el
 * ROL ACTIVO (invariante 10).
 */
final class ClientesController
{
    private const PERMISO_VER = 'solicitudes.cliente.ver';

    private const PERMISO_SUSPENDER = 'solicitudes.cliente.suspender';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoCliente::tryFrom(TextoDeFiltro::de($request, 'estado'));

        $clientes = Cliente::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['razon_social', 'documento_fiscal', 'telefono'], $busqueda))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->orderByRaw("CASE estado WHEN 'suspendido' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('solicitudes::pages.clientes.index', [
            ...$this->autorizacion->cascara($request),
            'clientes' => $clientes,
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoCliente::cases(),
        ]);
    }

    public function show(Request $request, Cliente $cliente, LecturaCuentas $cuentas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $solicitudes = SolicitudServicio::query()
            ->where('cliente_id', $cliente->id)
            ->with('terreno:id,nombre')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('solicitudes::pages.clientes.show', [
            ...$this->autorizacion->cascara($request),
            'cliente' => $cliente,
            'cuenta' => $cuentas->porIds([$cliente->usuario_id])[$cliente->usuario_id] ?? null,
            'solicitudes' => $solicitudes,
            'totalSolicitudes' => SolicitudServicio::query()->where('cliente_id', $cliente->id)->count(),
            'totalTerrenos' => Terreno::query()->where('cliente_id', $cliente->id)->count(),
            'puedeSuspender' => $this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER),
            'puedeVerSolicitudes' => $this->autorizacion->tienePermiso($request, 'solicitudes.solicitud.ver'),
        ]);
    }

    public function suspender(SuspenderClienteRequest $request, Cliente $cliente, CambiarEstadoDeCliente $cambiar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER), 403);

        try {
            $cambiar->suspender($cliente->id, (string) $request->validated('motivo'));
        } catch (SolicitudInvalida $excepcion) {
            return redirect()->route('panel.clientes.show', $cliente)->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.clientes.show', $cliente)->with('estado', __('solicitudes.clientes.suspendido'));
    }

    public function reactivar(Request $request, Cliente $cliente, CambiarEstadoDeCliente $cambiar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER), 403);

        try {
            $cambiar->reactivar($cliente->id);
        } catch (SolicitudInvalida $excepcion) {
            return redirect()->route('panel.clientes.show', $cliente)->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.clientes.show', $cliente)->with('estado', __('solicitudes.clientes.reactivado'));
    }
}
