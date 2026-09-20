<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\AgregarPausasPorCausa;
use App\Dominios\Operaciones\Aplicacion\ListarPausas;
use App\Dominios\Operaciones\Aplicacion\RegistrarPausa;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\Excepciones\PausaFinAnteriorAInicio;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\RegistrarPausaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET/POST /panel/pausas*` (HU-44, tarea 58): "como jefe de campo, quiero
 * registrar las pausas con su causa atribuible (DS-01), para saber qué
 * tiempo se pierde y por qué". Mismo molde que `GastosController` — alta y
 * listado, sin edición: una pausa mal cargada no se corrige, se registra de
 * nuevo (no hay caso de uso de edición ni de baja, a diferencia de `Gasto`
 * — sin dinero de por medio que proteger de una rendición en curso).
 *
 * Dos permisos de grano fino (`operaciones.pausa.ver`/`.registrar`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que el resto del panel.
 * Ninguna regla de negocio acá: la guarda `fin > inicio` y el cálculo de
 * `duracion_minutos` los hace `Aplicacion/RegistrarPausa`.
 */
final class PausasController
{
    private const PERMISO_VER = 'operaciones.pausa.ver';

    private const PERMISO_REGISTRAR = 'operaciones.pausa.registrar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(
        Request $request,
        ListarPausas $listarPausas,
        AgregarPausasPorCausa $agregarPausasPorCausa,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $periodo = $request->string('periodo')->toString();
        $periodoFiltro = $periodo !== '' ? $periodo : null;

        return view('operaciones::pages.pausas.index', [
            ...$this->autorizacion->cascara($request),
            'pausas' => $listarPausas->ejecutar($periodoFiltro),
            'agregado' => $agregarPausasPorCausa->ejecutar($periodoFiltro),
            'filtros' => ['periodo' => $periodo],
            'puedeRegistrar' => $this->autorizacion->tienePermiso($request, self::PERMISO_REGISTRAR),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_REGISTRAR), 403);

        return view('operaciones::pages.pausas.create', [
            ...$this->autorizacion->cascara($request),
            'sesionesDisponibles' => $this->sesionesDisponibles(),
            'opcionesCausa' => collect(CausaPausa::cases())
                ->mapWithKeys(fn (CausaPausa $causa): array => [$causa->value => __('operaciones.pausas.causa.'.$causa->value)])
                ->all(),
        ]);
    }

    public function store(RegistrarPausaRequest $request, RegistrarPausa $registrarPausa): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_REGISTRAR), 403);

        $datos = $request->validated();

        try {
            $registrarPausa->ejecutar(
                (int) $datos['sesion_id'],
                CausaPausa::from((string) $datos['causa']),
                $request->inicio(),
                $request->fin(),
            );
        } catch (PausaFinAnteriorAInicio $excepcion) {
            return redirect()->back()->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.pausas.index')
            ->with('estado', __('operaciones.pausas.creada'));
    }

    /**
     * Últimas 100 sesiones, mismo criterio de acotar el select que
     * `GastosController::trabajosDisponibles()`: el CA esencial es poder
     * ligar la pausa a una sesión, no navegar el historial completo desde
     * este formulario.
     *
     * @return Collection<int, string>
     */
    private function sesionesDisponibles(): Collection
    {
        return Sesion::query()
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'trabajo_id', 'secuencia'])
            ->mapWithKeys(fn (Sesion $sesion): array => [
                $sesion->id => __('operaciones.pausas.sesion_etiqueta', [
                    'id' => $sesion->id,
                    'trabajo' => $sesion->trabajo_id,
                    'secuencia' => $sesion->secuencia,
                ]),
            ]);
    }
}
