<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Proveedores\Aplicacion\EstadoDocumentalDeSujeto;
use App\Dominios\Proveedores\Aplicacion\RegistrarProveedor;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroDuplicado;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\HistorialEstado;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\RegistrarProveedorDesdePanelRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\CuentaYaRegistrada;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * `GET /panel/proveedores` y `GET /panel/proveedores/{proveedor}`: la cola de
 * proveedores y su ficha de revisión (ADR 0032). Cuatro estados de aprobación
 * y tres tipos; la ficha reúne datos, documentos, personal, flota e historial,
 * y ofrece las acciones de aprobación que el rol activo puede hacer.
 *
 * También el alta a mano para quien llega a la oficina (`GET /panel/proveedores/crear`,
 * `POST /panel/proveedores`): reutiliza {@see RegistrarProveedor}, deja al proveedor
 * `pendiente` y a quien lo registró como autor, que no podrá aprobarlo.
 *
 * Gateada por `proveedores.proveedor.ver` y, el alta, por `proveedores.proveedor.crear`,
 * verificados DENTRO del controlador contra el ROL ACTIVO (invariante 10).
 */
final class ProveedoresController
{
    use ArmaFichasDeAprobacion;

    private const PERMISO_VER = 'proveedores.proveedor.ver';

    private const PERMISO_CREAR = RegistrarProveedorDesdePanelRequest::PERMISO;

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, LecturaCuentas $cuentas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoAprobacion::tryFrom(TextoDeFiltro::de($request, 'estado'));
        $tipo = TipoProveedor::tryFrom(TextoDeFiltro::de($request, 'tipo'));

        $proveedores = Proveedor::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['razon_social', 'documento_fiscal'], $busqueda))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->when($tipo !== null, fn ($consulta) => $consulta->where('tipo', $tipo->value))
            // La cola de revisión primero: los pendientes más antiguos arriba.
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobado' THEN 1 WHEN 'suspendido' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('proveedores::pages.proveedores.index', [
            ...$this->autorizacion->cascara($request),
            'proveedores' => $proveedores,
            'titulares' => $cuentas->porIds($proveedores->getCollection()->pluck('usuario_titular_id')->filter()->all()),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'tipo' => $tipo?->value],
            'estadosFiltro' => EstadoAprobacion::cases(),
            'tiposFiltro' => TipoProveedor::cases(),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
        ]);
    }

    /** `GET /panel/proveedores/crear`: el formulario de alta a mano. */
    public function create(Request $request, LecturaZonas $zonas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('proveedores::pages.proveedores.create', [
            ...$this->autorizacion->cascara($request),
            'tipos' => TipoProveedor::registrables(),
            'zonas' => $zonas->todas(),
        ]);
    }

    /**
     * `POST /panel/proveedores`: registra al proveedor (y, si es independiente, a su piloto)
     * con el mismo caso de uso que el registro de la app. Queda `pendiente`: otra persona de
     * la oficina lo aprueba, con sus documentos.
     */
    public function store(RegistrarProveedorDesdePanelRequest $request, RegistrarProveedor $registrar): RedirectResponse
    {
        $datos = $request->validated();
        $datos['registrado_por'] = (int) Auth::guard('interno')->id();

        try {
            $proveedor = $registrar->ejecutar($datos);
        } catch (CuentaYaRegistrada|CuentaDuplicada|RegistroDuplicado $excepcion) {
            return redirect()->back()->withInput($request->except('password'))->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.proveedores.show', $proveedor)->with('estado', __('proveedores.alta_manual.registrado'));
    }

    public function show(
        Request $request,
        Proveedor $proveedor,
        LecturaCuentas $cuentas,
        LecturaZonas $zonas,
        LecturaEquipos $equipos,
        EstadoDocumentalDeSujeto $estadoDocumental,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $cascara = $this->autorizacion->cascara($request);

        $titular = $proveedor->usuario_titular_id === null ? null : ($cuentas->porIds([$proveedor->usuario_titular_id])[$proveedor->usuario_titular_id] ?? null);

        $pilotos = Piloto::query()->where('proveedor_id', $proveedor->id)->orderBy('id')->get();
        $nombres = $cuentas->porIds($pilotos->pluck('usuario_id')->all());

        $historial = HistorialEstado::query()
            ->where('sujeto_tipo', TipoSujeto::Proveedor->value)
            ->where('sujeto_id', $proveedor->id)
            ->orderByDesc('id')
            ->get();

        return view('proveedores::pages.proveedores.show', [
            ...$cascara,
            'proveedor' => $proveedor,
            'titular' => $titular,
            'zona' => $proveedor->zona_base_id === null ? null : $zonas->nombre($proveedor->zona_base_id),
            'pilotos' => $pilotos,
            'nombres' => $nombres,
            'documentos' => $this->documentosDe(TipoSujeto::Proveedor, $proveedor->id),
            'documentosFaltantes' => $estadoDocumental->faltantesDe(TipoSujeto::Proveedor, $proveedor->id),
            'aptoParaOfertas' => $equipos->tieneEquipoApto($proveedor->id),
            'acciones' => $this->accionesOfrecidas($request, $this->autorizacion, 'proveedores.proveedor', $proveedor->estado),
            'puedeRevisarDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.revisar'),
            'puedeVerDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.ver'),
            'puedeRegistrarPiloto' => $proveedor->tipo === TipoProveedor::Tercero && $this->autorizacion->tienePermiso($request, 'proveedores.piloto.crear'),
            'puedeRegistrarDron' => $this->autorizacion->tienePermiso($request, 'flota.dron.crear'),
            // Si lo cargó la oficina (no su propia cuenta), quién fue: esa persona no lo puede aprobar.
            'registradoPor' => $proveedor->created_by !== null && $proveedor->created_by !== $proveedor->usuario_titular_id
                ? ($cuentas->porIds([$proveedor->created_by])[$proveedor->created_by]->nombre ?? null)
                : null,
            'puedeVerPilotos' => $this->autorizacion->tienePermiso($request, 'proveedores.piloto.ver'),
            'lineaDeTiempo' => $this->lineaDeTiempo($historial, $cuentas, $cascara['zonaHoraria'] ?? null),
        ]);
    }
}
