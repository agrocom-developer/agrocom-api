<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Aplicacion\AprobarRendicion;
use App\Dominios\Finanzas\Aplicacion\AsociarGastoARendicion;
use App\Dominios\Finanzas\Aplicacion\CrearRendicion;
use App\Dominios\Finanzas\Aplicacion\ListarRendiciones;
use App\Dominios\Finanzas\Aplicacion\PresentarRendicion;
use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\GastoYaAsociadoARendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoAceptaGastos;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoAprobable;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoPresentable;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\CrearRendicionRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST /panel/rendiciones*` (HU-34, tarea 48): "como jefe de campo,
 * quiero rendir los gastos que hice en campo; el encargado los aprueba para
 * reponer el fondo". Máquina de estados `abierta → presentada → aprobada`
 * (a diferencia de `AnticiposController`, sin ella) — mismo criterio de
 * controlador delgado que `PlanillasController`/`GastosController`: ninguna
 * regla de negocio acá, todo vive en `Aplicacion/`.
 *
 * Cuatro permisos de grano fino (`finanzas.rendicion.ver`/`.crear`/
 * `.presentar`/`.aprobar`), verificados DENTRO del controlador contra el ROL
 * ACTIVO vía {@see AutorizacionPanelWeb} — mismo criterio que el resto del
 * panel. A diferencia de `finanzas.planilla.aprobar` (exclusivo del dueño),
 * `finanzas.rendicion.aprobar` SÍ lo tiene el encargado (`SeguridadSeeder`):
 * la guarda real de "el aprobador nunca es quien rinde" la resuelve
 * `Aplicacion/AprobarRendicion` vía `PoliticaAprobacionRendicion`, por
 * PERSONA — no el permiso.
 *
 * `aprobar()` toma la `persona_id` del usuario autenticado con
 * `AutorizacionPanelWeb::personaId()`, fail-closed (`abort_if(... === null,
 * 403)`) — mismo patrón que
 * `ValidacionSesionesController::personaIdONoAutorizado()`.
 * `JefeCampoNoPuedeAprobarSuPropiaRendicion` (extiende `AuthorizationException`)
 * NO se atrapa acá: se deja propagar al manejador de excepciones del
 * framework, que la traduce a 403 sin mapeo adicional — mismo criterio que
 * `ValidacionSesionesController::validar()` con
 * `PilotoNoPuedeDecidirSuPropiaSesion`.
 */
final class RendicionesController
{
    /**
     * Tono de cada estado, definido UNA vez (plan de homogeneización §3.1):
     * lo comparten el badge del listado, las acciones de fila que cambian de
     * estado y sus modales, para que los tres hablen con el mismo color.
     * `abierta` y `aprobada` conservan el gris y el verde que el listado ya
     * tenía; `presentada` estrena tono propio (el ternario anterior la
     * pintaba igual que `aprobada`, que es justo lo que hay que distinguir).
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'abierta' => 'neutral',
        'presentada' => 'info',
        'aprobada' => 'success',
    ];

    private const PERMISO_VER = 'finanzas.rendicion.ver';

    private const PERMISO_CREAR = 'finanzas.rendicion.crear';

    private const PERMISO_PRESENTAR = 'finanzas.rendicion.presentar';

    private const PERMISO_APROBAR = 'finanzas.rendicion.aprobar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarRendiciones $listarRendiciones): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $baseId = $request->integer('base_id') ?: null;
        $estado = EstadoRendicion::tryFrom($request->string('estado')->toString())?->value;

        $rendiciones = $listarRendiciones->ejecutar($baseId, $estado);

        return view('finanzas::pages.rendiciones.index', [
            ...$this->autorizacion->cascara($request),
            'rendiciones' => $rendiciones,
            'resumen' => $listarRendiciones->resumen($baseId, $estado),
            'basesDisponibles' => $this->basesDisponibles(),
            'etiquetasBase' => $this->etiquetasBase($rendiciones->pluck('base_id')->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'etiquetasJefeCampo' => $this->etiquetasPersona($rendiciones->pluck('jefe_campo_id')->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'filtros' => ['base_id' => $baseId, 'estado' => $estado],
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'personaId' => $this->autorizacion->personaId($request),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'puedePresentar' => $this->autorizacion->tienePermiso($request, self::PERMISO_PRESENTAR),
            'puedeAprobar' => $this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('finanzas::pages.rendiciones.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'personasDisponibles' => $this->personasDisponibles(),
        ]);
    }

    public function store(CrearRendicionRequest $request, CrearRendicion $crearRendicion): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $crearRendicion->ejecutar(
            (int) $datos['base_id'],
            (int) $datos['jefe_campo_id'],
            (string) $datos['fecha'],
            isset($datos['descripcion']) && $datos['descripcion'] !== '' ? (string) $datos['descripcion'] : null,
        );

        // Al listado, no al detalle (tarea 119): una rendición no tiene ficha
        // de edición a la que quedarse, así que el alta vuelve a la pantalla
        // desde donde se la pidió, con su aviso — guía §6.3.2. La nueva queda
        // primera en la tabla (orden por fecha) con su acción «Ver» para
        // seguir asociándole gastos.
        return redirect()
            ->route('panel.rendiciones.index')
            ->with('estado', __('finanzas.rendiciones.creada'));
    }

    public function show(Request $request, Rendicion $rendicion): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $gastosAsociados = $rendicion->gastos()->with('rubro')->orderByDesc('fecha')->get();

        $gastosDisponibles = $rendicion->estado === EstadoRendicion::Abierta
            ? Gasto::query()
                ->with('rubro')
                ->whereNull('rendicion_id')
                ->where('base_id', $rendicion->base_id)
                ->orderByDesc('fecha')
                ->limit(100)
                ->get()
            : new Collection;

        return view('finanzas::pages.rendiciones.show', [
            ...$this->autorizacion->cascara($request),
            'rendicion' => $rendicion,
            'gastosAsociados' => $gastosAsociados,
            'gastosDisponibles' => $gastosDisponibles,
            'etiquetasBase' => $this->etiquetasBase([(int) $rendicion->base_id]),
            'etiquetasJefeCampo' => $this->etiquetasPersona([(int) $rendicion->jefe_campo_id]),
            'personaId' => $this->autorizacion->personaId($request),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'puedePresentar' => $this->autorizacion->tienePermiso($request, self::PERMISO_PRESENTAR),
            'puedeAprobar' => $this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR),
        ]);
    }

    public function asociarGasto(Request $request, Rendicion $rendicion, Gasto $gasto, AsociarGastoARendicion $asociarGasto): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        try {
            $asociarGasto->ejecutar($gasto, $rendicion);
        } catch (GastoYaAsociadoARendicion|RendicionNoAceptaGastos $excepcion) {
            return redirect()
                ->route('panel.rendiciones.show', $rendicion)
                ->withErrors(['gasto' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.rendiciones.show', $rendicion)
            ->with('estado', __('finanzas.rendiciones.gasto_asociado'));
    }

    public function presentar(Request $request, Rendicion $rendicion, PresentarRendicion $presentarRendicion): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_PRESENTAR), 403);

        try {
            $presentarRendicion->ejecutar($rendicion);
        } catch (RendicionNoPresentable $excepcion) {
            return redirect()
                ->route('panel.rendiciones.show', $rendicion)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.rendiciones.show', $rendicion)
            ->with('estado', __('finanzas.rendiciones.presentada'));
    }

    public function aprobar(Request $request, Rendicion $rendicion, AprobarRendicion $aprobarRendicion): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR), 403);

        try {
            $aprobarRendicion->ejecutar($rendicion, $this->personaIdONoAutorizado($request));
        } catch (RendicionNoAprobable $excepcion) {
            return redirect()
                ->route('panel.rendiciones.show', $rendicion)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.rendiciones.show', $rendicion)
            ->with('estado', __('finanzas.rendiciones.aprobada'));
    }

    /**
     * Fail-closed (mismo criterio que `ValidacionSesionesController`): un
     * usuario de panel sin `persona_id` asociada no puede aprobar nada —
     * `abort(403)` en vez de dejar pasar un `null` que la policy comparara
     * con laxitud.
     */
    private function personaIdONoAutorizado(Request $request): int
    {
        $personaId = $this->autorizacion->personaId($request);

        abort_if($personaId === null, 403);

        return $personaId;
    }

    /** @return Collection<int, string> */
    private function basesDisponibles(): Collection
    {
        return DB::table('per_bases')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->mapWithKeys(fn (string $nombre, int|string $id): array => [(int) $id => $nombre]);
    }

    /** @return Collection<int, string> */
    private function personasDisponibles(): Collection
    {
        return DB::table('per_personas')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->mapWithKeys(fn (string $nombre, int|string $id): array => [(int) $id => $nombre]);
    }

    /**
     * Etiquetas legibles de base, mismo criterio de lectura directa por
     * `DB::table` que `AnticiposController::etiquetasPersona()` (ADR 0003
     * regla 3).
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasBase(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_bases')
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasPersona(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_personas')
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }
}
