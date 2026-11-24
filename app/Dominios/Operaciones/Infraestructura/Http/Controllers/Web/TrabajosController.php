<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Operaciones\Aplicacion\EliminarTrabajo;
use App\Dominios\Operaciones\Aplicacion\GenerarActaTrabajo;
use App\Dominios\Operaciones\Aplicacion\GenerarReporteTecnico;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoValidadoNoEliminable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Contratos\LecturaPanelPersonal;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Panel de trabajos (HU-05/HU-15; ADR 0037): el listado de los trabajos que los
 * equipos abrieron sobre solicitudes de servicio (`GET /panel/trabajos`) y el
 * detalle de UNO — sus sesiones, acta, reporte técnico y evidencias —, con
 * eliminar mientras no esté `validado`. Los trabajos nacen en la app de campo
 * por la sincronización: el panel no los crea ni los edita.
 *
 * Mismo patrón que `VersionesApkController` (HU-20): un único permiso
 * (`operaciones.trabajo.ver`) gatea toda la pantalla, verificado DENTRO del
 * controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} — nunca
 * `SecUser`/`CascaraPanel` directos (ADR 0003, regla 2).
 */
final class TrabajosController
{
    private const PERMISO = 'operaciones.trabajo.ver';

    /** HU-93 (tarea 108): eliminar un trabajo mientras no esté `validado` — ver `Aplicacion/EliminarTrabajo`. */
    private const PERMISO_ELIMINAR = 'operaciones.trabajo.eliminar';

    /** HU-18 (tarea 25): gatea solo el botón/ruta del reporte técnico, no toda la pantalla — ver runs/25.md. */
    private const PERMISO_REPORTE = 'operaciones.reporte.ver';

    /** Cada vínculo se gatea por el permiso del módulo de lo que MUESTRA, no por el de esta pantalla. */
    private const PERMISO_VER_SOLICITUD = 'solicitudes.solicitud.ver';

