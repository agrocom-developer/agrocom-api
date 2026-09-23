<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Aplicacion\AprobarPlanilla;
use App\Dominios\Finanzas\Aplicacion\GenerarPlanilla;
use App\Dominios\Finanzas\Aplicacion\ListarPlanillas;
use App\Dominios\Finanzas\Dominio\EstadoPlanilla;
use App\Dominios\Finanzas\Dominio\Excepciones\PlanillaNoAprobable;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
use App\Dominios\Finanzas\Infraestructura\Eloquent\PlanillaDetalle;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\GenerarPlanillaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * `GET/POST /panel/planillas*` (HU-30, tarea 44): planilla del período,
 * generada desde devengos y anticipos, aprobada exclusivamente por el dueño.
 * `critica=si` (CLAUDE.md, "Qué no delegar sin revisión línea por línea":
 * "los listeners que generan dinero (devengos, planilla)").
 *
 * Tres permisos de grano fino (`finanzas.planilla.ver`/`.generar`/
 * `.aprobar`), verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que `AnticiposController`.
 * Ninguna regla de negocio acá: `Aplicacion/GenerarPlanilla` y
 * `Aplicacion/AprobarPlanilla` hacen el trabajo.
 *
 * `index()` (tarea 119) filtra por estado y arma la franja de KPI con
 * `ListarPlanillas::resumen()`, el mismo filtro que la tabla; el listado
 * ofrece por fila la única transición de la máquina (borrador → aprobada),
 * contra la misma ruta `panel.planillas.aprobar` que ya usaba el detalle.
 *
 * `aprobar()` toma el id del usuario autenticado con
 * `$request->user('interno')->id` (mismo guard que
 * `AutorizacionPanelWebSesion`, sin depender de un método propio del
 * contrato: `aprobada_por` es un dato de NEGOCIO de esta pantalla, distinto
 * de `created_by`/`updated_by`, que ya resuelve `RegistraAutoria` solo).
 */
final class PlanillasController
{
    /**
     * Tono de cada estado, definido UNA vez (plan de homogeneización §3.1):
     * lo comparten el badge del listado, la acción de fila que aprueba y su
     * modal, para que los tres hablen con el mismo color. Son los tonos con
     * los que el listado ya venía pintando el badge — no se reeligen.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'borrador' => 'warning',
        'aprobada' => 'success',
    ];

    private const PERMISO_VER = 'finanzas.planilla.ver';

    private const PERMISO_GENERAR = 'finanzas.planilla.generar';

    private const PERMISO_APROBAR = 'finanzas.planilla.aprobar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarPlanillas $listarPlanillas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        // `?estado[]=x` llega como arreglo: `->string()` lo convertiría a texto y
        // rompería con un 500, así que solo se acepta un texto.
        $estadoQuery = $request->query('estado');
        $estado = is_string($estadoQuery) ? EstadoPlanilla::tryFrom($estadoQuery)?->value : null;

        return view('finanzas::pages.planillas.index', [
            ...$this->autorizacion->cascara($request),
            'planillas' => $listarPlanillas->ejecutar($estado),
            'resumen' => $listarPlanillas->resumen($estado),
            'filtros' => ['estado' => $estado],
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'puedeGenerar' => $this->autorizacion->tienePermiso($request, self::PERMISO_GENERAR),
            'puedeAprobar' => $this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR),
        ]);
    }

    /**
     * Arquetipo Detalle (tarea 126, guía §6.4): misma anatomía que
     * `TrabajosController::show()`/`CuadrillasController::show()` —
     * `page-header` + KPI + `form-layout` de solo lectura + aside con
     * metadatos/vínculos/actividad. Aprobar es la única transición de la
     * máquina (`TransicionesPlanilla`), misma guarda que el listado.
     */
    public function show(Request $request, Planilla $planilla): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $detalles = $planilla->detalles()->orderBy('persona_id')->get();

