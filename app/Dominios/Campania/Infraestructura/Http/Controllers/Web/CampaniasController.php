<?php

namespace App\Dominios\Campania\Infraestructura\Http\Controllers\Web;

use App\Dominios\Campania\Aplicacion\ActualizarCampania;
use App\Dominios\Campania\Aplicacion\CambiarEstadoCampania;
use App\Dominios\Campania\Aplicacion\CrearCampania;
use App\Dominios\Campania\Aplicacion\ListarCampanias;
use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\Excepciones\CampaniaDuplicada;
use App\Dominios\Campania\Dominio\Excepciones\TransicionCampaniaNoPermitida;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Campania\Infraestructura\Http\Requests\ActualizarCampaniaRequest;
use App\Dominios\Campania\Infraestructura\Http\Requests\CambiarEstadoCampaniaRequest;
use App\Dominios\Campania\Infraestructura\Http\Requests\CrearCampaniaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT /panel/campanias*` (ADR 0015 punto 1, tarea 69): alta y
 * mantenimiento del catálogo de campañas — compartido entre clientes desde
 * la corrección del 15/9/2026. Mismo molde que `ContratosController`: sin
 * `destroy` (la baja es una transición de estado hacia `cerrada`, no un soft
 * delete fuera de la máquina de estados — invariante 7), sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`campania.campania.ver`/`.crear`/`.editar`/`.cambiar_estado`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}.
 * `.cambiar_estado` es exclusivo del rol `dueno` en `SeguridadSeeder` — "solo
 * el dueño cierra una campaña" (ADR 0015, prompt de la tarea 69), y ahora
 * cierra la campaña para TODOS los clientes que la usan, no solo para uno.
 * Ninguna regla de negocio acá: los casos de uso de `Aplicacion/` hacen el
 * trabajo.
 */
final class CampaniasController
{
    private const PERMISO_VER = 'campania.campania.ver';

    private const PERMISO_CREAR = 'campania.campania.crear';

    private const PERMISO_EDITAR = 'campania.campania.editar';

    private const PERMISO_CAMBIAR_ESTADO = 'campania.campania.cambiar_estado';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarCampanias $listarCampanias): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        $campanias = $listarCampanias->ejecutar($busqueda !== '' ? $busqueda : null);

        return view('campania::pages.campanias.index', [
            ...$this->autorizacion->cascara($request),
            'campanias' => $campanias,
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('campania::pages.campanias.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearCampaniaRequest $request, CrearCampania $crearCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearCampania->ejecutar(
                (string) $datos['codigo'],
                $this->cadenaONull($datos['nombre'] ?? null),
                (string) $datos['fecha_inicio'],
                (string) $datos['fecha_fin'],
                (string) $datos['estacion'],
            );
        } catch (CampaniaDuplicada $excepcion) {
            return redirect()
                ->route('panel.campanias.create')
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campanias.index')
            ->with('estado', __('campania.campanias.creado'));
    }

    public function edit(Request $request, Campania $campania): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('campania::pages.campanias.edit', [
            ...$this->autorizacion->cascara($request),
            'campania' => $campania,
        ]);
    }

    public function update(ActualizarCampaniaRequest $request, Campania $campania, ActualizarCampania $actualizarCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarCampania->ejecutar(
                $campania,
                (string) $datos['codigo'],
                $this->cadenaONull($datos['nombre'] ?? null),
                (string) $datos['fecha_inicio'],
                (string) $datos['fecha_fin'],
                (string) $datos['estacion'],
            );
        } catch (CampaniaDuplicada $excepcion) {
            return redirect()
                ->route('panel.campanias.edit', $campania)
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campanias.index')
            ->with('estado', __('campania.campanias.actualizado'));
    }

    public function cambiarEstado(CambiarEstadoCampaniaRequest $request, Campania $campania, CambiarEstadoCampania $cambiarEstadoCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CAMBIAR_ESTADO), 403);

        $hacia = EstadoCampania::from((string) $request->validated('estado'));

        try {
            $cambiarEstadoCampania->ejecutar($campania, $hacia);
        } catch (TransicionCampaniaNoPermitida $excepcion) {
            return redirect()
                ->route('panel.campanias.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campanias.index')
            ->with('estado', __('campania.campanias.estado_cambiado'));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
