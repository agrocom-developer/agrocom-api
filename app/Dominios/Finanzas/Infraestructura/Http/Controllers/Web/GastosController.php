<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Aplicacion\CrearGasto;
use App\Dominios\Finanzas\Aplicacion\EliminarGasto;
use App\Dominios\Finanzas\Aplicacion\ListarGastos;
use App\Dominios\Finanzas\Dominio\Excepciones\CampaniaCerrada;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\CrearGastoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * `GET/POST/DELETE /panel/gastos*` (HU-33, tarea 47): "como encargado,
 * quiero cargar gastos con su categoría y comprobante, para que la campaña
 * tenga costo real". Mismo molde que `AnticiposController` — ABM acotado sin
 * edición: alta, listado y baja lógica.
 *
 * Tres permisos de grano fino (`finanzas.gasto.ver`/`.crear`/`.eliminar`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que el resto del panel.
 * Ninguna regla de negocio acá: el cálculo de `monto` y el guardado del
 * comprobante los hace `Aplicacion/CrearGasto`.
 *
 * Los selects de `base_id`/`trabajo_id` se arman con `DB::table` directo
 * (ADR 0003 regla 3, mismo criterio que
 * `AnticiposController::personasDisponibles()`), sin importar los modelos
 * Eloquent de `Personal`/`Operaciones`. `rubro`/`subrubro` sí usan el modelo
 * Eloquent: son del propio módulo `Finanzas`.
 */
final class GastosController
{
    private const PERMISO_VER = 'finanzas.gasto.ver';

    private const PERMISO_CREAR = 'finanzas.gasto.crear';

    private const PERMISO_ELIMINAR = 'finanzas.gasto.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarGastos $listarGastos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $rubroId = $request->integer('rubro_id') ?: null;
        $baseId = $request->integer('base_id') ?: null;
        $trabajoId = $request->integer('trabajo_id') ?: null;
        $equipoTrabajoId = $request->integer('equipo_trabajo_id') ?: null;
        $campaniaId = $request->integer('campania_id') ?: null;
        $periodo = $request->string('periodo')->toString();
        $periodoFiltro = $periodo !== '' ? $periodo : null;

        $gastos = $listarGastos->ejecutar($rubroId, $baseId, $trabajoId, $periodoFiltro, $equipoTrabajoId, $campaniaId);
        $rubrosDisponibles = $this->rubrosDisponibles();
        $basesDisponibles = $this->basesDisponibles();
        $equiposDisponibles = $this->equiposDisponibles();

        return view('finanzas::pages.gastos.index', [
            ...$this->autorizacion->cascara($request),
            'gastos' => $gastos,
            'etiquetasRubro' => $rubrosDisponibles->all(),
            'etiquetasBase' => $basesDisponibles->all(),
            'etiquetasEquipo' => $equiposDisponibles->all(),
            'etiquetasTrabajo' => $this->etiquetasTrabajo($gastos->pluck('trabajo_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'rubrosDisponibles' => $rubrosDisponibles,
            'basesDisponibles' => $basesDisponibles,
            'equiposDisponibles' => $equiposDisponibles,
            'campaniasDisponibles' => $this->todasLasCampanias(),
            'filtros' => [
                'rubro_id' => $rubroId,
                'base_id' => $baseId,
                'trabajo_id' => $trabajoId,
                'equipo_trabajo_id' => $equipoTrabajoId,
                'campania_id' => $campaniaId,
                'periodo' => $periodo,
            ],
            'total' => $equipoTrabajoId !== null
                ? $listarGastos->total($rubroId, $baseId, $trabajoId, $periodoFiltro, $equipoTrabajoId, $campaniaId)
                : null,
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('finanzas::pages.gastos.create', [
            ...$this->autorizacion->cascara($request),
            'rubrosConSubrubros' => Rubro::query()->with('subrubros')->orderBy('nombre')->get(),
            'equiposDisponibles' => $this->equiposDisponibles(),
            'basesDisponibles' => $this->basesDisponibles(),
            'trabajosDisponibles' => $this->trabajosDisponibles(),
            'campaniasDisponibles' => $this->campaniasNoCerradas(),
        ]);
    }

    public function store(CrearGastoRequest $request, CrearGasto $crearGasto): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearGasto->ejecutar(
                fecha: (string) $datos['fecha'],
                rubroId: (int) $datos['rubro_id'],
                subrubroId: isset($datos['subrubro_id']) ? (int) $datos['subrubro_id'] : null,
                cantidad: (string) $datos['cantidad'],
                precioUnitario: (string) $datos['precio_unitario'],
                baseId: isset($datos['base_id']) ? (int) $datos['base_id'] : null,
                trabajoId: isset($datos['trabajo_id']) ? (int) $datos['trabajo_id'] : null,
                campaniaId: isset($datos['campania_id']) ? (int) $datos['campania_id'] : null,
                comprobante: $request->file('comprobante'),
                equipoTrabajoId: isset($datos['equipo_trabajo_id']) ? (int) $datos['equipo_trabajo_id'] : null,
            );
        } catch (CampaniaCerrada $excepcion) {
            return redirect()
                ->route('panel.gastos.create')
                ->withInput()
                ->withErrors(['campania_id' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.gastos.index')
            ->with('estado', __('finanzas.gastos.creado'));
    }

    public function destroy(Request $request, Gasto $gasto, EliminarGasto $eliminarGasto): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarGasto->ejecutar($gasto);

        return redirect()
            ->route('panel.gastos.index')
            ->with('estado', __('finanzas.gastos.eliminado'));
    }

    /**
     * Sirve el comprobante desde el disco `r2` (privado, ADR 0009) — mismo
     * patrón que `PlanillasController::recibo()`, gateado por el mismo
     * permiso de lectura que el listado.
     */
    public function comprobante(Request $request, Gasto $gasto): Response
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        abort_if($gasto->comprobante_url === null || ! Storage::disk('r2')->exists($gasto->comprobante_url), 404);

        return response(Storage::disk('r2')->get($gasto->comprobante_url), 200, [
            'Content-Type' => Storage::disk('r2')->mimeType($gasto->comprobante_url) ?: 'application/octet-stream',
        ]);
    }

    /** @return Collection<int, string> */
    private function rubrosDisponibles(): Collection
    {
        return Rubro::query()
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->mapWithKeys(fn (string $nombre, int|string $id): array => [(int) $id => $nombre]);
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

    /**
     * Equipos de trabajo (tarea 73, HU-50): camino PRINCIPAL de imputación,
     * el formulario lo ofrece antes que base/trabajo. `DB::table` directo
     * (ADR 0003 regla 3): `Personal` es de otro módulo.
     *
     * @return Collection<int, string>
     */
    private function equiposDisponibles(): Collection
    {
        return DB::table('per_equipos_trabajo')
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->mapWithKeys(fn (object $equipo): array => [
                (int) $equipo->id => $equipo->nombre !== null ? "{$equipo->codigo} — {$equipo->nombre}" : $equipo->codigo,
            ]);
    }

    /**
     * Campañas no cerradas (ADR 0015 punto 6, tarea 69): el selector es
     * opcional y se filtra por campañas no `cerrada` — imputar a una
     * cerrada lo rechaza igual `Aplicacion/CrearGasto`, esto es solo para no
     * ofrecerla en el formulario. `DB::table` directo (ADR 0003 regla 3):
     * `Campania` es de otro módulo.
     *
     * @return Collection<int, non-falsy-string>
     */
    private function campaniasNoCerradas(): Collection
    {
        return DB::table('cpn_campanias')
            ->join('com_clientes', 'com_clientes.id', '=', 'cpn_campanias.cliente_id')
            ->whereNull('cpn_campanias.deleted_at')
            ->where('cpn_campanias.estado', '!=', 'cerrada')
            ->orderBy('com_clientes.razon_social')
            ->orderBy('cpn_campanias.codigo')
            ->get(['cpn_campanias.id', 'cpn_campanias.codigo', 'com_clientes.razon_social'])
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->id => sprintf('%s — %s', $fila->codigo, $fila->razon_social)]);
    }

    /**
     * TODAS las campañas (activas), sin filtrar por estado (tarea 73, punto
     * 5: "por campaña, opcional, dentro de un cliente") — a diferencia de
     * `campaniasNoCerradas()` (solo para el formulario de alta), acá el
     * filtro del LISTADO tiene que poder encontrar gastos de una campaña ya
     * `cerrada`: cerrarla no borra su historial de costo.
     *
     * @return Collection<int, non-falsy-string>
     */
    private function todasLasCampanias(): Collection
    {
        return DB::table('cpn_campanias')
            ->join('com_clientes', 'com_clientes.id', '=', 'cpn_campanias.cliente_id')
            ->whereNull('cpn_campanias.deleted_at')
            ->orderBy('com_clientes.razon_social')
            ->orderBy('cpn_campanias.codigo')
            ->get(['cpn_campanias.id', 'cpn_campanias.codigo', 'com_clientes.razon_social'])
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->id => sprintf('%s — %s', $fila->codigo, $fila->razon_social)]);
    }

    /**
     * Últimos 100 trabajos, mismo criterio de acotar el select que un ABM
     * chico sin buscador todavía — el CA esencial es poder imputar a un
     * trabajo, no navegar el historial completo desde este formulario.
     *
     * @return Collection<int, string>
     */
    private function trabajosDisponibles(): Collection
    {
        return DB::table('ope_trabajos')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'lote_id', 'nro_aplicacion'])
            ->mapWithKeys(fn (object $trabajo): array => [
                (int) $trabajo->id => __('finanzas.gastos.trabajo_etiqueta', [
                    'id' => $trabajo->id,
                    'lote' => $trabajo->lote_id,
                    'aplicacion' => $trabajo->nro_aplicacion,
                ]),
            ]);
    }

    /**
     * Etiquetas legibles de trabajo acotadas a la página actual del listado,
     * mismo criterio de lectura directa que `basesDisponibles()` —
     * `AnticiposController::etiquetasPersona()` es el molde.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasTrabajo(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('ope_trabajos')
            ->whereIn('id', $ids)
            ->get(['id', 'lote_id', 'nro_aplicacion'])
            ->mapWithKeys(fn (object $trabajo): array => [
                (int) $trabajo->id => __('finanzas.gastos.trabajo_etiqueta', [
                    'id' => $trabajo->id,
                    'lote' => $trabajo->lote_id,
                    'aplicacion' => $trabajo->nro_aplicacion,
                ]),
            ])
            ->all();
    }
}
