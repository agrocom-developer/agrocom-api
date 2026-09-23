<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\RechazarSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\SesionNoDisponibleParaDecision;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\RechazarSesionRequest;
use App\Dominios\Personal\Contratos\LecturaPanelPersonal;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET /panel/sesiones/validacion`, `POST .../validar`, `POST .../rechazar`
 * (HU-14, tarea 14): la cola del jefe de campo — sesiones `cerrado`
 * pendientes de aprobación.
 *
 * Un único permiso (`operaciones.sesion.validar`) gatea toda la pantalla,
 * mismo criterio que `VersionesApkController`/`TrabajosController` — pero
 * ACÁ, además, la policy de la invariante 4 (validador ≠ piloto, a nivel
 * persona) rige cada FILA puntual: `index()` la usa para deshabilitar el
 * botón de validar en la sesión propia; `validar()`/`rechazar()` la
 * reverifican del lado del servidor si igual se fuerza el POST — nunca se
 * confía en que el botón deshabilitado alcanza.
 *
 * Depende de {@see AutorizacionPanelWeb} (contrato de Seguridad, ADR 0003
 * regla 2), nunca de `SecUser` directo.
 *
 * Tarea 131: la fila deja de mostrar códigos crudos sin contexto. El nombre
 * del piloto llega por {@see LecturaPanelPersonal} (Personal, ADR 0003 regla
 * 2 — nunca `PerPersona` directo), en lote para las N sesiones de la cola en
 * una sola consulta. El lote del trabajo (subtítulo, opcional) y el enlace a
 * la ficha del trabajo son el mismo mecanismo que ya usa `TrabajosController`.
 */
final class ValidacionSesionesController
{
    private const PERMISO = 'operaciones.sesion.validar';

    /** Tarea 131: gatea SOLO el enlace de la columna «Trabajo» a su ficha, no la cola entera. */
    private const PERMISO_VER_TRABAJOS = 'operaciones.trabajo.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, LecturaPanelPersonal $lecturaPersonal): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $personaId = $this->autorizacion->personaId($request);

        $sesiones = Sesion::query()
            ->where('estado', EstadoSesion::Cerrado)
            ->whereNull('anulada_en')
            ->with('trabajo')
            ->orderBy('fin')
            ->get();

        return view('operaciones::pages.sesiones.validacion', [
            ...$this->autorizacion->cascara($request),
            'sesiones' => $sesiones,
            'personaId' => $personaId,
            // Tarea 131: nombre real del piloto, nunca `PerPersona` directo desde
            // Operaciones — mismo contrato cruzado de módulos que ya usan
            // Inventario/Seguridad para resolver `ope_sesiones.piloto_id`.
            'etiquetasPiloto' => $lecturaPersonal->nombresDePersonas(
                $sesiones->pluck('piloto_id')->unique()->values()->all()
            ),
            'loteLabelPorTrabajo' => $this->loteLabelPorTrabajo($sesiones),
            'puedeVerTrabajos' => $this->autorizacion->tienePermiso($request, self::PERMISO_VER_TRABAJOS),
        ]);
    }

    public function validar(Request $request, Sesion $sesion, ValidarSesion $validarSesion): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        try {
            $validarSesion->ejecutar($sesion, $this->personaIdONoAutorizado($request));
        } catch (SesionNoDisponibleParaDecision|TransicionSesionNoPermitida $excepcion) {
            return redirect()
                ->route('panel.sesiones.validacion.index')
                ->withErrors(['sesion' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.sesiones.validacion.index')
            ->with('estado', __('operaciones.sesiones_validacion.validada'));
    }

    public function rechazar(RechazarSesionRequest $request, Sesion $sesion, RechazarSesion $rechazarSesion): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        try {
            $rechazarSesion->ejecutar($sesion, (string) $request->validated('motivo'), $this->personaIdONoAutorizado($request));
        } catch (SesionNoDisponibleParaDecision $excepcion) {
            return redirect()
                ->route('panel.sesiones.validacion.index')
                ->withErrors(['motivo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.sesiones.validacion.index')
            ->with('estado', __('operaciones.sesiones_validacion.rechazada'));
    }

    /**
     * Fail-closed (mismo criterio que `AutorizacionPanelWebSesion`): un
     * usuario de panel sin `persona_id` asociada no puede validar ni
     * rechazar nada — `abort(403)` en vez de dejar pasar un `null` que la
     * policy comparara con laxitud.
     */
    private function personaIdONoAutorizado(Request $request): int
    {
        $personaId = $this->autorizacion->personaId($request);

        abort_if($personaId === null, 403);

        return $personaId;
    }

    /**
     * Subtítulo chico de lote para la columna «Trabajo» (tarea 131) — barato
     * porque `$sesiones` ya trae `trabajo` precargado (un solo `with()`, sin
     * N+1 por fila) y esto agrega una única consulta más, en lote.
     *
     * @param  Collection<int, Sesion>  $sesiones
     * @return array<int, string> indexado por `trabajo_id`
     */
    private function loteLabelPorTrabajo(Collection $sesiones): array
    {
        $loteIds = $sesiones
            ->map(fn (Sesion $sesion): ?int => $sesion->trabajo?->lote_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $etiquetasLote = $this->etiquetasLote($loteIds);

        return $sesiones
            ->mapWithKeys(function (Sesion $sesion) use ($etiquetasLote): array {
                $loteId = $sesion->trabajo?->lote_id;

                return [$sesion->trabajo_id => $loteId !== null ? ($etiquetasLote[$loteId] ?? null) : null];
            })
            ->filter()
            ->all();
    }

    /**
     * Etiquetas legibles de lote por id — mismo criterio que
     * `TrabajosController::etiquetasLote()`.
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
