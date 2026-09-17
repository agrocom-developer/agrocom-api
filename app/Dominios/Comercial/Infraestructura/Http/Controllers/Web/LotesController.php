<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarLote;
use App\Dominios\Comercial\Aplicacion\CrearLote;
use App\Dominios\Comercial\Aplicacion\EliminarLote;
use App\Dominios\Comercial\Aplicacion\ListarLotes;
use App\Dominios\Comercial\Aplicacion\Lote\GuardadoLote;
use App\Dominios\Comercial\Aplicacion\ResolverProveedorMapa;
use App\Dominios\Comercial\Dominio\Excepciones\LoteConHistorialAsociado;
use App\Dominios\Comercial\Dominio\Excepciones\LoteDuplicado;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarLoteRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearLoteRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/lotes*` (tarea 77, HU-54, etapa 2): ficha
 * propia de un lote — antes solo se podía tocar un lote entrando por su
 * propiedad. Mismo molde que `PropiedadesController`, sin sub-entidad propia
 * en esta pantalla: los lotes de una propiedad se cargan desde aquí, no acá.
 *
 * Cuatro permisos de grano fino
 * (`comercial.lote.ver`/`.crear`/`.editar`/`.eliminar`, sembrados en la
 * etapa 1 de esta tarea), verificados DENTRO del controlador contra el ROL
 * ACTIVO vía {@see AutorizacionPanelWeb}. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo, y el guardado en sí es el
 * MISMO colaborador que usa `CrearLote`/`ActualizarLote`
 * ({@see GuardadoLote}) — no hay dos
 * formas de crear o editar un lote, solo dos puertas de entrada.
 */
final class LotesController
{
    private const PERMISO_VER = 'comercial.lote.ver';

    private const PERMISO_CREAR = 'comercial.lote.crear';

    private const PERMISO_EDITAR = 'comercial.lote.editar';

    private const PERMISO_ELIMINAR = 'comercial.lote.eliminar';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly ResolverProveedorMapa $resolverProveedorMapa,
    ) {}

    public function index(Request $request, ListarLotes $listarLotes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $clienteId = $request->filled('cliente_id') ? $request->integer('cliente_id') : null;
        $propiedadId = $request->filled('propiedad_id') ? $request->integer('propiedad_id') : null;

        return view('comercial::pages.lotes.index', [
            ...$this->autorizacion->cascara($request),
            'lotes' => $listarLotes->ejecutar($clienteId, $propiedadId, $busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda, 'cliente_id' => $clienteId, 'propiedad_id' => $propiedadId],
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.lotes.create', [
            ...$this->autorizacion->cascara($request),
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
            'proveedorMapa' => $this->resolverProveedorMapa->ejecutar(),
            'referenciaMapa' => $this->referenciaMapa(null),
            // Alta rápida desde otro formulario (tarea "contratos-lotes",
            // 16/9/2026): con ?propiedad_id=, arranca con esa propiedad ya
            // elegida; con ?volver_a=, al guardar se ofrece un botón para
            // volver a esa URL con este lote ya preseleccionado.
            'propiedadIdPreseleccionado' => $request->integer('propiedad_id') ?: null,
            'volverA' => $request->query('volver_a'),
        ]);
    }

    public function store(CrearLoteRequest $request, CrearLote $crearLote): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $lote = $crearLote->ejecutar((int) $datos['propiedad_id'], $this->normalizarDatos($datos));
        } catch (LoteDuplicado $excepcion) {
            return redirect()
                ->route('panel.lotes.create')
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::store()).
        // `volverA` viaja igual que antes para el caso de alta rápida desde
        // otro formulario.
        return redirect()
            ->route('panel.lotes.edit', $lote)
            ->with('estado', __('comercial.lotes.creado'))
            ->with('volverA', $request->input('volver_a'));
    }

    public function edit(Request $request, Lote $lote): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.lotes.edit', [
            ...$this->autorizacion->cascara($request),
            'lote' => $lote->load('propiedad'),
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
            'proveedorMapa' => $this->resolverProveedorMapa->ejecutar(),
            'referenciaMapa' => $this->referenciaMapa($lote->id),
            // Lista de UNA tarjeta (16/9/2026): el Blade del aside itera
            // `$resumenLote` igual que `$resumenPropiedad` en Propiedad,
            // que sí puede traer varias — acá alcanza con "Siembra actual",
            // pero el shape tiene que ser lista igual para que el mismo
            // @foreach sirva sin ifs especiales.
            'resumenLote' => [$this->resumenLote($lote)],
            'volverA' => session('volverA'),
        ]);
    }

    public function update(ActualizarLoteRequest $request, Lote $lote, ActualizarLote $actualizarLote): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarLote->ejecutar($lote, (int) $datos['propiedad_id'], $this->normalizarDatos($datos));
        } catch (LoteDuplicado $excepcion) {
            return redirect()
                ->route('panel.lotes.edit', $lote)
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::update()).
        return redirect()
            ->route('panel.lotes.edit', $lote)
            ->with('estado', __('comercial.lotes.actualizado'));
    }

    public function destroy(Request $request, Lote $lote, EliminarLote $eliminarLote): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminarLote->ejecutar($lote);
        } catch (LoteConHistorialAsociado $excepcion) {
            return redirect()
                ->route('panel.lotes.index')
                ->withErrors(['lote' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.lotes.index')
            ->with('estado', __('comercial.lotes.eliminado'));
    }

    /** @return Collection<int, string> */
    private function clientesActivos(): Collection
    {
        return Cliente::query()->orderBy('razon_social')->pluck('razon_social', 'id');
    }

    /**
     * Propiedades activas con su `cliente_id`, para el primer nivel del
     * cascade cliente → propiedad (ADR 0020: eliminó el nivel de campo
     * intermedio).
     *
     * @return Collection<int, Propiedad> id => Propiedad (con `cliente_id`, `nombre`)
     */
    private function propiedadesActivas(): Collection
    {
        return Propiedad::query()
            ->orderBy('nombre')
            ->get(['id', 'cliente_id', 'nombre'])
            ->keyBy('id');
    }

    /**
     * Capas de referencia del editor de mapa (pedido directo del dueño,
     * 16/9/2026): el límite de la propiedad y los lotes YA dibujados de esa
     * misma propiedad, para no "dibujar a ciegas" un lote nuevo — el
     * perímetro de la propiedad es un contenedor visual, nunca una hectárea
     * oficial (ver docblock de `Propiedad`: eso queda para reportes, no para
     * la portada del cliente en el portal, que podría malinterpretar un
     * desvío del polígono dibujado como un cobro de más).
     *
     * Todas las propiedades y todos los lotes con geometría viajan embebidos
     * de una — mismo criterio "estrategia embebida" que el cascade
     * cliente→propiedad de este mismo formulario (`$mapaClientePropiedad`):
     * la cascada es 100% cliente, sin ida y vuelta al servidor cuando se
     * cambia de propiedad en el select.
     *
     * @param  int|null  $loteActualId  se excluye de "lotes ya dibujados" —
     *                                  un lote no es referencia de sí mismo.
     * @return array{propiedades: array<int, array<string, mixed>|null>, lotesPorPropiedad: array<int, list<array<string, mixed>>>}
     */
    private function referenciaMapa(?int $loteActualId): array
    {
        $propiedades = Propiedad::query()->whereNotNull('geometria')->get(['id', 'geometria'])
            ->mapWithKeys(fn (Propiedad $propiedad) => [$propiedad->id => $propiedad->geometria])
            ->all();

        $lotesPorPropiedad = Lote::query()
            ->whereNotNull('geometria')
            ->when($loteActualId !== null, fn ($consulta) => $consulta->where('id', '!=', $loteActualId))
            ->get(['id', 'propiedad_id', 'geometria'])
            ->groupBy('propiedad_id')
            ->map(fn (Collection $lotes) => $lotes->map(fn (Lote $lote) => ['id' => $lote->id, 'geometria' => $lote->geometria])->values()->all())
            ->all();

        return ['propiedades' => $propiedades, 'lotesPorPropiedad' => $lotesPorPropiedad];
    }

    /**
     * Aside "Siembra actual" de la ficha del lote (16/9/2026): mismo shape
     * que `PropiedadesController::resumenSiembra()`, acotado a UN lote en
     * vez de a toda la propiedad — informativo, con acción a la pantalla
     * real de siembra (ADR 0015 punto 4: el cultivo se edita por
     * propiedad×campaña, no acá).
     *
     * @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, mostrarAccion: bool, accion: array{label: string, href: string}}
     */
    private function resumenLote(Lote $lote): array
    {
        // Memento de navegación (17/9/2026): mismo criterio que
        // PropiedadesController::resumenPropiedad() — ver ese comentario.
        $origenNavegacion = ['volver_a' => route('panel.lotes.edit', $lote), 'volver_texto' => $lote->codigo];

        $campaniaVigente = DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->orderByDesc('fecha_inicio')
            ->first(['id', 'codigo']);

        $siembra = $campaniaVigente !== null
            ? LoteCampania::query()
                ->where('lote_id', $lote->id)
                ->where('campania_id', $campaniaVigente->id)
                ->first()
            : null;

        $items = [];
        if ($campaniaVigente !== null) {
            $items[] = ['label' => __('comercial.lotes.aside_siembra_campania'), 'value' => $campaniaVigente->codigo, 'mono' => true];
        }
        if ($siembra !== null) {
            $cultivo = Cultivo::query()->find($siembra->cultivo_id);

            $items[] = [
                'label' => __('comercial.lotes.aside_siembra_cultivo'),
                'value' => $cultivo !== null ? $cultivo->nombre_comun : __('comercial.lotes.aside_siembra_cultivo_desconocido'),
            ];
        }

        return [
            'titulo' => __('comercial.lotes.aside_siembra_titulo'),
            'icono' => 'eco',
            'tieneDatos' => $siembra !== null,
            'items' => $items,
            'vacioTitulo' => __('comercial.lotes.aside_siembra_vacio_titulo'),
            'vacioDetalle' => __('comercial.lotes.aside_siembra_vacio_detalle'),
            'mostrarAccion' => true,
            'accion' => [
                'label' => __('comercial.lotes.aside_siembra_accion'),
                'href' => route('panel.propiedades.siembra', [$lote->propiedad, ...$origenNavegacion]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{codigo: string, hectareas: string, geometria: array<string, mixed>|null, restricciones: string|null, desnivel: string|null, limpieza: string|null}
     */
    private function normalizarDatos(array $datos): array
    {
        $lote = $datos['lote'];

        return [
            'codigo' => (string) $lote['codigo'],
            'hectareas' => (string) $lote['hectareas'],
            'geometria' => $this->decodificarGeometria($lote['geometria'] ?? null),
            'restricciones' => $this->cadenaONull($lote['restricciones'] ?? null),
            'desnivel' => $this->cadenaONull($lote['desnivel'] ?? null),
            'limpieza' => $this->limpiezaDesdeSwitch($lote),
        ];
    }

    /**
     * Combina el switch "¿está limpio?" (`lote.limpio`) con el grado de
     * obstáculos (`lote.grado_obstaculos`) en el único valor que persiste
     * la columna `limpieza` (16/9/2026) — el Form Request ya garantizó que
     * `grado_obstaculos` viene si el switch no está marcado.
     *
     * @param  array<string, mixed>  $lote
     */
    private function limpiezaDesdeSwitch(array $lote): ?string
    {
        if (! empty($lote['limpio'])) {
            return 'limpio';
        }

        return $this->cadenaONull($lote['grado_obstaculos'] ?? null);
    }

    /**
     * El Form Request ya validó que, si viene, es JSON bien formado con la
     * forma mínima de un GeoJSON `Polygon` — acá solo se decodifica, no se
     * revalida.
     *
     * @return array<string, mixed>|null
     */
    private function decodificarGeometria(mixed $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        /** @var array<string, mixed> $decodificado */
        $decodificado = json_decode((string) $valor, true);

        return $decodificado;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
