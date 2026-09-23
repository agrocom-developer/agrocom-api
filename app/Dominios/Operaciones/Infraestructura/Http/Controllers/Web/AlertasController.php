<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Operaciones\Aplicacion\AtenderAlerta;
use App\Dominios\Operaciones\Aplicacion\ListarAlertas;
use App\Dominios\Operaciones\Dominio\EstadoAlerta;
use App\Dominios\Operaciones\Dominio\TipoAlerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/alertas`, `POST /panel/alertas/{alerta}/atender` (HU-19, tarea
 * 26): bandeja de alertas por excepción del encargado de operaciones.
 *
 * Vive en `routes/web.php`, NUNCA en `routes/api.php` (ADR 0008, regla 1: ese
 * archivo sirve exclusivamente a las apps de campo por token Sanctum de
 * dispositivo — el prompt de la tarea nombraba `/api/alertas`, pero esta
 * pantalla es del encargado de operaciones desde el panel, con sesión; el
 * "api" del prompt describía la forma del endpoint, no el archivo de rutas
 * literal). Documentado en runs/26.md.
 *
 * Dos permisos, mismo criterio que `ValidacionSesionesController`
 * (`operaciones.sesion.validar`): uno gatea la pantalla completa
 * (`operaciones.alerta.ver`), otro gatea la acción de atender
 * (`operaciones.alerta.atender`) — ambos verificados DENTRO del controlador
 * contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}, nunca `SecUser`
 * directo (ADR 0003, regla 2).
 */
final class AlertasController
{
    private const PERMISO_VER = 'operaciones.alerta.ver';

    private const PERMISO_ATENDER = 'operaciones.alerta.atender';

    /**
     * Tono de cada estado, definido UNA vez (§6.3.4 de la guía de pantalla):
     * lo comparten el badge del listado, la tarjeta de KPI, la acción de la
     * fila y el modal de confirmación de «Atender». Los tonos son los que la
     * bandeja ya tenía: no se reeligen acá.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'pendiente' => 'warning',
        'atendida' => 'success',
    ];

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarAlertas $listarAlertas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estadoQuery = TextoDeFiltro::de($request, 'estado');
        $estado = $estadoQuery !== '' ? EstadoAlerta::tryFrom($estadoQuery) : null;
        $tipoQuery = TextoDeFiltro::de($request, 'tipo');
        $tipo = $tipoQuery !== '' ? TipoAlerta::tryFrom($tipoQuery) : null;

        return view('operaciones::pages.alertas.index', [
            ...$this->autorizacion->cascara($request),
            'alertas' => $listarAlertas->ejecutar($estado, $tipo),
            'resumen' => $listarAlertas->resumen($estado, $tipo),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'estadosFiltro' => EstadoAlerta::cases(),
            'tiposFiltro' => TipoAlerta::cases(),
            'filtros' => [
                'estado' => $estado?->value,
                'tipo' => $tipo?->value,
            ],
            'puedeAtender' => $this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER),
        ]);
    }

    public function atender(Request $request, Alerta $alerta, AtenderAlerta $atenderAlerta): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ATENDER), 403);

        $personaId = $this->autorizacion->personaId($request);
        abort_if($personaId === null, 403);

        $atenderAlerta->ejecutar($alerta, $personaId);

        return redirect()
            ->route('panel.alertas.index')
            ->with('estado', __('operaciones.alertas.atendida'));
    }
}
