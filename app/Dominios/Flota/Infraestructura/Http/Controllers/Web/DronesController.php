<?php

namespace App\Dominios\Flota\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\DocumentosIncompletos;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Compartido\Dominio\TransicionesAprobacion;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Flota\Aplicacion\GestionarFotosDeDron;
use App\Dominios\Flota\Aplicacion\ResolverAprobacionDron;
use App\Dominios\Flota\Dominio\Excepciones\FotosDeDronIncompletas;
use App\Dominios\Flota\Dominio\Excepciones\OperacionDeFlotaInvalida;
use App\Dominios\Flota\Dominio\OrigenDron;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Flota\Infraestructura\Eloquent\HistorialEstadoDron;
use App\Dominios\Flota\Infraestructura\Http\Requests\ResolverAprobacionDronRequest;
use App\Dominios\Flota\Infraestructura\Http\Requests\RevisarFotoDeDronRequest;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * `GET /panel/drones-flota` y su ficha: los drones del marketplace de todos los
 * proveedores, con su dueño, su estado de aprobación y sus ciclos de batería
 * (ADR 0029).
 *
 * Gateada por `flota.dron.ver`; las acciones, por `.aprobar`, `.suspender` y
 * `.dar_baja`, todas contra el ROL ACTIVO. Las fotos que el proveedor sube del
 * dron se ven desde el disco privado y se aprueban o rechazan una por una con
 * `flota.dron.aprobar`; sin las fotos obligatorias aprobadas el dron no se aprueba.
 */
final class DronesController
{
    private const PERMISO_VER = 'flota.dron.ver';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaDocumentos $documentos,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoAprobacion::tryFrom(TextoDeFiltro::de($request, 'estado'));
        $origen = OrigenDron::tryFrom(TextoDeFiltro::de($request, 'origen'));
        $fotosEnRevision = TextoDeFiltro::de($request, 'fotos') === 'en_revision';

        $drones = Dron::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['marca', 'modelo', 'numero_serie'], $busqueda))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->when($origen !== null, fn ($consulta) => $consulta->where('origen', $origen->value))
            ->when($fotosEnRevision, fn ($consulta) => $consulta->whereIn('id', DB::table('flo_dron_fotos')->whereNull('deleted_at')->where('estado', 'en_revision')->select('dron_id')))
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
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'origen' => $origen?->value, 'fotos' => $fotosEnRevision ? 'en_revision' : null],
            'estadosFiltro' => EstadoAprobacion::cases(),
            'origenesFiltro' => OrigenDron::cases(),
        ]);
    }

    public function show(Request $request, Dron $dron, LecturaCuentas $cuentas, VerificacionDocumental $documentos): View
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
            'fotos' => $this->fotosDe($dron->id),
            'fotosFaltantes' => $this->fotosFaltantes($dron->id),
            'puedeRevisarFotos' => $this->autorizacion->tienePermiso($request, 'flota.dron.aprobar'),
            'documentosFaltantes' => array_map(static fn (string $tipo): TipoDocumento => TipoDocumento::from($tipo), $documentos->faltantes('dron', $dron->id)),
            'equipos' => $equipos,
            'pilotos' => $pilotos,
            'acciones' => $acciones,
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

        $volver = redirect()->route('panel.drones-flota.show', $dron);

        try {
            $resolver->ejecutar($dron->id, $accion, (int) Auth::guard('interno')->id(), $request->validated('motivo'));
        } catch (TransicionDeAprobacionInvalida|MotivoRequerido|DocumentosIncompletos|FotosDeDronIncompletas $excepcion) {
            return $volver->withErrors(['aprobacion' => $excepcion->getMessage()]);
        }

        return $volver->with('estado', __('proveedores.aprobacion.resuelta.'.$accion->value));
    }

    /** La foto de un dron, desde el disco privado: nunca una URL pública. */
    public function foto(Request $request, Dron $dron, int $foto): BinaryFileResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $fila = DB::table('flo_dron_fotos')->where('dron_id', $dron->id)->whereNull('deleted_at')->where('id', $foto)->first();

        abort_if($fila === null || ! Storage::disk('local')->exists($fila->archivo), 404);

        return response()->file(Storage::disk('local')->path($fila->archivo), ['Cache-Control' => 'private, max-age=300']);
    }

    public function revisarFoto(RevisarFotoDeDronRequest $request, Dron $dron, int $foto, GestionarFotosDeDron $fotos): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, 'flota.dron.aprobar'), 403);
        abort_unless(DB::table('flo_dron_fotos')->where('dron_id', $dron->id)->whereNull('deleted_at')->where('id', $foto)->exists(), 404);

        $volver = redirect()->route('panel.drones-flota.show', $dron);
        $aprobar = $request->validated('decision') === 'aprobar';

        try {
            $fotos->revisar((int) Auth::guard('interno')->id(), $foto, $aprobar, $request->validated('motivo'));
        } catch (OperacionDeFlotaInvalida $excepcion) {
            return $volver->withErrors(['aprobacion' => $excepcion->getMessage()]);
        }

        return $volver->with('estado', __($aprobar ? 'flota.fotos.aprobada' : 'flota.fotos.rechazada'));
    }

    /**
     * Las fotos vivas del dron, la más reciente de cada tipo primero no: en orden de subida.
     *
     * @return list<array{id: int, tipo: string, estado: string, motivo: string|null}>
     */
    private function fotosDe(int $dronId): array
    {
        return DB::table('flo_dron_fotos')
            ->where('dron_id', $dronId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'tipo', 'estado', 'motivo'])
            ->map(static fn (object $f): array => ['id' => (int) $f->id, 'tipo' => (string) $f->tipo, 'estado' => (string) $f->estado, 'motivo' => $f->motivo])
            ->all();
    }

    /**
     * Los tipos obligatorios que todavía no tienen una foto aprobada.
     *
     * @return list<string>
     */
    private function fotosFaltantes(int $dronId): array
    {
        $aprobadas = array_column(array_filter($this->fotosDe($dronId), static fn (array $f): bool => $f['estado'] === 'aprobada'), 'tipo');

        return array_values(array_diff((array) config('flota.fotos_obligatorias', []), $aprobadas));
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
