<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\ActualizarTrabajo;
use App\Dominios\Operaciones\Aplicacion\EliminarTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoValidadoNoEditable;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoValidadoNoEliminable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ActualizarTrabajoRequest;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * `GET /panel/trabajos/detalle/{trabajo}` (HU-05/HU-15, extendido por HU-93
 * y la reforma "Orden de Trabajo" del 18/9/2026): detalle de UN `Trabajo`
 * puntual (equipo×lote) — sus sesiones, acta, reporte técnico, evidencias, y
 * (HU-93) editar/eliminar mientras no esté `validado`.
 *
 * Antes de la reforma, esta clase también tenía `index()` (tablero de TODOS
 * los trabajos) — ese listado ahora es maestro de `OrdenTrabajo`, vive en
 * {@see OrdenesTrabajoController}. Esta clase queda recortada al detalle de
 * UN trabajo (mismo criterio de nombre que el resto del panel: el
 * controlador se llama como el modelo del que es dueño), rutas movidas de
 * `panel.trabajos.show/edit/...` a `panel.trabajos.detalle*` para no chocar
 * con el `show()` nuevo del maestro (que ahora es una `OrdenTrabajo`).
 *
 * Mismo patrón que `VersionesApkController` (HU-20): un único permiso
 * (`operaciones.trabajo.ver`) gatea toda la pantalla, verificado DENTRO del
 * controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} — nunca
 * `SecUser`/`CascaraPanel` directos (ADR 0003, regla 2).
 */
final class TrabajosController
{
    private const PERMISO = 'operaciones.trabajo.ver';

    /** HU-93 (tarea 108): editar/eliminar un trabajo mientras no esté `validado` — ver `Aplicacion/ActualizarTrabajo`/`EliminarTrabajo`. */
    private const PERMISO_EDITAR = 'operaciones.trabajo.editar';

    private const PERMISO_ELIMINAR = 'operaciones.trabajo.eliminar';

