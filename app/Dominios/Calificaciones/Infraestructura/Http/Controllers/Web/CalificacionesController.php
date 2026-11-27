<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Calificaciones\Aplicacion\ListarParaModeracion;
use App\Dominios\Calificaciones\Aplicacion\ModerarCalificacion;
use App\Dominios\Calificaciones\Dominio\CausaModeracion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Calificaciones\Infraestructura\Http\Requests\OcultarCalificacionRequest;
use App\Dominios\Calificaciones\Infraestructura\Http\Requests\RestablecerCalificacionRequest;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Moderación de calificaciones (política de calificación, ADR 0031 y 0039):
 * `GET /panel/calificaciones` (las reportadas primero), `POST
 * /panel/calificaciones/{calificacion}/ocultar` (con causa y motivo) y `POST
 * /panel/calificaciones/{calificacion}/restablecer` (el reporte no procede o la
 * moderación fue un error). Ocultar no borra: la calificación deja de contar para el
 * promedio y conserva su registro.
 *
 * Gateada por `calificaciones.calificacion.ver` (y `.moderar` para las acciones),
 * verificados DENTRO del controlador contra el ROL ACTIVO (invariante 10).
 */
final class CalificacionesController
{
    private const PERMISO_VER = 'calificaciones.calificacion.ver';

    private const PERMISO_MODERAR = 'calificaciones.calificacion.moderar';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaServicios $servicios,
    ) {}

    public function index(Request $request, ListarParaModeracion $listar): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estado = EstadoCalificacion::tryFrom(TextoDeFiltro::de($request, 'estado'));
        $sujeto = SujetoCalificacion::tryFrom(TextoDeFiltro::de($request, 'sujeto'));

        $busqueda = TextoDeFiltro::de($request, 'q');

        $calificaciones = $listar->ejecutar($estado?->value, $sujeto?->value, busqueda: $busqueda);

        return view('calificaciones::pages.calificaciones.index', [
            ...$this->autorizacion->cascara($request),
            'calificaciones' => $calificaciones,
            'nombres' => $this->nombres($calificaciones->getCollection()->all()),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'sujeto' => $sujeto?->value],
            'estadosFiltro' => EstadoCalificacion::cases(),
            'sujetosFiltro' => SujetoCalificacion::cases(),
            'causas' => CausaModeracion::cases(),
            'puedeModerar' => $this->autorizacion->tienePermiso($request, self::PERMISO_MODERAR),
        ]);
    }

    public function ocultar(OcultarCalificacionRequest $request, int $calificacion, ModerarCalificacion $moderar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_MODERAR), 403);

        try {
            $moderar->ocultar($this->actorId($request), $calificacion, CausaModeracion::from((string) $request->validated('causa')), (string) $request->validated('motivo'));
        } catch (CalificacionInvalida $excepcion) {
            return redirect()->route('panel.calificaciones.index')->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.calificaciones.index')->with('estado', __('calificaciones.panel.oculta'));
    }

    public function restablecer(RestablecerCalificacionRequest $request, int $calificacion, ModerarCalificacion $moderar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_MODERAR), 403);

        try {
            $moderar->dejarVisible($this->actorId($request), $calificacion, (string) $request->validated('motivo'));
        } catch (CalificacionInvalida $excepcion) {
            return redirect()->route('panel.calificaciones.index')->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.calificaciones.index')->with('estado', __('calificaciones.panel.restablecida'));
    }

    private function actorId(Request $request): int
    {
        return (int) $request->user('interno')->getAuthIdentifier();
    }

    /**
     * Nombres legibles de los sujetos de la página, resueltos por los contratos de
     * `Proveedores` y de `Solicitudes`: este módulo no lee tablas ajenas.
     *
     * @param  list<Calificacion>  $calificaciones
     * @return array<int, string> por id de calificación.
     */
    private function nombres(array $calificaciones): array
    {
        $nombres = [];

        foreach ($calificaciones as $calificacion) {
            $nombres[$calificacion->id] = match ($calificacion->sujeto_tipo) {
                SujetoCalificacion::Proveedor => $this->proveedores->proveedor($calificacion->sujeto_id)->razonSocial ?? "#{$calificacion->sujeto_id}",
                SujetoCalificacion::Piloto => $this->proveedores->piloto($calificacion->sujeto_id)->nombre ?? "#{$calificacion->sujeto_id}",
                SujetoCalificacion::Cliente => $this->servicios->datos($calificacion->solicitud_id)->clienteNombre ?? "#{$calificacion->sujeto_id}",
            };
        }

        return $nombres;
    }
}
