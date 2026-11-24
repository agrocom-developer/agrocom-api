<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Solicitudes\Aplicacion\CambiarEstadoDeCliente;
use App\Dominios\Solicitudes\Dominio\EstadoCliente;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\TipoCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\CambiarEstadoDeClienteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Clientes del marketplace en el panel: `GET /panel/clientes` (listado con
 * filtros), `GET /panel/clientes/{cliente}` (ficha con sus terrenos y
 * solicitudes) y `POST /panel/clientes/{cliente}/estado` (suspender con motivo o
 * reactivar). Los clientes se registran solos desde la app; el panel no los crea
 * ni los edita.
 *
 * Gateada por `solicitudes.cliente.ver`; suspender y reactivar, por
 * `solicitudes.cliente.suspender`, ambos contra el ROL ACTIVO (invariante 10).
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
        $tipo = TipoCliente::tryFrom(TextoDeFiltro::de($request, 'tipo'));

        $clientes = Cliente::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['razon_social', 'documento_fiscal', 'telefono'], $busqueda))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->when($tipo !== null, fn ($consulta) => $consulta->where('tipo', $tipo->value))
            ->orderByRaw("CASE estado WHEN 'suspendido' THEN 0 ELSE 1 END")
            ->orderBy('razon_social')
            ->paginate(15)
            ->withQueryString();

        $ids = $clientes->getCollection()->pluck('id')->all();
        $solicitudes = SolicitudServicio::query()->whereIn('cliente_id', $ids)->selectRaw('cliente_id, COUNT(*) as cantidad')->groupBy('cliente_id')->pluck('cantidad', 'cliente_id');

        return view('solicitudes::pages.clientes.index', [
            ...$this->autorizacion->cascara($request),
            'clientes' => $clientes,
            'solicitudesPorCliente' => $solicitudes->map(fn ($n): int => (int) $n)->all(),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'tipo' => $tipo?->value],
            'estadosFiltro' => EstadoCliente::cases(),
            'tiposFiltro' => TipoCliente::cases(),
        ]);
    }

    public function show(Request $request, Cliente $cliente): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $solicitudes = SolicitudServicio::query()
            ->where('cliente_id', $cliente->id)
            ->with('terreno:id,nombre')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $enCurso = EstadoSolicitud::cases();
        $enCurso = array_values(array_filter($enCurso, static fn (EstadoSolicitud $e): bool => ! $e->esTerminal() && $e !== EstadoSolicitud::Borrador));

        return view('solicitudes::pages.clientes.show', [
            ...$this->autorizacion->cascara($request),
            'cliente' => $cliente,
            'terrenos' => Terreno::query()->where('cliente_id', $cliente->id)->with('zona:id,nombre')->orderBy('nombre')->get(),
            'solicitudes' => $solicitudes,
            'totalSolicitudes' => SolicitudServicio::query()->where('cliente_id', $cliente->id)->count(),
            'solicitudesEnCurso' => SolicitudServicio::query()->where('cliente_id', $cliente->id)->whereIn('estado', array_map(static fn (EstadoSolicitud $e): string => $e->value, $enCurso))->count(),
            'puedeSuspender' => $this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER),
            'puedeVerSolicitud' => $this->autorizacion->tienePermiso($request, 'solicitudes.solicitud.ver'),
        ]);
    }

    public function cambiarEstado(CambiarEstadoDeClienteRequest $request, Cliente $cliente, CambiarEstadoDeCliente $cambiar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER), 403);

        $volver = redirect()->route('panel.clientes.show', $cliente);

        try {
            if ($request->validated('accion') === 'suspender') {
                $cambiar->suspender($cliente->id, (string) $request->validated('motivo'));

                return $volver->with('estado', __('solicitudes.clientes.suspendido'));
            }

            $cambiar->reactivar($cliente->id);
        } catch (SolicitudInvalida $excepcion) {
            return $volver->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return $volver->with('estado', __('solicitudes.clientes.reactivado'));
    }
}