    /** HU-18 (tarea 25): gatea solo el botón/ruta del reporte técnico, no toda la pantalla — ver runs/25.md. */
    private const PERMISO_REPORTE = 'operaciones.reporte.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function show(Request $request, Trabajo $trabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return view('operaciones::pages.trabajos.show', [
            ...$this->autorizacion->cascara($request),
            'trabajo' => $trabajo->load(['sesiones.rechazo', 'acta', 'reporteTecnico', 'ordenTrabajo']),
            'puedeVerReporte' => $this->autorizacion->tienePermiso($request, self::PERMISO_REPORTE),
        ]);
    }

    /**
     * `GET /panel/trabajos/detalle/{trabajo}/acta/pdf` (HU-17, tarea 24):
     * solo lectura, mismo permiso que `show()` — generar/firmar el acta es
     * de `agrocom-field` (`ActaController`, API), no del panel.
     */
    public function actaPdf(Request $request, Trabajo $trabajo): Response
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $acta = $trabajo->acta;

        abort_if($acta === null || $acta->pdf_path === null || ! Storage::disk('r2')->exists($acta->pdf_path), 404);

        return response(Storage::disk('r2')->get($acta->pdf_path), 200, ['Content-Type' => 'application/pdf']);
    }

    /**
     * `GET /panel/trabajos/detalle/{trabajo}/reporte/pdf` (HU-18, tarea 25):
     * solo lectura, permiso propio `operaciones.reporte.ver` — el reporte se
     * genera solo al firmar el acta (`GenerarReporteTecnico`, enganchado en
     * `FirmarActa`); esta ruta nunca lo genera.
     */
    public function reporteTecnicoPdf(Request $request, Trabajo $trabajo): Response
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_REPORTE), 403);

        $reporte = $trabajo->reporteTecnico;

        abort_if($reporte === null || $reporte->pdf_path === null || ! Storage::disk('r2')->exists($reporte->pdf_path), 404);

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

    /**
     * `GET /panel/trabajos/detalle/{trabajo}/editar` (HU-93, tarea 108):
     * corregir lote/equipo/hectáreas/turno de un trabajo cargado mal. Sin
     * guarda de estado acá a propósito (mismo criterio que
     * `OrdenesController::edit()`): la guarda de negocio ("no validado")
     * vive en `Aplicacion/ActualizarTrabajo`, evaluada recién en `update()`
     * — acá solo se verifica el permiso. El link "Editar" ya se oculta en el
     * maestro-detalle para un trabajo validado, pero un `GET` directo a esta
     * URL sigue pudiendo abrir el formulario; lo que nunca puede pasar es
     * que un `PUT` a `update()` sobrescriba el registro validado.
     */
    public function edit(Request $request, Trabajo $trabajo, LecturaEquipoTrabajo $equipos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $equiposDisponibles = $this->equiposDisponibles($equipos);

        // El equipo actual del trabajo puede haber dejado de estar vigente
        // desde que se le asignó — se agrega igual a las opciones (con su
        // etiqueta real) para que el formulario no lo pierda de vista ni lo
        // desasigne en silencio si el encargado guarda sin tocar este campo.
        if ($trabajo->equipo_trabajo_id !== null && ! $equiposDisponibles->has($trabajo->equipo_trabajo_id)) {
            $etiquetaActual = $this->etiquetasEquipo([$trabajo->equipo_trabajo_id]);

            if (isset($etiquetaActual[$trabajo->equipo_trabajo_id])) {
                $equiposDisponibles[$trabajo->equipo_trabajo_id] = $etiquetaActual[$trabajo->equipo_trabajo_id];
            }
        }

        return view('operaciones::pages.trabajos.edit', [
            ...$this->autorizacion->cascara($request),
            'trabajo' => $trabajo,
            'lotesDisponibles' => $this->lotesDeLaOrden($trabajo->orden_id),
            'equiposDisponibles' => $equiposDisponibles,
        ]);
    }

    public function update(ActualizarTrabajoRequest $request, Trabajo $trabajo, ActualizarTrabajo $actualizarTrabajo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarTrabajo->ejecutar($trabajo, [
                'lote_id' => (int) $datos['lote_id'],
                'equipo_trabajo_id' => isset($datos['equipo_trabajo_id']) && $datos['equipo_trabajo_id'] !== ''
                    ? (int) $datos['equipo_trabajo_id']
                    : null,
                'hectareas_declaradas' => (string) $datos['hectareas_declaradas'],
                'turno' => $datos['turno'] ?? null,
                'turno_hora_inicio' => $datos['turno_hora_inicio'] ?? null,
                'turno_hora_fin' => $datos['turno_hora_fin'] ?? null,
            ]);
        } catch (TrabajoValidadoNoEditable $excepcion) {
            return redirect()
                ->route('panel.trabajos.detalle', $trabajo)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        } catch (EquipoTrabajoNoVigente|LoteNoPerteneceAOrden|HectareasAsignadasSuperanLote $excepcion) {
            return redirect()
                ->route('panel.trabajos.detalle-editar', $trabajo)
                ->withErrors(['hectareas_declaradas' => $excepcion->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('panel.trabajos.detalle', $trabajo)
            ->with('estado', __('operaciones.trabajos.actualizado'));
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
     * Lotes de la orden de ESTE trabajo (`ope_orden_lotes`), para el
     * `<select>` de `edit()` — mismo criterio de "solo lo que pertenece a la
     * orden" que `AsignarEquipoOrdenRequest`, con etiqueta legible (mismo
     * formato que `OrdenesController::etiquetasLote()`).
     *
     * @return array<int, string>
     */
    private function lotesDeLaOrden(int $ordenId): array
    {
        $loteIds = DB::table('ope_orden_lotes')
            ->where('orden_id', $ordenId)
            ->whereNull('deleted_at')
            ->orderBy('lote_id')
            ->pluck('lote_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->etiquetasLote($loteIds);
    }

    /**
     * Equipos vigentes hoy para el `<select>` de `edit()` — mismo criterio y
     * mismo formato de etiqueta que `AsignacionEquiposController::equiposDisponibles()`.
     *
     * @return Collection<int, string>
     */
    private function equiposDisponibles(LecturaEquipoTrabajo $equipos): Collection
    {
        return collect($equipos->vigentesAFecha(now()->toDateString()))
            ->mapWithKeys(fn (DatosEquipoTrabajo $equipo): array => [
                $equipo->id => $equipo->nombre !== null
                    ? "{$equipo->codigo} — {$equipo->nombre}"
                    : $equipo->codigo,
            ]);
    }

    /**
     * Etiquetas legibles de equipo por id — mismo criterio que
     * `AsignacionEquiposController::etiquetasEquipo()`.
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

    /**
     * Etiquetas legibles de lote por id — mismo criterio que
     * `OrdenesController::etiquetasLote()`.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasLote(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('com_lotes as l')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->whereIn('l.id', $ids)
            ->get(['l.id', 'p.nombre', 'l.codigo'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => __('operaciones.ordenes.campo_lote_opcion', [
                    'campo' => $fila->nombre,
                    'codigo' => $fila->codigo,
                ]),
            ])
            ->all();
    }
}