    private const PERMISO_VER_CUADRILLA = 'personal.equipo_trabajo.ver';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly GenerarActaTrabajo $generarActa,
        private readonly GenerarReporteTecnico $generarReporte,
        private readonly LecturaContextoSolicitudes $solicitudes,
    ) {}

    /**
     * `GET /panel/trabajos`: los trabajos, del más reciente al más viejo, con
     * su solicitud y su cliente. Filtra por estado de tablero (abierto, cerrado
     * o validado) y por texto sobre el número de solicitud.
     */
    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $estado = EstadoTableroTrabajo::tryFrom(TextoDeFiltro::de($request, 'estado'));
        $busqueda = TextoDeFiltro::de($request, 'q');

        $trabajos = Trabajo::query()
            ->with('sesiones')
            ->when($estado !== null, fn ($consulta) => $consulta->conEstadoTablero($estado))
            ->when($busqueda !== '' && ctype_digit(ltrim($busqueda, '#')), fn ($consulta) => $consulta->where('solicitud_id', (int) ltrim($busqueda, '#')))
            ->orderByDesc('inicio')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('operaciones::pages.trabajos.index', [
            ...$this->autorizacion->cascara($request),
            'trabajos' => $trabajos,
            'contexto' => $this->solicitudes->porIds($trabajos->getCollection()->pluck('solicitud_id')->filter()->map(fn ($id): int => (int) $id)->all()),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoTableroTrabajo::cases(),
        ]);
    }

    /**
     * Arquetipo Detalle (tarea 124, guía §6.4): misma anatomía que
     * `OrdenesController::show()`/`OrdenesTrabajoController::show()` —
     * `page-header` + KPI + `form-layout` de solo lectura + aside con
     * avance/vínculos/actividad. Editar/eliminar son las mismas dos acciones
     * que ya existían (mismo permiso y misma guarda de "no validado" que
     * `edit()`/`destroy()`), ninguna acción nueva.
     */
    public function show(Request $request, Trabajo $trabajo, LecturaPanelPersonal $lecturaPersonal): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $trabajo->load(['sesiones.rechazo', 'sesiones.dron', 'acta', 'reporteTecnico']);
        $sesionesVigentes = $trabajo->sesiones->whereNull('anulada_en');
        $editable = $trabajo->estadoTablero() !== EstadoTableroTrabajo::Validado;

        $hectareasDeclaradas = BigDecimal::of((string) $trabajo->hectareas_declaradas);
        $hectareasAplicadas = BigDecimal::of($this->hectareasAplicadas($trabajo));
        $porcentajeCobertura = $hectareasDeclaradas->isZero()
            ? 0
            : (int) round(((float) (string) $hectareasAplicadas / (float) (string) $hectareasDeclaradas) * 100);

        return view('operaciones::pages.trabajos.show', [
            ...$this->autorizacion->cascara($request),
            'trabajo' => $trabajo,
            'solicitud' => $trabajo->solicitud_id === null ? null : ($this->solicitudes->porIds([(int) $trabajo->solicitud_id])[(int) $trabajo->solicitud_id] ?? null),
            'cuadrillaLabel' => $trabajo->equipo_trabajo_id !== null
                ? ($this->etiquetasEquipo([$trabajo->equipo_trabajo_id])[$trabajo->equipo_trabajo_id] ?? "#{$trabajo->equipo_trabajo_id}")
                : null,
            'hectareasAplicadas' => (string) $hectareasAplicadas,
            'porcentajeCobertura' => min(100, $porcentajeCobertura),
            'sesionesValidadas' => $sesionesVigentes->where('estado', EstadoSesion::Validado)->count(),
            'sesionesTotal' => $sesionesVigentes->count(),
            'puedeEliminar' => $editable && $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
            'puedeVerReporte' => $this->autorizacion->tienePermiso($request, self::PERMISO_REPORTE),
            'vinculos' => $this->vinculosDeTrabajo($trabajo, $request),
            'actividad' => $this->actividadDeTrabajo($trabajo),
            // Tarea 131: nombre real del piloto por sesión, en lote — mismo
            // contrato cruzado de módulos que usa ahora ValidacionSesionesController.
            'etiquetasPiloto' => $lecturaPersonal->nombresDePersonas(
                $trabajo->sesiones->pluck('piloto_id')->unique()->values()->all()
            ),
        ]);
    }

    /**
     * Hectáreas voladas: suma de las sesiones VIGENTES (invariante 2 — las
     * anuladas no cuentan), recalculada en cada llamada desde la base
     * (invariante 6 — `DECIMAL`, nunca `float`; mismo criterio que
     * `Trabajo::cuadreCaldo()`). También la usa {@see self::tarjetaRegistroEnCampo()}
     * para el aside de `edit()` — no es una cuenta nueva, es la misma.
     */
    private function hectareasAplicadas(Trabajo $trabajo): string
    {
        return (string) BigDecimal::of((string) $trabajo->sesiones()->whereNull('anulada_en')->sum('hectareas_declaradas'))->toScale(2);
    }

    /**
     * "Vínculos" de `show()` (arquetipo Detalle): accesos a la solicitud de la que
     * nació, la cuadrilla asignada
     * y los dos PDF cuando existen — como filas sueltas en vez de tarjetas
     * completas. Cada uno gatea por el permiso del módulo de LO QUE
     * MUESTRA, por su propio permiso.
     *
     * @return list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>
     */
    private function vinculosDeTrabajo(Trabajo $trabajo, Request $request): array
    {
        $vinculos = [];

        if ($trabajo->solicitud_id !== null && $this->autorizacion->tienePermiso($request, self::PERMISO_VER_SOLICITUD)) {
            $vinculos[] = [
                'href' => route('panel.solicitudes.show', $trabajo->solicitud_id),
                'icon' => 'request_quote',
                'title' => __('operaciones.trabajos.vinculo_solicitud'),
                'meta' => __('operaciones.trabajos.vinculo_solicitud_meta', ['id' => $trabajo->solicitud_id]),
                'tone' => 'primary-2',
            ];
        }

        if ($trabajo->equipo_trabajo_id !== null && $this->autorizacion->tienePermiso($request, self::PERMISO_VER_CUADRILLA)) {
            $vinculos[] = [
                'href' => route('panel.cuadrillas.show', $trabajo->equipo_trabajo_id),
                'icon' => 'groups',
                'title' => __('operaciones.trabajos.vinculo_cuadrilla'),
                'meta' => null,
                'tone' => 'distintivo-1',
            ];
        }

        if ($trabajo->acta?->pdf_path !== null) {
            $vinculos[] = [
                'href' => route('panel.trabajos.acta-pdf', $trabajo),
                'icon' => 'picture_as_pdf',
                'title' => __('operaciones.trabajos.vinculo_acta_pdf'),
                'meta' => null,
                'tone' => 'success',
            ];
        }

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_REPORTE) && $trabajo->reporteTecnico?->pdf_path !== null) {
            $vinculos[] = [
                'href' => route('panel.trabajos.reporte-pdf', $trabajo),
                'icon' => 'summarize',
                'title' => __('operaciones.trabajos.vinculo_reporte_pdf'),
                'meta' => null,
                'tone' => 'alert',
            ];
        }

        return $vinculos;
    }

    /**
     * "Actividad" de `show()`: solo eventos reconstruibles desde columnas
     * reales (guía §6.4 regla 3) — nunca una bitácora antes/después que no
     * existe todavía (invariante 9 de CLAUDE.md, pendiente).
     *
     * @return list<array{title: string, meta: string, tone: string}>
     */
    private function actividadDeTrabajo(Trabajo $trabajo): array
    {
        $eventos = [[
            'title' => __('operaciones.trabajos.actividad_sincronizado'),
            'meta' => $this->metaFecha($trabajo->created_at),
            'tone' => 'neutral',
        ]];

        if ($trabajo->fin !== null) {
            $eventos[] = [
                'title' => __('operaciones.trabajos.actividad_cerrado'),
                'meta' => $this->metaFecha($trabajo->fin),
                'tone' => 'info',
            ];
        }

        foreach ($trabajo->sesiones as $sesion) {
            if ($sesion->anulada_en !== null && $sesion->rechazo !== null) {
                $eventos[] = [
                    'title' => __('operaciones.trabajos.actividad_sesion_rechazada', ['secuencia' => $sesion->secuencia, 'motivo' => $sesion->rechazo->motivo]),
                    'meta' => $this->metaFecha($sesion->rechazo->created_at),
                    'tone' => 'danger',
                ];
            } elseif ($sesion->fecha_validacion !== null) {
                $eventos[] = [
                    'title' => __('operaciones.trabajos.actividad_sesion_validada', ['secuencia' => $sesion->secuencia]),
                    'meta' => $this->metaFecha($sesion->fecha_validacion),
                    'tone' => 'success',
                ];
            }
        }

        if ($trabajo->acta?->estado === EstadoActa::Firmada) {
            $eventos[] = [
                'title' => __('operaciones.trabajos.actividad_acta_firmada', ['firmante' => $trabajo->acta->firmante ?? '—']),
                'meta' => $this->metaFecha($trabajo->acta->fecha_firma),
                'tone' => 'success',
            ];
        }

        return $eventos;
    }

    private function metaFecha(?\DateTimeInterface $fecha): string
    {
        return $fecha?->format('d/m/Y H:i') ?? '—';
    }

    /**
     * `GET /panel/trabajos/detalle/{trabajo}/acta/pdf` (HU-17, tarea 24):
     * solo lectura, mismo permiso que `show()` — generar/firmar el acta es
     * de `agrocom-field` (`ActaController`, API), no del panel. Si el
     * archivo físico se perdió, lo reconstruye antes de responder
     * (`GenerarActaTrabajo::asegurarPdf`, ADR 0026).
     */
    public function actaPdf(Request $request, Trabajo $trabajo): Response
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $acta = $trabajo->acta;

        abort_if($acta === null || $acta->pdf_path === null, 404);

        $this->generarActa->asegurarPdf($acta);

        return response(Storage::disk('r2')->get($acta->pdf_path), 200, ['Content-Type' => 'application/pdf']);
    }

    /**
     * `GET /panel/trabajos/detalle/{trabajo}/reporte/pdf` (HU-18, tarea 25):
     * solo lectura, permiso propio `operaciones.reporte.ver` — el reporte se
     * genera solo al firmar el acta (`GenerarReporteTecnico`, enganchado en
     * `FirmarActa`); esta ruta nunca crea la fila, pero reconstruye el
     * archivo físico si se perdió (`GenerarReporteTecnico::asegurarPdf`, ADR 0026).
     */
    public function reporteTecnicoPdf(Request $request, Trabajo $trabajo): Response
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_REPORTE), 403);

        $reporte = $trabajo->reporteTecnico;

        abort_if($reporte === null || $reporte->pdf_path === null, 404);

        $this->generarReporte->asegurarPdf($reporte);

        return response(Storage::disk('r2')->get($reporte->pdf_path), 200, ['Content-Type' => 'application/pdf']);
    }

    /**
     * `GET /panel/trabajos/detalle/{trabajo}/evidencias` (HU-42, tarea 56):
     * galería de evidencias de un trabajo — imagen de campo, captura del
     * control remoto de cada sesión, firma del acta y fotos de incidencia
     * por sesión (todas las evidencias que existen para un trabajo). Mismo
     * permiso que `show()`: es una sub-pantalla del detalle, no un recurso
     * con permiso propio.
     */
    public function evidencias(Request $request, Trabajo $trabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return view('operaciones::pages.trabajos.evidencias', [
            ...$this->autorizacion->cascara($request),
            'trabajo' => $trabajo->load(['imagenCampoEvidencia', 'acta.evidenciaFirma', 'sesiones.capturaRc', 'sesiones.incidencias.evidenciaFoto']),
        ]);
    }

    /**
     * `GET /panel/evidencias/{evidencia}/archivo`: streaming del archivo real
     * desde el disco `r2` — mismo patrón que `actaPdf`/`reporteTecnicoPdf`,
     * `archivo_url` nunca se expone directo (es una ruta privada del disco,
     * no una URL pública). Quien llega a la galería ya pasó `self::PERMISO`,
     * pero se reverifica acá por si alguien pega la URL directo.
     */
    public function evidenciaArchivo(Request $request, Evidencia $evidencia): Response
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        abort_unless(Storage::disk('r2')->exists($evidencia->archivo_url), 404);

        return response(Storage::disk('r2')->get($evidencia->archivo_url), 200, [
            'Content-Type' => Storage::disk('r2')->mimeType($evidencia->archivo_url) ?: 'application/octet-stream',
        ]);
    }

    public function destroy(Request $request, Trabajo $trabajo, EliminarTrabajo $eliminarTrabajo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminarTrabajo->ejecutar($trabajo);
        } catch (TrabajoValidadoNoEliminable $excepcion) {
            return redirect()
                ->route('panel.trabajos.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.trabajos.index')
            ->with('estado', __('operaciones.trabajos.eliminado'));
    }

    /**
     * Etiquetas legibles de equipo por id — mismo criterio que
     * `RepartoCuadrillasController::etiquetasEquipo()`.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasEquipo(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_equipos_trabajo')
            ->whereIn('id', $ids)
            ->pluck('codigo', 'id')
            ->map(fn ($valor) => (string) $valor)
            ->all();
    }
}