        return view('finanzas::pages.planillas.show', [
            ...$this->autorizacion->cascara($request),
            'planilla' => $planilla,
            'detalles' => $detalles,
            'etiquetasPersona' => $this->etiquetasPersona($detalles->pluck('persona_id')->unique()->values()->all()),
            'generadaPorNombre' => $this->nombreAutor($planilla->created_by),
            'aprobadaPorNombre' => $this->nombreAutor($planilla->aprobada_por),
            'devengosIncluidos' => $this->devengosDelPeriodo($planilla->periodo),
            'vinculos' => $this->vinculosDePlanilla($request),
            'actividad' => $this->actividadDePlanilla($planilla),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'puedeAprobar' => $this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR),
        ]);
    }

    public function store(GenerarPlanillaRequest $request, GenerarPlanilla $generarPlanilla): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_GENERAR), 403);

        $planilla = $generarPlanilla->ejecutar((string) $request->validated('periodo'));

        return redirect()
            ->route('panel.planillas.show', $planilla)
            ->with('estado', __('finanzas.planillas.generada'));
    }

    public function aprobar(Request $request, Planilla $planilla, AprobarPlanilla $aprobarPlanilla): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_APROBAR), 403);

        try {
            $aprobarPlanilla->ejecutar($planilla, (int) $request->user('interno')->id);
        } catch (PlanillaNoAprobable $excepcion) {
            return redirect()
                ->route('panel.planillas.show', $planilla)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.planillas.show', $planilla)
            ->with('estado', __('finanzas.planillas.aprobada'));
    }

    public function recibo(Request $request, Planilla $planilla, PlanillaDetalle $detalle): Response
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);
        abort_if($detalle->planilla_id !== $planilla->id, 404);
        abort_if($detalle->pdf_path === null || ! Storage::disk('r2')->exists($detalle->pdf_path), 404);

        return response(Storage::disk('r2')->get($detalle->pdf_path), 200, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Etiquetas legibles de persona, mismo criterio de lectura directa por
     * `DB::table` que `AnticiposController::etiquetasPersona()` (ADR 0003
     * regla 3).
     *
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
     * Nombre de usuario (`sec_user`, mismo criterio que
     * `CuadrillasController::nombreAutor()`): `created_by`/`aprobada_por` son
     * el mismo tipo de referencia (un `sec_user`, no una `per_personas`).
     * `"#id"` si el usuario ya no existe.
     */
    private function nombreAutor(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return DB::table('sec_user')->where('id', $userId)->value('name') ?? "#{$userId}";
    }

    /**
     * "Devengos incluidos" del KPI: cuenta (no suma dinero) los
     * `fin_devengos_personal` PAGABLES del mismo mes calendario que
     * `GenerarPlanilla` usó para armar esta planilla — mismo rango de fechas,
     * sin recalcular ningún monto (invariante 6: esto no es una suma nueva,
     * es un `count()` de filas ya existentes).
     */
    private function devengosDelPeriodo(string $periodo): int
    {
        $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        return DevengoPersonal::query()
            ->pagables()
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->count();
    }

    /**
     * "Vínculos" de `show()` (arquetipo Detalle): solo el listado de
     * planillas — no hay ruta de "devengos del período" agregada (Devengos
     * es por persona, `panel.devengos.show/{persona}`), así que ese enlace
     * no se ofrece (guía §6.4: nunca se inventa una ruta que no existe).
     *
     * @return list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>
     */
    private function vinculosDePlanilla(Request $request): array
    {
        $vinculos = [];

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_VER)) {
            $vinculos[] = [
                'href' => route('panel.planillas.index'),
                'icon' => 'event_note',
                'title' => __('finanzas.planillas.vinculo_listado'),
                'meta' => null,
                'tone' => 'primary-2',
            ];
        }

        return $vinculos;
    }

    /**
     * "Actividad" de `show()`: solo eventos reconstruibles desde columnas
     * reales (guía §6.4 regla 3) — `created_at`/`created_by` (generada) y
     * `aprobada_en`/`aprobada_por` (aprobada), las dos ya persistidas por
     * `MaquinaEstadosPlanilla`.
     *
     * @return list<array{title: string, meta: string, tone: string}>
     */
    private function actividadDePlanilla(Planilla $planilla): array
    {
        $eventos = [[
            'title' => __('finanzas.planillas.actividad_generada'),
            'meta' => $this->metaFecha($planilla->created_at),
            'tone' => 'neutral',
        ]];

        if ($planilla->aprobada_en !== null) {
            $eventos[] = [
                'title' => __('finanzas.planillas.actividad_aprobada'),
                'meta' => $this->metaFecha($planilla->aprobada_en),
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
