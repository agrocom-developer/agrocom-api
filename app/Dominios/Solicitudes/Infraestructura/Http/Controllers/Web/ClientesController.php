<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\CuentaYaRegistrada;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Solicitudes\Aplicacion\CambiarEstadoDeCliente;
use App\Dominios\Solicitudes\Aplicacion\RegistrarCliente;
use App\Dominios\Solicitudes\Dominio\Excepciones\ClienteDuplicado;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\TipoCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\ClienteHistorialEstado;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\RegistrarClienteDesdePanelRequest;
use App\Dominios\Solicitudes\Infraestructura\Http\Requests\SuspenderClienteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Los clientes del marketplace en el panel: `GET /panel/clientes` (cola y búsqueda),
 * `GET /panel/clientes/{cliente}` (ficha con su reputación y sus solicitudes) y la
 * suspensión o reactivación con motivo. El cliente se registra solo desde la app o,
 * para quien llega a la oficina, lo carga a mano el panel con el mismo caso de uso
 * ({@see RegistrarCliente}); el panel no lo edita.
 *
 * Gateada por `solicitudes.cliente.ver`; suspender y reactivar exigen además
 * `solicitudes.cliente.suspender` y el alta a mano `solicitudes.cliente.crear`,
 * verificados DENTRO del controlador contra el ROL ACTIVO (invariante 10).
 */
final class ClientesController
{
    private const PERMISO_VER = 'solicitudes.cliente.ver';

    private const PERMISO_SUSPENDER = 'solicitudes.cliente.suspender';

    private const PERMISO_APROBAR = 'solicitudes.cliente.aprobar';

    private const PERMISO_CREAR = RegistrarClienteDesdePanelRequest::PERMISO;

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoAprobacion::tryFrom(TextoDeFiltro::de($request, 'estado'));

