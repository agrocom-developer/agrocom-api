<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Finanzas\Aplicacion\ActualizarRendicion;
use App\Dominios\Finanzas\Aplicacion\AprobarRendicion;
use App\Dominios\Finanzas\Aplicacion\AsociarGastoARendicion;
use App\Dominios\Finanzas\Aplicacion\CrearRendicion;
use App\Dominios\Finanzas\Aplicacion\ListarRendiciones;
use App\Dominios\Finanzas\Aplicacion\PresentarRendicion;
use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\GastoYaAsociadoARendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoAceptaGastos;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoAprobable;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoEditable;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoPresentable;
use App\Dominios\Finanzas\Dominio\PoliticaEdicionRendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\ActualizarRendicionRequest;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\CrearRendicionRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT /panel/rendiciones*` (HU-34, tarea 48; edición de CABECERA
 * agregada en la tarea 134): "como jefe de campo, quiero rendir los gastos
 * que hice en campo; el encargado los aprueba para reponer el fondo".
 * Máquina de estados `abierta → presentada → aprobada` (a diferencia de
 * `AnticiposController`, sin ella) — mismo criterio de controlador delgado
 * que `PlanillasController`/`GastosController`: ninguna regla de negocio
 * acá, todo vive en `Aplicacion/`.
 *
 * Cuatro permisos de grano fino (`finanzas.rendicion.ver`/`.crear`/
 * `.presentar`/`.aprobar`), verificados DENTRO del controlador contra el ROL
 * ACTIVO vía {@see AutorizacionPanelWeb} — mismo criterio que el resto del
 * panel. A diferencia de `finanzas.planilla.aprobar` (exclusivo del dueño),
 * `finanzas.rendicion.aprobar` SÍ lo tiene el encargado (`SeguridadSeeder`):
 * la guarda real de "el aprobador nunca es quien rinde" la resuelve
 * `Aplicacion/AprobarRendicion` vía `PoliticaAprobacionRendicion`, por
 * PERSONA — no el permiso. La edición de cabecera (`edit()`/`update()`)
 * reusa `finanzas.rendicion.presentar` (no existe `.editar`) y solo se
 * admite mientras la rendición sigue `Abierta`
 * (`Dominio/PoliticaEdicionRendicion`) — nunca toca `estado`/`monto`.
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
     * Son los tonos con los que el listado ya venía pintando el badge — no se
     * reeligen: `presentada` y `aprobada` siguen en verde, como en la ficha de
     * detalle, y `abierta` pasa del `secondary` que `atoms/badge` no tiene al
     * `neutral`, que es el gris real del catálogo.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'abierta' => 'neutral',
        'presentada' => 'success',
        'aprobada' => 'success',
    ];

    private const PERMISO_VER = 'finanzas.rendicion.ver';

    private const PERMISO_CREAR = 'finanzas.rendicion.crear';

    private const PERMISO_PRESENTAR = 'finanzas.rendicion.presentar';

    private const PERMISO_APROBAR = 'finanzas.rendicion.aprobar';

    /** Reusado para gatear la edición de cabecera (tarea 134): no existe `finanzas.rendicion.editar`. */
    private const PERMISO_EDITAR = self::PERMISO_PRESENTAR;

    /** Tarea 126: el vínculo "Relacionado" a la base gatea por SU permiso, mismo criterio que `CuadrillasController`. */
    private const PERMISO_VER_BASE = 'personal.base.editar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarRendiciones $listarRendiciones): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $baseId = $request->integer('base_id') ?: null;
        $estadoQuery = TextoDeFiltro::de($request, 'estado');
        $estado = $estadoQuery !== '' ? EstadoRendicion::tryFrom($estadoQuery)?->value : null;

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
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
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
        // desde donde se la pidió, con su aviso — guía §6.3.2. Para seguirle
        // asociando gastos se entra a su detalle con «Ver»; el listado se ordena
        // por fecha, así que la nueva no siempre queda primera.
        return redirect()
            ->route('panel.rendiciones.index')
            ->with('estado', __('finanzas.rendiciones.creada'));
    }

    /**
     * Arquetipo Detalle (tarea 126, guía §6.4): misma anatomía que
     * `PlanillasController::show()` — `page-header` + KPI + `form-layout` de
     * solo lectura + aside con metadatos/vínculos/actividad. Presentar y
     * Aprobar son las dos transiciones de `TransicionesRendicion`, cada una
     * gateada por su propio permiso y (Aprobar) por
     * `PoliticaAprobacionRendicion` vía `personaId`.
     */
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

        $etiquetasJefeCampo = $this->etiquetasPersona([(int) $rendicion->jefe_campo_id, ...($rendicion->aprobado_por !== null ? [(int) $rendicion->aprobado_por] : [])]);

        return view('finanzas::pages.rendiciones.show', [
            ...$this->autorizacion->cascara($request),
            'rendicion' => $rendicion,
            'gastosAsociados' => $gastosAsociados,
            'gastosDisponibles' => $gastosDisponibles,
            'etiquetasBase' => $this->etiquetasBase([(int) $rendicion->base_id]),
            'etiquetasJefeCampo' => $etiquetasJefeCampo,
            'creadaPorNombre' => $this->nombreAutor($rendicion->created_by),
            'personaId' => $this->autorizacion->personaId($request),
            'vinculos' => $this->vinculosDeRendicion($rendicion, $request),
            'actividad' => $this->actividadDeRendicion($rendicion, $etiquetasJefeCampo),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'puedePresentar' => $this->autorizacion->tienePermiso($request, self::PERMISO_PRESENTAR),
            'puedeAprobar' => $this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR),
            'puedeEditarEsta' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR)
                && PoliticaEdicionRendicion::admiteEdicion($rendicion->estado),
        ]);
    }

    /**
     * `GET /panel/rendiciones/{rendicion}/editar` (tarea 134). Mismo criterio
     * de guarda de redirección que `GastosController::edit()`.
     */
    public function edit(Request $request, Rendicion $rendicion): View|RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        if (! PoliticaEdicionRendicion::admiteEdicion($rendicion->estado)) {
            return redirect()
                ->route('panel.rendiciones.show', $rendicion)
                ->withErrors(['estado' => RendicionNoEditable::porEstado($rendicion->id, $rendicion->estado->value)->getMessage()]);
        }

        return view('finanzas::pages.rendiciones.edit', [
            ...$this->autorizacion->cascara($request),
            'rendicion' => $rendicion,
            'basesDisponibles' => $this->basesDisponibles(),
            'personasDisponibles' => $this->personasDisponibles(),
            'gastosAsociadosCount' => $rendicion->gastos()->count(),
        ]);
    }

    public function update(ActualizarRendicionRequest $request, Rendicion $rendicion, ActualizarRendicion $actualizarRendicion): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarRendicion->ejecutar(
                rendicion: $rendicion,
                baseId: (int) $datos['base_id'],
                jefeCampoId: (int) $datos['jefe_campo_id'],
                fecha: (string) $datos['fecha'],
                descripcion: isset($datos['descripcion']) && $datos['descripcion'] !== '' ? (string) $datos['descripcion'] : null,
            );
        } catch (RendicionNoEditable $excepcion) {
            return redirect()
                ->route('panel.rendiciones.show', $rendicion)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.rendiciones.show', $rendicion)
            ->with('estado', __('finanzas.rendiciones.actualizada'));
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

    /**
     * Nombre de usuario (`sec_user`), mismo criterio que
     * `PlanillasController::nombreAutor()`/`CuadrillasController::nombreAutor()`:
     * `created_by` es un `sec_user`, distinto de `jefe_campo_id`/`aprobado_por`
     * (que son `per_personas`). `"#id"` si el usuario ya no existe.
     */
    private function nombreAutor(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return DB::table('sec_user')->where('id', $userId)->value('name') ?? "#{$userId}";
    }

    /**
     * "Vínculos" de `show()` (arquetipo Detalle): las rendiciones de la
     * misma base y la ficha de la base (si el rol puede editarla) — mismo
     * espíritu que `CuadrillasController::vinculosDeCuadrilla()`. La base es
     * un entero plano de `per_bases` (ADR 0003 regla 3): la navegación a su
     * ficha es un `href` directo a la ruta de Personal, sin leer sus datos
     * acá.
     *
     * @return list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>
     */
    private function vinculosDeRendicion(Rendicion $rendicion, Request $request): array
    {
        $vinculos = [];

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_VER)) {
            $vinculos[] = [
                'href' => route('panel.rendiciones.index', ['base_id' => $rendicion->base_id]),
                'icon' => 'receipt_long',
                'title' => __('finanzas.rendiciones.vinculo_listado'),
                'meta' => null,
                'tone' => 'primary-2',
            ];
        }

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_VER_BASE)) {
            $vinculos[] = [
                'href' => route('panel.bases.edit', $rendicion->base_id),
                'icon' => 'holiday_village',
                'title' => __('finanzas.rendiciones.vinculo_base'),
                'meta' => $this->etiquetasBase([(int) $rendicion->base_id])[$rendicion->base_id] ?? null,
                'tone' => 'distintivo-1',
            ];
        }

        return $vinculos;
    }

    /**
     * "Actividad" de `show()`: solo eventos reconstruibles desde columnas
     * reales (guía §6.4 regla 3). `fin_rendiciones` NO persiste una fecha
     * propia de "presentada" (solo `estado`) ni de "aprobada" (solo
     * `aprobado_por`, sin timestamp): mientras la rendición está
     * `Presentada`, `updated_at` SÍ refleja exactamente ese momento (una
     * única transición desde la creación); en cuanto llega a `Aprobada`,
     * `updated_at` pasa a reflejar la aprobación y el momento de la
     * presentación deja de ser reconstruible — no se inventa esa fecha, se
     * omite ese evento intermedio (documentado en runs/126.md).
     *
     * @param  array<int, string>  $etiquetasJefeCampo
     * @return list<array{title: string, meta: string, tone: string}>
     */
    private function actividadDeRendicion(Rendicion $rendicion, array $etiquetasJefeCampo): array
    {
        $eventos = [[
            'title' => __('finanzas.rendiciones.actividad_creada'),
            'meta' => $this->metaFecha($rendicion->created_at),
            'tone' => 'neutral',
        ]];

        if ($rendicion->estado === EstadoRendicion::Presentada) {
            $eventos[] = [
                'title' => __('finanzas.rendiciones.actividad_presentada'),
                'meta' => $this->metaFecha($rendicion->updated_at),
                'tone' => 'info',
            ];
        }

        if ($rendicion->estado === EstadoRendicion::Aprobada) {
            $eventos[] = [
                'title' => __('finanzas.rendiciones.actividad_aprobada', [
                    'persona' => $etiquetasJefeCampo[(int) $rendicion->aprobado_por] ?? "#{$rendicion->aprobado_por}",
                ]),
                'meta' => $this->metaFecha($rendicion->updated_at),
                'tone' => 'success',
            ];
        }

        return $eventos;
    }

    private function metaFecha(?\DateTimeInterface $fecha): string
    {
        return $fecha?->format('d/m/Y H:i') ?? '—';
    }
}
