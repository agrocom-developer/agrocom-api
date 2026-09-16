<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Mantenimiento\Aplicacion\ActualizarFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\CrearFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\EliminarFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\ListarFichasDron;
use App\Dominios\Mantenimiento\Dominio\Excepciones\FichaDronDuplicada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarFichaDronRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearFichaDronRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/fichas-dron*` (HU-82, tarea 97): alta y
 * mantenimiento de la ficha de inventario del dron (serie, chasis, versión
 * de software, región, serie del control, accesorios). Mismo molde que
 * `BateriasController`, sin sub-entidad ni cruce con otro módulo salvo la
 * validación de `identificador_dron` que hace el propio Request.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.ficha_dron.ver`/`.crear`/`.editar`/`.eliminar`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que el resto del panel.
 */
final class FichasDronController
{
    private const PERMISO_VER = 'mantenimiento.ficha_dron.ver';

    private const PERMISO_CREAR = 'mantenimiento.ficha_dron.crear';

    private const PERMISO_EDITAR = 'mantenimiento.ficha_dron.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.ficha_dron.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarFichasDron $listarFichasDron): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        $fichas = $listarFichasDron->ejecutar(busqueda: $busqueda !== '' ? $busqueda : null);

        return view('mantenimiento::pages.fichas-dron.index', [
            ...$this->autorizacion->cascara($request),
            'fichas' => $fichas,
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.fichas-dron.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearFichaDronRequest $request, CrearFichaDron $crearFichaDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $fichaDron = $crearFichaDron->ejecutar(
                (string) $datos['identificador_dron'],
                $this->cadenaONull($datos['numero_serie'] ?? null),
                $this->cadenaONull($datos['chasis'] ?? null),
                $this->cadenaONull($datos['version_software'] ?? null),
                $this->cadenaONull($datos['region'] ?? null),
                $this->cadenaONull($datos['serie_control'] ?? null),
                (bool) ($datos['tiene_cargador_control'] ?? false),
                (bool) ($datos['tiene_modem'] ?? false),
                (bool) ($datos['tiene_maletin'] ?? false),
            );
        } catch (FichaDronDuplicada $excepcion) {
            return redirect()
                ->route('panel.fichas-dron.create')
                ->withInput()
                ->withErrors(['identificador_dron' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.fichas-dron.edit', $fichaDron)
            ->with('estado', __('mantenimiento.fichas_dron.creado'));
    }

    public function edit(Request $request, FichaDron $fichaDron): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.fichas-dron.edit', [
            ...$this->autorizacion->cascara($request),
            'ficha' => $fichaDron,
        ]);
    }

    public function update(ActualizarFichaDronRequest $request, FichaDron $fichaDron, ActualizarFichaDron $actualizarFichaDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarFichaDron->ejecutar(
                $fichaDron,
                (string) $datos['identificador_dron'],
                $this->cadenaONull($datos['numero_serie'] ?? null),
                $this->cadenaONull($datos['chasis'] ?? null),
                $this->cadenaONull($datos['version_software'] ?? null),
                $this->cadenaONull($datos['region'] ?? null),
                $this->cadenaONull($datos['serie_control'] ?? null),
                (bool) ($datos['tiene_cargador_control'] ?? false),
                (bool) ($datos['tiene_modem'] ?? false),
                (bool) ($datos['tiene_maletin'] ?? false),
            );
        } catch (FichaDronDuplicada $excepcion) {
            return redirect()
                ->route('panel.fichas-dron.edit', $fichaDron)
                ->withInput()
                ->withErrors(['identificador_dron' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.fichas-dron.edit', $fichaDron)
            ->with('estado', __('mantenimiento.fichas_dron.actualizado'));
    }

    public function destroy(Request $request, FichaDron $fichaDron, EliminarFichaDron $eliminarFichaDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarFichaDron->ejecutar($fichaDron);

        return redirect()
            ->route('panel.fichas-dron.index')
            ->with('estado', __('mantenimiento.fichas_dron.eliminado'));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
