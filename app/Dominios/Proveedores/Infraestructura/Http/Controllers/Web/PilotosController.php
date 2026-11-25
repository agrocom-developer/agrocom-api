<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Proveedores\Aplicacion\EstadoDocumentalDeSujeto;
use App\Dominios\Proveedores\Aplicacion\RegistrarPiloto;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\HistorialEstado;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\RegistrarPilotoDesdePanelRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\CuentaDuplicada;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * `GET /panel/pilotos` y `GET /panel/pilotos/{piloto}`: pilotos de todos los
 * proveedores y su ficha de revisión (ADR 0032), y el alta a mano de un piloto de un
 * proveedor tercero (`GET /panel/pilotos/crear`, `POST /panel/pilotos`), con el mismo caso
 * de uso que la app ({@see RegistrarPiloto}). Gateada por `proveedores.piloto.ver` y, el
 * alta, por `proveedores.piloto.crear`, contra el ROL ACTIVO.
 */
final class PilotosController
{
    use ArmaFichasDeAprobacion;

    private const PERMISO_VER = 'proveedores.piloto.ver';

    private const PERMISO_CREAR = RegistrarPilotoDesdePanelRequest::PERMISO;

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, LecturaCuentas $cuentas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoAprobacion::tryFrom(TextoDeFiltro::de($request, 'estado'));

        $pilotos = Piloto::query()
            ->when($busqueda !== '', fn ($consulta) => $consulta->where(function ($interna) use ($busqueda): void {
                $interna->where('licencia_numero', 'like', '%'.$busqueda.'%');
                // El nombre vive en la cuenta (otro módulo): se resuelve por contrato, no con un join.
                $interna->orWhereIn('usuario_id', $this->idsDeCuentaPorNombre($busqueda));
            }))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobado' THEN 1 WHEN 'suspendido' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('proveedores::pages.pilotos.index', [
            ...$this->autorizacion->cascara($request),
            'pilotos' => $pilotos,
            'nombres' => $cuentas->porIds($pilotos->getCollection()->pluck('usuario_id')->all()),
            'proveedores' => Proveedor::query()->whereIn('id', $pilotos->getCollection()->pluck('proveedor_id')->unique())->pluck('razon_social', 'id'),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoAprobacion::cases(),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
        ]);
    }

    /** `GET /panel/pilotos/crear`: el formulario de alta a mano; `?proveedor=` preselecciona al proveedor. */
    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('proveedores::pages.pilotos.create', [
            ...$this->autorizacion->cascara($request),
            'proveedores' => Proveedor::query()
                ->where('tipo', TipoProveedor::Tercero->value)
                ->whereIn('estado', [EstadoAprobacion::Pendiente->value, EstadoAprobacion::Aprobado->value])
                ->orderBy('razon_social')
                ->pluck('razon_social', 'id')
                ->all(),
            'proveedorElegido' => $request->integer('proveedor') ?: null,
        ]);
    }

    /** `POST /panel/pilotos`: registra al piloto `pendiente`; quien lo carga no podrá aprobarlo. */
    public function store(RegistrarPilotoDesdePanelRequest $request, RegistrarPiloto $registrar): RedirectResponse
    {
        $datos = $request->validated();
        $proveedorId = (int) $datos['proveedor_id'];
        unset($datos['proveedor_id']);

        try {
            $piloto = $registrar->ejecutarParaProveedor($proveedorId, (int) Auth::guard('interno')->id(), $datos);
        } catch (CuentaDuplicada|RegistroInvalido $excepcion) {
            return redirect()->back()->withInput($request->except('password'))->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.pilotos.show', $piloto)->with('estado', __('proveedores.alta_manual.piloto_registrado'));
    }

    public function show(Request $request, Piloto $piloto, LecturaCuentas $cuentas, EstadoDocumentalDeSujeto $estadoDocumental): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $cascara = $this->autorizacion->cascara($request);

        $historial = HistorialEstado::query()
            ->where('sujeto_tipo', TipoSujeto::Piloto->value)
            ->where('sujeto_id', $piloto->id)
            ->orderByDesc('id')
            ->get();

        return view('proveedores::pages.pilotos.show', [
            ...$cascara,
            'piloto' => $piloto->load('proveedor'),
            'cuenta' => $cuentas->porIds([$piloto->usuario_id])[$piloto->usuario_id] ?? null,
            'documentos' => $this->documentosDe(TipoSujeto::Piloto, $piloto->id),
            'documentosFaltantes' => $estadoDocumental->faltantesDe(TipoSujeto::Piloto, $piloto->id),
            'acciones' => $this->accionesOfrecidas($request, $this->autorizacion, 'proveedores.piloto', $piloto->estado),
            'puedeRevisarDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.revisar'),
            'puedeVerDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.ver'),
            'puedeVerProveedor' => $this->autorizacion->tienePermiso($request, 'proveedores.proveedor.ver'),
            'lineaDeTiempo' => $this->lineaDeTiempo($historial, $cuentas, $cascara['zonaHoraria'] ?? null),
        ]);
    }

    /**
     * Ids de cuenta cuyo nombre contiene el texto. Se consulta `sec_user` por
     * contrato de lectura de cuentas no existe una búsqueda por nombre: se acota
     * a las cuentas que son pilotos (pocas) y se filtra en memoria.
     *
     * @return list<int>
     */
    private function idsDeCuentaPorNombre(string $busqueda): array
    {
        $cuentas = app(LecturaCuentas::class);
        $candidatas = Piloto::query()->pluck('usuario_id')->all();
        $patron = mb_strtolower($busqueda);

        return array_values(array_map(
            static fn ($cuenta): int => $cuenta->id,
            array_filter($cuentas->porIds($candidatas), static fn ($cuenta): bool => str_contains(mb_strtolower($cuenta->nombre), $patron)),
        ));
    }
}
