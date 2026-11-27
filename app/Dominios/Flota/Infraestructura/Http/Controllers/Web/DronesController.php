<?php

namespace App\Dominios\Flota\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\AprobacionPropia;
use App\Dominios\Compartido\Dominio\Excepciones\DocumentosIncompletos;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Compartido\Dominio\TransicionesAprobacion;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Flota\Aplicacion\FotosObligatorias;
use App\Dominios\Flota\Aplicacion\GestionarFotosDeDron;
use App\Dominios\Flota\Aplicacion\RegistrarDron;
use App\Dominios\Flota\Aplicacion\ResolverAprobacionDron;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Dominio\OrigenDron;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Flota\Infraestructura\Eloquent\HistorialEstadoDron;
use App\Dominios\Flota\Infraestructura\Http\Requests\RegistrarDronDesdePanelRequest;
use App\Dominios\Flota\Infraestructura\Http\Requests\ResolverAprobacionDronRequest;
use App\Dominios\Proveedores\Contratos\DatosDocumento;
use App\Dominios\Proveedores\Contratos\LecturaDocumentos;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Contratos\VerificacionDocumental;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /panel/drones-flota` y su ficha: los drones del marketplace de todos los
 * proveedores, con su dueño, su estado de aprobación y sus ciclos de batería
 * (ADR 0029) y la revisión de sus fotos (ADR 0042).
 *
 * También el alta a mano del dron de un proveedor (`GET /panel/drones-flota/crear`,
 * `POST /panel/drones-flota`), con el mismo caso de uso que la app ({@see RegistrarDron}).
 *
 * Gateada por `flota.dron.ver`; las acciones, por `.crear`, `.aprobar`, `.suspender`,
 * `.dar_baja` y `.revisar_foto`, todas contra el ROL ACTIVO.
 */
final class DronesController
{
    private const PERMISO_VER = 'flota.dron.ver';

    private const PERMISO_CREAR = RegistrarDronDesdePanelRequest::PERMISO;

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaDocumentos $documentos,
        private readonly AlmacenDeMedios $almacen,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoAprobacion::tryFrom(TextoDeFiltro::de($request, 'estado'));
        $origen = OrigenDron::tryFrom(TextoDeFiltro::de($request, 'origen'));

        $drones = Dron::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['marca', 'modelo', 'numero_serie'], $busqueda))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->when($origen !== null, fn ($consulta) => $consulta->where('origen', $origen->value))
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobado' THEN 1 WHEN 'suspendido' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        $duenos = [];
        foreach ($drones->getCollection()->pluck('dueno_proveedor_id')->unique() as $id) {
            $duenos[$id] = $this->proveedores->proveedor($id)->razonSocial ?? "#{$id}";
        }

        return view('flota::pages.drones.index', [
            ...$this->autorizacion->cascara($request),
            'drones' => $drones,
            'duenos' => $duenos,
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'origen' => $origen?->value],
            'estadosFiltro' => EstadoAprobacion::cases(),
            'origenesFiltro' => OrigenDron::cases(),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'accionesPorEstado' => $this->accionesPorEstado($request),
        ]);
    }

    /**
     * Las acciones de aprobación que el rol activo ofrece en cada estado, para las filas del listado.
     *
     * @return array<string, list<AccionAprobacion>>
     */
    private function accionesPorEstado(Request $request): array
    {
        $porEstado = [];

        foreach (EstadoAprobacion::cases() as $estado) {
            $porEstado[$estado->value] = array_values(array_filter(
                TransicionesAprobacion::accionesDesde($estado),
                fn (AccionAprobacion $accion): bool => ! $accion->esDelSolicitante()
                    && $this->autorizacion->tienePermiso($request, 'flota.dron.'.self::permisoDeAccion($accion)),
            ));
        }

        return $porEstado;
    }

    /** `GET /panel/drones-flota/crear`: el formulario de alta a mano; `?proveedor=` preselecciona al proveedor. */
    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('flota::pages.drones.create', [
            ...$this->autorizacion->cascara($request),
            'proveedores' => $this->proveedores->admitenAltas(),
            'proveedorElegido' => $request->integer('proveedor') ?: null,
        ]);
    }

    /** `POST /panel/drones-flota`: registra el dron `pendiente`; quien lo carga no podrá aprobarlo. */
    public function store(RegistrarDronDesdePanelRequest $request, RegistrarDron $registrar): RedirectResponse
    {
        $datos = $request->validated();
        $proveedorId = (int) $datos['proveedor_id'];
        unset($datos['proveedor_id']);

        try {
            $dron = $registrar->ejecutarParaProveedor($proveedorId, (int) Auth::guard('interno')->id(), $datos);
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return redirect()->back()->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.drones-flota.show', $dron)->with('estado', __('flota.alta_manual.registrado'));
    }

    public function show(Request $request, Dron $dron, LecturaCuentas $cuentas, VerificacionDocumental $documentos, FotosObligatorias $obligatorias): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $cascara = $this->autorizacion->cascara($request);

        $acciones = array_values(array_filter(
            TransicionesAprobacion::accionesDesde($dron->estado),
            fn (AccionAprobacion $accion): bool => ! $accion->esDelSolicitante()
                && $this->autorizacion->tienePermiso($request, 'flota.dron.'.self::permisoDeAccion($accion)),
        ));

        $historial = HistorialEstadoDron::query()->where('dron_id', $dron->id)->orderByDesc('id')->get();
        $actores = $cuentas->porIds($historial->pluck('actor_usuario_id')->filter()->unique()->values()->all());

        $equipos = Equipo::query()->where('dron_id', $dron->id)->orderByDesc('id')->get();
        $pilotos = [];
        foreach ($equipos as $equipo) {
            $pilotos[$equipo->piloto_id] = $this->proveedores->piloto($equipo->piloto_id)->nombre ?? "#{$equipo->piloto_id}";
        }

        return view('flota::pages.drones.show', [
            ...$cascara,
            'dron' => $dron,
            'dueno' => $this->proveedores->proveedor($dron->dueno_proveedor_id),
            'operador' => $this->proveedores->proveedor($dron->operador_proveedor_id),
            'documentos' => $this->documentosDe($dron->id),
            'documentosFaltantes' => array_map(static fn (string $tipo): TipoDocumento => TipoDocumento::from($tipo), $documentos->faltantes('dron', $dron->id)),
            'equipos' => $equipos,
            'pilotos' => $pilotos,
            'acciones' => $acciones,
            'fotos' => DB::table('flo_dron_fotos')->where('dron_id', $dron->id)->whereNull('deleted_at')->orderBy('id')->get(['id', 'tipo', 'estado', 'motivo'])->all(),
            'fotosObligatorias' => $obligatorias->tipos(),
            'fotosFaltantes' => $obligatorias->faltantes($dron->id),
            'puedeRevisarFotos' => $this->autorizacion->tienePermiso($request, 'flota.dron.revisar_foto'),
            'puedeRevisarDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.revisar'),
            'puedeVerDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.ver'),
            'puedeVerProveedor' => $this->autorizacion->tienePermiso($request, 'proveedores.proveedor.ver'),
            'lineaDeTiempo' => $historial->map(fn (HistorialEstadoDron $fila): array => [
                'title' => __('proveedores.historial.cambio', [
                    'de' => __('aprobacion.estado.'.$fila->estado_anterior->value),
                    'a' => __('aprobacion.estado.'.$fila->estado_nuevo->value),
                ]),
                'meta' => implode(' · ', array_filter([
                    $this->fechaHora($fila->created_at, $cascara['zonaHoraria'] ?? null),
                    $fila->origen->value === 'sistema'
                        ? __('proveedores.historial.por_el_sistema')
                        : ($fila->actor_usuario_id === null ? __('proveedores.historial.sin_autor') : ($actores[$fila->actor_usuario_id]->nombre ?? "#{$fila->actor_usuario_id}")),
                    $fila->motivo,
                ])),
                'tone' => $fila->estado_nuevo->tono(),
            ])->all(),
        ]);
    }

    public function resolver(ResolverAprobacionDronRequest $request, Dron $dron, ResolverAprobacionDron $resolver): RedirectResponse
    {
        $accion = AccionAprobacion::from($request->validated('accion'));

        abort_unless($this->autorizacion->tienePermiso($request, 'flota.dron.'.self::permisoDeAccion($accion)), 403);

        // Desde una fila del listado se vuelve al listado, con sus filtros; desde la ficha, a la ficha.
        $volver = $request->input('desde') === 'listado'
            ? redirect()->to(url()->previous(route('panel.drones-flota.index')))
            : redirect()->route('panel.drones-flota.show', $dron);

        try {
            $resolver->ejecutar($dron->id, $accion, (int) Auth::guard('interno')->id(), $request->validated('motivo'));
        } catch (TransicionDeAprobacionInvalida|MotivoRequerido|DocumentosIncompletos|OperacionDeFlotaInvalida|AprobacionPropia $excepcion) {
            return $volver->withErrors(['aprobacion' => $excepcion->getMessage()]);
        }

        return $volver->with('estado', __('proveedores.aprobacion.resuelta.'.$accion->value));
    }

    /**
     * `GET /panel/drones-flota/fotos/{foto}/archivo`: la foto, desde el disco privado (nunca una URL pública).
     * `?variante=miniatura|media` entrega el derivado reducido; sin variante, el original.
     */
    public function foto(Request $request, int $foto): StreamedResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $fila = DB::table('flo_dron_fotos')->whereNull('deleted_at')->where('id', $foto)->first();

        $respuesta = $fila === null ? null : $this->almacen->respuesta($fila->archivo, VarianteDeMedio::desde($request->query('variante')));

        abort_if($respuesta === null, 404);

        return $respuesta;
    }

    /** `POST /panel/drones-flota/fotos/{foto}/revisar`: aprueba o rechaza una foto (el rechazo lleva motivo). */
    public function revisarFoto(Request $request, int $foto, GestionarFotosDeDron $fotos): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, 'flota.dron.revisar_foto'), 403);

        $datos = $request->validate([
            'decision' => ['required', 'in:aprobar,rechazar'],
            'motivo' => ['required_if:decision,rechazar', 'nullable', 'string', 'max:500'],
        ], [
            'motivo.required_if' => __('flota.fotos.motivo_requerido'),
        ]);

        $dronId = (int) DB::table('flo_dron_fotos')->whereNull('deleted_at')->where('id', $foto)->value('dron_id');
        abort_if($dronId === 0, 404);

        $volver = redirect()->route('panel.drones-flota.show', $dronId);
        $aprobar = $datos['decision'] === 'aprobar';

        try {
            $fotos->revisar((int) Auth::guard('interno')->id(), $foto, $aprobar, $datos['motivo'] ?? null);
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return $volver->withErrors(['foto' => $excepcion->getMessage()]);
        }

        return $volver->with('estado', __($aprobar ? 'flota.fotos.aprobada' : 'flota.fotos.rechazada'));
    }

    /** Aprobar, rechazar y rehabilitar comparten `aprobar`; suspender y dar de baja tienen el suyo. */
    private static function permisoDeAccion(AccionAprobacion $accion): string
    {
        return match ($accion) {
            AccionAprobacion::Suspender => 'suspender',
            AccionAprobacion::DarDeBaja => 'dar_baja',
            default => 'aprobar',
        };
    }

    /**
     * Documentos del dron: viven en `prv_documentos` (módulo Proveedores) y se
     * leen por el contrato {@see LecturaDocumentos}.
     *
     * @return list<DatosDocumento>
     */
    private function documentosDe(int $dronId): array
    {
        return $this->documentos->de('dron', $dronId);
    }

    private function fechaHora(?DateTimeInterface $momento, ?string $zonaHoraria): string
    {
        return $momento === null ? '—' : Carbon::instance($momento)->timezone($zonaHoraria ?: config('app.timezone'))->format('d/m/Y H:i');
    }
}
