<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\ActualizarTrabajo;
use App\Dominios\Operaciones\Aplicacion\EliminarTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoValidadoNoEditable;
use App\Dominios\Operaciones\Dominio\Excepciones\TrabajoValidadoNoEliminable;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajoEquipo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\PasosDeOrden;
use App\Dominios\Operaciones\Infraestructura\Http\PasosDeTrabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ActualizarTrabajoRequest;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
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

    /**
     * Tarea 114: cada tarjeta del resumen relacionado se gatea por el permiso
     * del módulo de lo que MUESTRA, no por el de esta pantalla — ver
     * {@see self::relacionadoDeEdicion()}.
     */
    private const PERMISO_VER_ORDEN = 'operaciones.orden.ver';

    private const PERMISO_VER_CUADRILLA = 'personal.equipo_trabajo.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    /**
     * Arquetipo Detalle (tarea 124, guía §6.4): misma anatomía que
     * `OrdenesController::show()`/`OrdenesTrabajoController::show()` —
     * `page-header` + KPI + `form-layout` de solo lectura + aside con
     * avance/vínculos/actividad. Editar/eliminar son las mismas dos acciones
     * que ya existían (mismo permiso y misma guarda de "no validado" que
     * `edit()`/`destroy()`), ninguna acción nueva.
     */
    public function show(Request $request, Trabajo $trabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $trabajo->load(['sesiones.rechazo', 'sesiones.dron', 'acta', 'reporteTecnico', 'ordenTrabajo.equipos']);
        $sesionesVigentes = $trabajo->sesiones->whereNull('anulada_en');
        $editable = $trabajo->estadoTablero() !== EstadoTableroTrabajo::Validado;
        $condicionPago = $trabajo->ordenTrabajo?->equipos->firstWhere('equipo_trabajo_id', $trabajo->equipo_trabajo_id);

        $hectareasDeclaradas = BigDecimal::of((string) $trabajo->hectareas_declaradas);
        $hectareasAplicadas = BigDecimal::of($this->hectareasAplicadas($trabajo));
        $porcentajeCobertura = $hectareasDeclaradas->isZero()
            ? 0
            : (int) round(((float) (string) $hectareasAplicadas / (float) (string) $hectareasDeclaradas) * 100);

        return view('operaciones::pages.trabajos.show', [
            ...$this->autorizacion->cascara($request),
            'trabajo' => $trabajo,
            'loteLabel' => $this->etiquetasLote([$trabajo->lote_id])[$trabajo->lote_id] ?? "#{$trabajo->lote_id}",
            'cuadrillaLabel' => $trabajo->equipo_trabajo_id !== null
                ? ($this->etiquetasEquipo([$trabajo->equipo_trabajo_id])[$trabajo->equipo_trabajo_id] ?? "#{$trabajo->equipo_trabajo_id}")
                : null,
            'pagoEquipo' => $this->pagoDeEquipo($condicionPago),
            'hectareasAplicadas' => (string) $hectareasAplicadas,
            'porcentajeCobertura' => min(100, $porcentajeCobertura),
            'sesionesValidadas' => $sesionesVigentes->where('estado', EstadoSesion::Validado)->count(),
            'sesionesTotal' => $sesionesVigentes->count(),
            'puedeEditar' => $editable && $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            'puedeEliminar' => $editable && $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
            'puedeVerReporte' => $this->autorizacion->tienePermiso($request, self::PERMISO_REPORTE),
            'vinculos' => $this->vinculosDeTrabajo($trabajo, $request),
            'actividad' => $this->actividadDeTrabajo($trabajo),
        ]);
    }

    /**
     * Condición de pago del equipo de ESTE trabajo (ADR 0023), leída como la
     * ficha de la OT (`OrdenesTrabajoController::pagoDeEquipo()`, misma
     * forma): `null` si el trabajo no tiene OT o su equipo no tiene
     * condición registrada (OT anterior a la reforma de pago).
     *
     * @return array{modalidad: string, monto_piloto: string, monto_auxiliar: string, negociado: bool, motivo: ?string}|null
     */
    private function pagoDeEquipo(?OrdenTrabajoEquipo $condicion): ?array
    {
        if ($condicion === null) {
            return null;
        }

        return [
            'modalidad' => $condicion->modalidad_pago->etiqueta(),
            'monto_piloto' => (string) $condicion->monto_piloto,
            'monto_auxiliar' => (string) $condicion->monto_auxiliar,
            'negociado' => $condicion->negociado,
            'motivo' => $condicion->motivo_negociacion,
        ];
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
     * "Vínculos" de `show()` (arquetipo Detalle): accesos a la OT (si este
     * trabajo pertenece a una), la orden de aplicación, la cuadrilla asignada
     * y los dos PDF cuando existen — mismo espíritu que
     * `OrdenesController::vinculosOrden()`, como filas sueltas en vez de
     * tarjetas completas. Cada uno gatea por el permiso del módulo de LO QUE
     * MUESTRA, igual que {@see self::relacionadoDeEdicion()}.
     *
     * @return list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>
     */
    private function vinculosDeTrabajo(Trabajo $trabajo, Request $request): array
    {
        $vinculos = [];

        if ($trabajo->ordenTrabajo !== null && $this->autorizacion->tienePermiso($request, self::PERMISO)) {
            $vinculos[] = [
                'href' => route('panel.trabajos.show', $trabajo->ordenTrabajo),
                'icon' => 'work_history',
                'title' => __('operaciones.trabajos.vinculo_ot'),
                'meta' => __('operaciones.trabajos.vinculo_ot_meta', ['id' => $trabajo->orden_trabajo_id]),
                'tone' => 'info',
            ];
        }

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_VER_ORDEN)) {
            $vinculos[] = [
                'href' => route('panel.ordenes.show', $trabajo->orden_id),
                'icon' => 'assignment',
                'title' => __('operaciones.trabajos.vinculo_orden'),
                'meta' => __('operaciones.trabajos.vinculo_orden_meta', ['aplicacion' => $trabajo->nro_aplicacion]),
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

        $pasosEstado = PasosDeTrabajo::armar($trabajo->estado);

        return view('operaciones::pages.trabajos.edit', [
            ...$this->autorizacion->cascara($request),
            'trabajo' => $trabajo,
            'lotesDisponibles' => $this->lotesDeLaOrden($trabajo->orden_id),
            'equiposDisponibles' => $equiposDisponibles,
            'pasosEstado' => $pasosEstado,
            'ayudaEstado' => PasosDeTrabajo::ayuda($pasosEstado),
            'relacionado' => $this->relacionadoDeEdicion($request, $trabajo, $equipos),
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

        // Se queda en la ficha de edición, no vuelve al detalle (§6.3.2 de
        // docs/diseno/guia_pantalla_panel.md): corregir un trabajo es seguir
        // trabajando sobre ÉL. El aviso de éxito lo pinta el propio formulario.
        return redirect()
            ->route('panel.trabajos.detalle-editar', $trabajo)
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
     * Resumen relacionado del aside de la ficha de edición (§6.3.1 de
     * docs/diseno/guia_pantalla_panel.md): las relaciones más cercanas del
     * trabajo, derivadas de sus FK reales — sus dos padres (la orden de
     * aplicación por `orden_id`, la orden de trabajo por `orden_trabajo_id`),
     * lo que el trabajo produjo en campo (sus sesiones) y la cuadrilla que lo
     * tiene asignado (`equipo_trabajo_id`).
     *
     * Cada tarjeta se gatea por el permiso del módulo de LO QUE MUESTRA contra
     * el rol activo, no por el de la pantalla: ver la cuadrilla pide
     * `personal.equipo_trabajo.ver`, no `operaciones.trabajo.editar`. La
     * cuadrilla, que es de otro módulo, llega por `Personal\Contratos\
     * LecturaEquipoTrabajo` con su DTO — nunca por `EquipoTrabajo` ni por un
     * `join` a `per_*` (ADR 0003, regla 3).
     *
     * Mientras el trabajo siga `abierto` el aside no dibuja tarjetas: muestra
     * una sola sección informativa con el paso que falta (plan §3.5) — hasta
     * que el piloto no cierra el trabajo desde la app de campo no hay
     * sesiones cerradas, ni acta, ni reporte que resumir.
     *
     * @return array{aviso: array{titulo: string, detalle: string}|null, tarjetas: list<array{titulo: string, items: list<array{label: string, value: string, mono?: bool, badge?: bool, variant?: string}>, acciones: list<array{label: string, href: string, icono: string}>}>}
     */
    private function relacionadoDeEdicion(Request $request, Trabajo $trabajo, LecturaEquipoTrabajo $equipos): array
    {
        if ($trabajo->estado === EstadoTrabajo::Abierto) {
            return [
                'aviso' => [
                    'titulo' => __('operaciones.trabajos.aside_abierto_titulo'),
                    'detalle' => __('operaciones.trabajos.aside_abierto_detalle'),
                ],
                'tarjetas' => [],
            ];
        }

        $tarjetas = [];

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_VER_ORDEN)) {
            $tarjetas[] = $this->tarjetaOrden($trabajo);
        }

        if ($this->autorizacion->tienePermiso($request, self::PERMISO)) {
            if ($trabajo->orden_trabajo_id !== null) {
                $tarjetas[] = $this->tarjetaOrdenTrabajo($trabajo);
            }

            $tarjetas[] = $this->tarjetaRegistroEnCampo($trabajo);
        }

        if ($trabajo->equipo_trabajo_id !== null && $this->autorizacion->tienePermiso($request, self::PERMISO_VER_CUADRILLA)) {
            $tarjetas[] = $this->tarjetaCuadrilla($trabajo, $equipos);
        }

        return ['aviso' => null, 'tarjetas' => array_values(array_filter($tarjetas))];
    }

    /**
     * La orden de aplicación de la que cuelga el trabajo. El estado va con el
     * mismo tono que ya usa su badge en el listado de órdenes
     * ({@see PasosDeOrden::TONO_POR_ESTADO}), para que los dos hablen con el
     * mismo color.
     *
     * @return array{titulo: string, items: list<array{label: string, value: string, mono?: bool, badge?: bool, variant?: string}>, acciones: list<array{label: string, href: string, icono: string}>}
     */
    private function tarjetaOrden(Trabajo $trabajo): array
    {
        $orden = OrdenAplicacion::query()->find($trabajo->orden_id);

        return [
            'titulo' => __('operaciones.trabajos.aside_orden_titulo'),
            'items' => $orden === null ? [] : [
                [
                    'label' => __('operaciones.trabajos.aside_orden_nro'),
                    'value' => '#'.$orden->nro_aplicacion,
                    'mono' => true,
                ],
                [
                    'label' => __('operaciones.trabajos.aside_orden_tipo'),
                    'value' => __('operaciones.tipo_aplicacion.'.$orden->tipo_aplicacion->value),
                ],
                [
                    'label' => __('operaciones.trabajos.aside_orden_estado'),
                    'value' => __('operaciones.estado.'.$orden->estado->value),
                    'badge' => true,
                    'variant' => PasosDeOrden::TONO_POR_ESTADO[$orden->estado->value],
                ],
            ],
            'acciones' => $orden === null ? [] : [[
                'label' => __('operaciones.trabajos.aside_orden_accion'),
                'href' => route('panel.ordenes.show', $orden),
                'icono' => 'assignment',
            ]],
        ];
    }

    /**
     * La tanda (orden de trabajo) en la que se repartió este trabajo: cuántos
     * trabajos la componen y cuántas cuadrillas distintas cubre.
     *
     * @return array{titulo: string, items: list<array{label: string, value: string, mono?: bool, badge?: bool, variant?: string}>, acciones: list<array{label: string, href: string, icono: string}>}
     */
    private function tarjetaOrdenTrabajo(Trabajo $trabajo): array
    {
        $tanda = OrdenTrabajo::query()->with('trabajos')->find($trabajo->orden_trabajo_id);
        $cuadrillas = $tanda?->trabajos->pluck('equipo_trabajo_id')->filter()->unique()->count() ?? 0;

        return [
            'titulo' => __('operaciones.trabajos.aside_tanda_titulo'),
            'items' => $tanda === null ? [] : [
                [
                    'label' => __('operaciones.trabajos.aside_tanda_id'),
                    'value' => '#'.$tanda->id,
                    'mono' => true,
                ],
                [
                    'label' => __('operaciones.trabajos.aside_tanda_trabajos'),
                    'value' => (string) $tanda->trabajos->count(),
                    'mono' => true,
                ],
                [
                    'label' => __('operaciones.trabajos.aside_tanda_cuadrillas'),
                    'value' => (string) $cuadrillas,
                    'mono' => true,
                ],
            ],
            'acciones' => $tanda === null ? [] : [[
                'label' => __('operaciones.trabajos.aside_tanda_accion'),
                'href' => route('panel.trabajos.show', $tanda),
                'icono' => 'work_history',
            ]],
        ];
    }

    /**
     * Lo que el trabajo registró en campo: sus sesiones vigentes (las anuladas
     * no cuentan, invariante 2), cuántas ya pasaron por la cola de validación y
     * cuántas hectáreas suman. Las cifras se recalculan desde las sesiones, no
     * se cachean (invariante 6).
     *
     * @return array{titulo: string, items: list<array{label: string, value: string, mono?: bool, badge?: bool, variant?: string}>, acciones: list<array{label: string, href: string, icono: string}>}
     */
    private function tarjetaRegistroEnCampo(Trabajo $trabajo): array
    {
        $vigentes = $trabajo->sesiones()->whereNull('anulada_en');

        return [
            'titulo' => __('operaciones.trabajos.aside_campo_titulo'),
            'items' => [
                [
                    'label' => __('operaciones.trabajos.aside_campo_sesiones'),
                    'value' => (string) $vigentes->clone()->count(),
                    'mono' => true,
                ],
                [
                    'label' => __('operaciones.trabajos.aside_campo_validadas'),
                    'value' => (string) $vigentes->clone()->where('estado', EstadoSesion::Validado)->count(),
                    'mono' => true,
                ],
                [
                    'label' => __('operaciones.trabajos.aside_campo_hectareas'),
                    'value' => number_format((float) $this->hectareasAplicadas($trabajo), 2, ',', '.'),
                    'mono' => true,
                ],
            ],
            'acciones' => [
                [
                    'label' => __('operaciones.trabajos.aside_campo_accion_detalle'),
                    'href' => route('panel.trabajos.detalle', $trabajo),
                    'icono' => 'flight',
                ],
                [
                    'label' => __('operaciones.trabajos.aside_campo_accion_evidencias'),
                    'href' => route('panel.trabajos.evidencias', $trabajo),
                    'icono' => 'photo_library',
                ],
            ],
        ];
    }

    /**
     * La cuadrilla asignada, leída por el contrato de `Personal` a la fecha de
     * inicio del trabajo: es la que lo cubría ese día, no la que el equipo
     * tenga hoy.
     *
     * @return array{titulo: string, items: list<array{label: string, value: string, mono?: bool, badge?: bool, variant?: string}>, acciones: list<array{label: string, href: string, icono: string}>}|null
     */
    private function tarjetaCuadrilla(Trabajo $trabajo, LecturaEquipoTrabajo $equipos): ?array
    {
        $equipoId = (int) $trabajo->equipo_trabajo_id;
        $equipo = $equipos->porIds([$equipoId])[$equipoId] ?? null;

        if ($equipo === null) {
            return null;
        }

        $fecha = $trabajo->inicio->toDateString();

        return [
            'titulo' => __('operaciones.trabajos.aside_cuadrilla_titulo'),
            'items' => [
                [
                    'label' => __('operaciones.trabajos.aside_cuadrilla_codigo'),
                    'value' => $equipo->codigo,
                    'mono' => true,
                ],
                [
                    'label' => __('operaciones.trabajos.aside_cuadrilla_nombre'),
                    'value' => $equipo->nombre ?? __('operaciones.trabajos.aside_cuadrilla_sin_nombre'),
                ],
                [
                    'label' => __('operaciones.trabajos.aside_cuadrilla_integrantes'),
                    'value' => (string) count($equipos->integrantesAFecha($equipoId, $fecha)),
                    'mono' => true,
                ],
            ],
            'acciones' => [[
                'label' => __('operaciones.trabajos.aside_cuadrilla_accion'),
                'href' => route('panel.cuadrillas.show', $equipoId),
                'icono' => 'groups',
            ]],
        ];
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
     * mismo formato de etiqueta que `RepartoCuadrillasController::equiposDisponibles()`.
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