        $clientes = Cliente::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['razon_social', 'documento_fiscal', 'telefono'], $busqueda))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            // La cola primero: lo que espera una decisión, luego lo suspendido.
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'suspendido' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('solicitudes::pages.clientes.index', [
            ...$this->autorizacion->cascara($request),
            'clientes' => $clientes,
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoAprobacion::cases(),
            'puedeAprobar' => $this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR),
            'puedeSuspender' => $this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
        ]);
    }

    /** `GET /panel/clientes/crear`: el formulario de alta a mano. */
    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('solicitudes::pages.clientes.create', [
            ...$this->autorizacion->cascara($request),
            'tipos' => TipoCliente::cases(),
            'filasDeContacto' => RegistrarClienteDesdePanelRequest::FILAS_DE_CONTACTO,
        ]);
    }

    /**
     * `POST /panel/clientes`: registra al cliente con el mismo caso de uso que el alta
     * pública. El autor queda en `created_by` y en la bitácora; el cliente nace activo,
     * como el que se registra solo (no hay aprobación de clientes).
     */
    public function store(RegistrarClienteDesdePanelRequest $request, RegistrarCliente $registrar): RedirectResponse
    {
        $datos = $request->validated();
        $datos['contactos'] = array_values($datos['contactos'] ?? []);
        $datos['registrado_por'] = (int) Auth::guard('interno')->id();

        try {
            $cliente = $registrar->ejecutar($datos);
        } catch (CuentaYaRegistrada|CuentaDuplicada|ClienteDuplicado|SolicitudInvalida $excepcion) {
            return redirect()->back()->withInput($request->except('password'))->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return $this->volver($request, $cliente)->with('estado', __('solicitudes.clientes.registrado'));
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

        $cascara = $this->autorizacion->cascara($request);

        return view('solicitudes::pages.clientes.show', [
            ...$cascara,
            'cliente' => $cliente,
            'cuenta' => $cuentas->porIds([$cliente->usuario_id])[$cliente->usuario_id] ?? null,
            'solicitudes' => $solicitudes,
            'totalSolicitudes' => SolicitudServicio::query()->where('cliente_id', $cliente->id)->count(),
            'totalTerrenos' => Terreno::query()->where('cliente_id', $cliente->id)->count(),
            'contactos' => $cliente->contactos()->get(),
            'lineaDeTiempo' => $this->lineaDeTiempo($cliente, $cuentas, $cascara['zonaHoraria'] ?? null),
            'puedeSuspender' => $this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER),
            'puedeAprobar' => $this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR),
            'puedeVerSolicitudes' => $this->autorizacion->tienePermiso($request, 'solicitudes.solicitud.ver'),
        ]);
    }

    public function suspender(SuspenderClienteRequest $request, Cliente $cliente, CambiarEstadoDeCliente $cambiar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER), 403);

        try {
            $cambiar->suspender($cliente->id, (string) $request->validated('motivo'), (int) Auth::guard('interno')->id());
        } catch (TransicionDeAprobacionInvalida|MotivoRequerido $excepcion) {
            return $this->volver($request, $cliente)->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return $this->volver($request, $cliente)->with('estado', __('solicitudes.clientes.suspendido'));
    }

    public function reactivar(Request $request, Cliente $cliente, CambiarEstadoDeCliente $cambiar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_SUSPENDER), 403);

        try {
            $cambiar->reactivar($cliente->id, (int) Auth::guard('interno')->id());
        } catch (TransicionDeAprobacionInvalida $excepcion) {
            return $this->volver($request, $cliente)->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return $this->volver($request, $cliente)->with('estado', __('solicitudes.clientes.reactivado'));
    }

    /** `POST /panel/clientes/{cliente}/aprobar`: el cliente pendiente pasa a aprobado y puede publicar y negociar. */
    public function aprobar(Request $request, Cliente $cliente, CambiarEstadoDeCliente $cambiar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR), 403);

        try {
            $cambiar->ejecutar($cliente->id, AccionAprobacion::Aprobar, null, (int) Auth::guard('interno')->id());
        } catch (TransicionDeAprobacionInvalida $excepcion) {
            return $this->volver($request, $cliente)->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return $this->volver($request, $cliente)->with('estado', __('solicitudes.clientes.aprobado'));
    }

    /** `POST /panel/clientes/{cliente}/rechazar`: con motivo; el cliente lo ve en su app y puede corregir y reenviar. */
    public function rechazar(SuspenderClienteRequest $request, Cliente $cliente, CambiarEstadoDeCliente $cambiar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR), 403);

        try {
            $cambiar->ejecutar($cliente->id, AccionAprobacion::Rechazar, (string) $request->validated('motivo'), (int) Auth::guard('interno')->id());
        } catch (TransicionDeAprobacionInvalida|MotivoRequerido $excepcion) {
            return $this->volver($request, $cliente)->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return $this->volver($request, $cliente)->with('estado', __('solicitudes.clientes.rechazado'));
    }

    /** Desde una fila del listado se vuelve al listado, con sus filtros; desde la ficha, a la ficha. */
    private function volver(Request $request, Cliente $cliente): RedirectResponse
    {
        return $request->input('desde') === 'listado'
            ? redirect()->to(url()->previous(route('panel.clientes.index')))
            : redirect()->route('panel.clientes.show', $cliente);
    }

    /**
     * Cambios de estado del cliente (alta, aprobación, suspensión…), del más nuevo al más antiguo,
     * con quién los hizo y el motivo si lo hubo: solo eventos reales de `sol_cliente_historial_estados`.
     *
     * @return list<array{title: string, meta: string, tone: string}>
     */
    private function lineaDeTiempo(Cliente $cliente, LecturaCuentas $cuentas, ?string $zonaHoraria): array
    {
        $filas = ClienteHistorialEstado::query()->where('cliente_id', $cliente->id)->orderByDesc('id')->get();
        $actores = $cuentas->porIds($filas->pluck('actor_usuario_id')->filter()->unique()->values()->all());

        return $filas->map(function (ClienteHistorialEstado $fila) use ($actores, $zonaHoraria): array {
            $actor = $fila->actor_usuario_id === null ? null : ($actores[$fila->actor_usuario_id]->nombre ?? "#{$fila->actor_usuario_id}");
            $partes = [
                Carbon::instance($fila->created_at)->timezone($zonaHoraria ?: config('app.timezone'))->format('d/m/Y H:i'),
                $fila->origen->value === 'sistema' ? __('solicitudes.clientes.historial_sistema') : ($actor ?? __('solicitudes.clientes.historial_sin_autor')),
            ];

            if ($fila->motivo !== null && $fila->motivo !== '') {
                $partes[] = $fila->motivo;
            }

            return [
                'title' => $fila->estado_anterior === null
                    ? __('solicitudes.clientes.historial_alta')
                    : __('solicitudes.clientes.historial_cambio', [
                        'de' => __('aprobacion.estado.'.$fila->estado_anterior->value),
                        'a' => __('aprobacion.estado.'.$fila->estado_nuevo->value),
                    ]),
                'meta' => implode(' · ', $partes),
                'tone' => $fila->estado_nuevo->tono(),
            ];
        })->values()->all();
    }
}
