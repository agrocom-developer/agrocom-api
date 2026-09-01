<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\RechazarSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\SesionNoDisponibleParaDecision;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\RechazarSesionRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 */
final class ValidacionSesionesController
{
    private const PERMISO = 'operaciones.sesion.validar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $personaId = $this->autorizacion->personaId($request);

        return view('operaciones::pages.sesiones.validacion', [
            ...$this->autorizacion->cascara($request),
            'sesiones' => Sesion::query()
                ->where('estado', EstadoSesion::Cerrado)
                ->whereNull('anulada_en')
                ->orderBy('fin')
                ->get(),
            'personaId' => $personaId,
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
}
