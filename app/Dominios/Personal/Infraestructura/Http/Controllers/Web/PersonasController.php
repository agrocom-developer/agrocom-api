<?php

namespace App\Dominios\Personal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Personal\Aplicacion\ActualizarPersona;
use App\Dominios\Personal\Aplicacion\CrearPersona;
use App\Dominios\Personal\Aplicacion\EliminarPersona;
use App\Dominios\Personal\Aplicacion\ListarPersonas;
use App\Dominios\Personal\Aplicacion\ObtenerDesempenioPersona;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Personal\Infraestructura\Http\Requests\ActualizarPersonaRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\CrearPersonaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/personas*` (HU-26, tarea 37): alta y
 * mantenimiento de personas operativas, con su rol y tarifa por hectárea.
 * Mismo molde que `BasesController` (misma tarea), pero con un `select`
 * adicional de base (opcional) — mismo criterio que `cliente_id` en
 * `CamposController`.
 *
 * Cuatro permisos de grano fino
 * (`personal.persona.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo. Editar `tarifa_ha` acá NO
 * afecta devengos ya generados — `Finanzas/GenerarDevengosSesion` congela su
 * propia copia al validarse la sesión (ver `ActualizarPersona`).
 */
final class PersonasController
{
    private const PERMISO_VER = 'personal.persona.ver';

    private const PERMISO_CREAR = 'personal.persona.crear';

    private const PERMISO_EDITAR = 'personal.persona.editar';

    private const PERMISO_ELIMINAR = 'personal.persona.eliminar';

    private const PERMISO_DESEMPENIO = 'personal.persona.desempenio';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarPersonas $listarPersonas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('personal::pages.personas.index', [
            ...$this->autorizacion->cascara($request),
            'personas' => $listarPersonas->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('personal::pages.personas.create', [
            ...$this->autorizacion->cascara($request),
            // `rolesOperativos`, no `roles`: `roles` ya es de la cáscara (los
            // roles del USUARIO, que el panel usa para ofrecer «Cambiar de rol»)
            // y pisarlo con los del enum lo dejaba siempre en «varios roles».
            'rolesOperativos' => RolOperativoPersona::cases(),
            'basesDisponibles' => $this->basesActivas(),
            // Atajo «Nueva persona» de la ficha de una base: llega con
            // `?base_id=` y el formulario la deja elegida.
            'baseIdInicial' => $request->string('base_id')->toString(),
            // Alta rápida desde otro formulario (tarea "cuadrillas-estadias",
            // 19/9/2026 — mismo criterio que `PropiedadesController::create()`):
            // con ?volver_a=, al guardar se ofrece un botón para volver a esa
            // URL con esta persona ya disponible en el select que la pidió.
            'volverA' => $this->origenLocal($request->query('volver_a')),
        ]);
    }

    public function store(CrearPersonaRequest $request, CrearPersona $crearPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $persona = $crearPersona->ejecutar(
            (string) $datos['nombre'],
            RolOperativoPersona::from((string) $datos['rol']),
            isset($datos['base_id']) && $datos['base_id'] !== '' ? (int) $datos['base_id'] : null,
            $this->cadenaONull($datos['tarifa_ha'] ?? null),
            (bool) ($datos['activo'] ?? false),
        );

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        // `volverA` viaja igual que en `PropiedadesController::store()` para
        // el caso de alta rápida desde otro formulario.
        return redirect()
            ->route('panel.personas.edit', $persona)
            ->with('estado', __('personal.personas.creado'))
            ->with('volverA', $this->origenLocal($request->input('volver_a')));
    }

    public function edit(Request $request, PerPersona $persona): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('personal::pages.personas.edit', [
            ...$this->autorizacion->cascara($request),
            'persona' => $persona,
            'rolesOperativos' => RolOperativoPersona::cases(),
            'basesDisponibles' => $this->basesActivas(),
            'volverA' => session('volverA'),
        ]);
    }

    public function update(ActualizarPersonaRequest $request, PerPersona $persona, ActualizarPersona $actualizarPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        $actualizarPersona->ejecutar(
            $persona,
            (string) $datos['nombre'],
            RolOperativoPersona::from((string) $datos['rol']),
            isset($datos['base_id']) && $datos['base_id'] !== '' ? (int) $datos['base_id'] : null,
            $this->cadenaONull($datos['tarifa_ha'] ?? null),
            (bool) ($datos['activo'] ?? false),
        );

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.personas.edit', $persona)
            ->with('estado', __('personal.personas.actualizado'));
    }

    public function destroy(Request $request, PerPersona $persona, EliminarPersona $eliminarPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarPersona->ejecutar($persona);

        return redirect()
            ->route('panel.personas.index')
            ->with('estado', __('personal.personas.eliminado'));
    }

    /**
     * Ficha de desempeño (HU-58, tarea 81): "¿qué hizo esta persona esta
     * campaña?", por sesión y no por equipo de trabajo (ADR 0015 punto 3).
     * Filtros por `GET` con querystring, mismo criterio que
     * `CuadrillasController::show()` — rango de fechas (default los
     * últimos 12 meses) y cliente/campaña, esta última dependiente del
     * cliente elegido (JS, presentación — el caso de uso ya filtra en
     * PHP sin importar lo que el navegador haya mostrado u ocultado).
     */
    public function desempenio(Request $request, PerPersona $persona, ObtenerDesempenioPersona $obtenerDesempenio): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_DESEMPENIO), 403);

        $hastaQuery = $request->string('hasta')->toString();
        $hasta = $hastaQuery !== '' ? $hastaQuery : now()->toDateString();

        $desdeQuery = $request->string('desde')->toString();
        $desde = $desdeQuery !== '' ? $desdeQuery : now()->subMonths(12)->toDateString();

        $clienteQuery = $request->string('cliente_id')->toString();
        $clienteId = $clienteQuery !== '' ? (int) $clienteQuery : null;

        $campaniaQuery = $request->string('campania_id')->toString();
        $campaniaId = $campaniaQuery !== '' ? (int) $campaniaQuery : null;

        $resultado = $obtenerDesempenio->ejecutar($persona->id, $desde, $hasta, $clienteId, $campaniaId);

        return view('personal::pages.personas.desempeno', [
            ...$this->autorizacion->cascara($request),
            'persona' => $persona,
            'resultado' => $resultado,
            'filtros' => ['desde' => $desde, 'hasta' => $hasta, 'cliente_id' => $clienteId, 'campania_id' => $campaniaId],
        ]);
    }

    /** @return Collection<int, string> */
    private function basesActivas(): Collection
    {
        return PerBase::query()->orderBy('nombre')->pluck('nombre', 'id');
    }

    /**
     * El `volver_a` de una alta rápida termina como `href` del botón «Volver al
     * formulario de origen»: solo se acepta una ruta de este mismo sitio (relativa,
     * o absoluta bajo la URL de la app) y sin espacios, controles ni barras
     * invertidas — el navegador los descarta o normaliza y `/\ /otro.com` acaba
     * siendo otro dominio. Cualquier otra cosa (`javascript:`, otro host) se ignora.
     */
    private function origenLocal(mixed $url): ?string
    {
        if (! is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }

        $esRutaLocal = str_starts_with($url, '/') && ! str_starts_with($url, '//');

        return $esRutaLocal || str_starts_with($url, url('/').'/') ? $url : null;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
