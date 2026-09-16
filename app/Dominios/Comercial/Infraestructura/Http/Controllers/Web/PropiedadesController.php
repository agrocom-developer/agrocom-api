<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarPropiedad;
use App\Dominios\Comercial\Aplicacion\CrearPropiedad;
use App\Dominios\Comercial\Aplicacion\EliminarPropiedad;
use App\Dominios\Comercial\Aplicacion\ListarPropiedades;
use App\Dominios\Comercial\Dominio\ColorPropiedad;
use App\Dominios\Comercial\Dominio\Excepciones\PropiedadConLotesAsociados;
use App\Dominios\Comercial\Dominio\Excepciones\PropiedadDuplicada;
use App\Dominios\Comercial\Dominio\Excepciones\UbicacionGeograficaInconsistente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Departamento;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Municipio;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Eloquent\Provincia;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarPropiedadRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearPropiedadRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/propiedades*` (ADR 0018, revertido por ADR 0020):
 * alta y mantenimiento de propiedades — nivel de terreno entre `Cliente` y
 * `Lote` (directo, sin `Campo` como nivel intermedio). Mismo molde que
 * `LotesController` (tarea 77), sin sub-entidad propia en esta pantalla: los
 * lotes de una propiedad se cargan desde `LotesController`, no acá.
 *
 * Coordenadas del mapa (latitud/longitud/geometría) viven en
 * `PropiedadMapaController`, pantalla aparte (adenda 16/9/2026 a ADR 0018 /
 * ADR 0020).
 *
 * Cuatro permisos de grano fino
 * (`comercial.propiedad.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}.
 * Ninguna regla de negocio acá: los casos de uso de `Aplicacion/` hacen el
 * trabajo.
 */
final class PropiedadesController
{
    private const PERMISO_VER = 'comercial.propiedad.ver';

    private const PERMISO_CREAR = 'comercial.propiedad.crear';

    private const PERMISO_EDITAR = 'comercial.propiedad.editar';

    private const PERMISO_ELIMINAR = 'comercial.propiedad.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarPropiedades $listarPropiedades): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $clienteId = $request->integer('cliente_id') ?: null;
        $departamentoId = $request->integer('departamento_id') ?: null;

        return view('comercial::pages.propiedades.index', [
            ...$this->autorizacion->cascara($request),
            'propiedades' => $listarPropiedades->ejecutar($busqueda !== '' ? $busqueda : null, $clienteId, $departamentoId),
            'filtros' => ['q' => $busqueda, 'cliente_id' => $clienteId, 'departamento_id' => $departamentoId],
            'clientesDisponibles' => $this->clientesActivos(),
            'departamentosDisponibles' => $this->departamentosActivos(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.propiedades.create', [
            ...$this->autorizacion->cascara($request),
            'clientesDisponibles' => $this->clientesActivos(),
            'departamentosDisponibles' => $this->departamentosActivos(),
            'geografia' => $this->geografiaEmbebida(),
            'coloresDisponibles' => $this->coloresDisponibles(),
            // Acceso directo desde el aside de `panel.clientes.edit` (tarea
            // "resumen de cliente"): con ?cliente_id=, el formulario arranca
            // con ese cliente ya elegido — ver _formulario.blade.php.
            'clienteIdPreseleccionado' => $request->integer('cliente_id') ?: null,
            // Alta rápida desde otro formulario (tarea "contratos-lotes",
            // 16/9/2026): con ?volver_a=, al guardar se ofrece un botón para
            // volver a esa URL con esta propiedad ya preseleccionada.
            'volverA' => $request->query('volver_a'),
        ]);
    }

    public function store(CrearPropiedadRequest $request, CrearPropiedad $crearPropiedad): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $propiedad = $crearPropiedad->ejecutar(
                (int) $datos['cliente_id'],
                (string) $datos['nombre'],
                $this->cadenaONull($datos['hectareas'] ?? null),
                $this->enteroONull($datos['departamento_id'] ?? null),
                $this->enteroONull($datos['provincia_id'] ?? null),
                $this->enteroONull($datos['municipio_id'] ?? null),
                $this->cadenaONull($datos['localidad'] ?? null),
                $this->cadenaONull($datos['color'] ?? null),
            );
        } catch (PropiedadDuplicada $excepcion) {
            return redirect()
                ->route('panel.propiedades.create')
                ->withInput()
                ->withErrors(['nombre' => $excepcion->getMessage()]);
        } catch (UbicacionGeograficaInconsistente $excepcion) {
            return redirect()
                ->route('panel.propiedades.create')
                ->withInput()
                ->withErrors(['provincia_id' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::store(): crear
        // o editar un objeto del sistema permanece en su formulario, nunca
        // salta al listado). `volverA` viaja igual que antes para el caso de
        // alta rápida desde otro formulario.
        return redirect()
            ->route('panel.propiedades.edit', $propiedad)
            ->with('estado', __('comercial.propiedades.creado'))
            ->with('volverA', $request->input('volver_a'));
    }

    public function edit(Request $request, Propiedad $propiedad): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.propiedades.edit', [
            ...$this->autorizacion->cascara($request),
            'propiedad' => $propiedad,
            'clientesDisponibles' => $this->clientesActivos(),
            'departamentosDisponibles' => $this->departamentosActivos(),
            'geografia' => $this->geografiaEmbebida(),
            'coloresDisponibles' => $this->coloresDisponibles(),
            'volverA' => session('volverA'),
            'resumenPropiedad' => $this->resumenPropiedad($propiedad, $request),
        ]);
    }

    public function update(ActualizarPropiedadRequest $request, Propiedad $propiedad, ActualizarPropiedad $actualizarPropiedad): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarPropiedad->ejecutar(
                $propiedad,
                (int) $datos['cliente_id'],
                (string) $datos['nombre'],
                $this->cadenaONull($datos['hectareas'] ?? null),
                $this->enteroONull($datos['departamento_id'] ?? null),
                $this->enteroONull($datos['provincia_id'] ?? null),
                $this->enteroONull($datos['municipio_id'] ?? null),
                $this->cadenaONull($datos['localidad'] ?? null),
                $this->cadenaONull($datos['color'] ?? null),
            );
        } catch (PropiedadDuplicada $excepcion) {
            return redirect()
                ->route('panel.propiedades.edit', $propiedad)
                ->withInput()
                ->withErrors(['nombre' => $excepcion->getMessage()]);
        } catch (UbicacionGeograficaInconsistente $excepcion) {
            return redirect()
                ->route('panel.propiedades.edit', $propiedad)
                ->withInput()
                ->withErrors(['provincia_id' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::update()).
        return redirect()
            ->route('panel.propiedades.edit', $propiedad)
            ->with('estado', __('comercial.propiedades.actualizado'));
    }

    public function destroy(Request $request, Propiedad $propiedad, EliminarPropiedad $eliminarPropiedad): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminarPropiedad->ejecutar($propiedad);
        } catch (PropiedadConLotesAsociados $excepcion) {
            return redirect()
                ->route('panel.propiedades.index')
                ->withErrors(['propiedad' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.propiedades.index')
            ->with('estado', __('comercial.propiedades.eliminado'));
    }

    /** @return Collection<int, string> */
    private function clientesActivos(): Collection
    {
        return Cliente::query()->orderBy('razon_social')->pluck('razon_social', 'id');
    }

    /** @return Collection<int, string> */
    private function departamentosActivos(): Collection
    {
        return Departamento::query()->orderBy('nombre')->pluck('nombre', 'id');
    }

    /**
     * Paleta curada para `atoms/color-swatch-picker` (adenda 16/9/2026 a
     * ADR 0018 punto 1) — la vista no conoce `ColorPropiedad`, mismo
     * criterio que `clientesActivos()` con `Cliente`.
     *
     * @return array<string, string> hex => etiqueta, ya traducida.
     */
    private function coloresDisponibles(): array
    {
        return collect(ColorPropiedad::cases())
            ->mapWithKeys(fn (ColorPropiedad $color) => [$color->value => $color->etiqueta()])
            ->all();
    }

    /**
     * Los 3 niveles del catálogo geográfico completo (~460 filas, liviano),
     * embebidos como JSON en la vista — mismo patrón "estrategia embebida"
     * que `ContratosController::propiedadesYLotesPorCliente()`: la cascada
     * de selects la resuelve `propiedades-form.js` en el cliente, sin AJAX.
     *
     * @return array{departamentos: list<array{id: int, nombre: string}>, provincias: list<array{id: int, departamento_id: int, nombre: string}>, municipios: list<array{id: int, provincia_id: int, nombre: string}>}
     */
    private function geografiaEmbebida(): array
    {
        return [
            'departamentos' => Departamento::query()->orderBy('nombre')->get(['id', 'nombre'])->toArray(),
            'provincias' => Provincia::query()->orderBy('nombre')->get(['id', 'departamento_id', 'nombre'])->toArray(),
            'municipios' => Municipio::query()->orderBy('nombre')->get(['id', 'provincia_id', 'nombre'])->toArray(),
        ];
    }

    /**
     * Resumen relacionado del aside (solo edición, §6.3.1 de la guía de
     * pantalla) — mismo molde que
     * `ClientesController::resumenRelacionado()`/`CampaniasController::resumenCampania()`:
     * tres tarjetas, cada una gateada por el permiso `.ver`/`.crear` del
     * módulo AJENO que describe (no el de Propiedad, que ya se verificó
     * arriba para poder estar en esta pantalla).
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, mostrarAccion: bool, accion: array{label: string, href: string}}>
     */
    private function resumenPropiedad(Propiedad $propiedad, Request $request): array
    {
        $resumen = [];

        // 1) Coordenadas del mapa — mismo permiso que esta pantalla
        // (comercial.propiedad.editar), sin gating adicional.
        $tieneCoordenadas = $propiedad->geometria !== null || $propiedad->latitud !== null;
        $vertices = $propiedad->geometria !== null ? $this->contarVertices($propiedad->geometria) : 0;

        $itemsMapa = [];
        if ($propiedad->latitud !== null && $propiedad->longitud !== null) {
            $itemsMapa[] = [
                'label' => __('comercial.propiedades.aside_mapa_referencia'),
                'value' => "{$propiedad->latitud}, {$propiedad->longitud}",
                'mono' => true,
            ];
        }
        if ($vertices > 0) {
            $itemsMapa[] = [
                'label' => __('comercial.propiedades.aside_mapa_vertices'),
                'value' => (string) $vertices,
                'mono' => true,
            ];
        }

        $resumen[] = [
            'titulo' => __('comercial.propiedades.aside_mapa_titulo'),
            'icono' => 'map',
            'tieneDatos' => $tieneCoordenadas,
            'items' => $itemsMapa,
            'vacioTitulo' => __('comercial.propiedades.aside_mapa_vacio_titulo'),
            'vacioDetalle' => __('comercial.propiedades.aside_mapa_vacio_detalle'),
            'mostrarAccion' => true,
            'accion' => [
                'label' => $tieneCoordenadas
                    ? __('comercial.propiedades.aside_mapa_accion_editar')
                    : __('comercial.propiedades.aside_mapa_accion_agregar'),
                'href' => route('panel.propiedades.mapa', $propiedad),
            ],
        ];

        // 2) Lotes — gateado por los permisos de Lote, no de Propiedad.
        $puedeVerLotes = $this->autorizacion->tienePermiso($request, 'comercial.lote.ver');
        $puedeCrearLotes = $this->autorizacion->tienePermiso($request, 'comercial.lote.crear');

        if ($puedeVerLotes || $puedeCrearLotes) {
            $totalLotes = $puedeVerLotes ? $propiedad->lotes()->count() : 0;
            $hectareasTotales = $puedeVerLotes ? (float) $propiedad->lotes()->sum('hectareas') : 0.0;
            $tieneLotes = $puedeVerLotes && $totalLotes > 0;

            $resumen[] = [
                'titulo' => __('comercial.propiedades.aside_lotes_titulo'),
                'icono' => 'grid_view',
                'tieneDatos' => $tieneLotes,
                'items' => [
                    ['label' => __('comercial.propiedades.aside_lotes_total'), 'value' => (string) $totalLotes, 'mono' => true],
                    ['label' => __('comercial.propiedades.aside_lotes_hectareas'), 'value' => number_format($hectareasTotales, 2, ',', '.'), 'mono' => true],
                    // Comparación contra la superficie DECLARADA de la hacienda
                    // completa (pedido directo, 16/9/2026) — solo si ya se
                    // cargó, nunca calculada del polígono del mapa.
                    ...($propiedad->hectareas !== null ? [
                        ['label' => __('comercial.propiedades.aside_lotes_hectareas_propiedad'), 'value' => number_format((float) $propiedad->hectareas, 2, ',', '.'), 'mono' => true],
                    ] : []),
                ],
                'vacioTitulo' => __('comercial.propiedades.aside_lotes_vacio_titulo'),
                'vacioDetalle' => __('comercial.propiedades.aside_lotes_vacio_detalle'),
                // Un solo botón, según haya o no lotes (16/9/2026, pedido
                // directo): con lotes, a la LISTA filtrada por esta
                // propiedad (de ahí "Nuevo lote" ya arrastra el mismo
                // propiedad_id, con cliente resuelto, ver
                // lotes/_formulario.blade.php); sin lotes todavía, directo
                // al generador masivo (CrearLotesMasivo) — es la vía rápida
                // para la primera tanda, no el alta de uno por uno.
                'mostrarAccion' => $tieneLotes ? $puedeVerLotes : $puedeCrearLotes,
                'accion' => $tieneLotes
                    ? [
                        'label' => __('comercial.propiedades.aside_lotes_accion'),
                        'href' => route('panel.lotes.index', ['propiedad_id' => $propiedad->id]),
                    ]
                    : [
                        'label' => __('comercial.propiedades.aside_lotes_generar'),
                        'href' => route('panel.propiedades.lotes.generar', $propiedad),
                    ],
            ];
        }

        // 3) Siembra / cultivo actual — mismo permiso que esta pantalla.
        $resumen[] = $this->resumenSiembra($propiedad);

        return $resumen;
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, mostrarAccion: bool, accion: array{label: string, href: string}} */
    private function resumenSiembra(Propiedad $propiedad): array
    {
        $campaniaVigente = DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->orderByDesc('fecha_inicio')
            ->first(['id', 'codigo']);

        $loteIds = $propiedad->lotes()->pluck('id');
        $lotesTotal = $loteIds->count();

        $siembra = $campaniaVigente !== null && $lotesTotal > 0
            ? LoteCampania::query()
                ->where('campania_id', $campaniaVigente->id)
                ->whereIn('lote_id', $loteIds)
                ->get()
            : new Collection;

        $lotesSembrados = $siembra->count();
        $cultivoPredominanteId = $siembra->groupBy('cultivo_id')->sortByDesc(fn ($grupo) => $grupo->count())->keys()->first();
        $cultivoPredominante = $cultivoPredominanteId !== null
            ? Cultivo::query()->find($cultivoPredominanteId)?->nombre
            : null;

        $items = [];
        if ($campaniaVigente !== null) {
            $items[] = ['label' => __('comercial.propiedades.aside_siembra_campania'), 'value' => $campaniaVigente->codigo, 'mono' => true];
            $items[] = [
                'label' => __('comercial.propiedades.aside_siembra_lotes'),
                'value' => __('comercial.propiedades.aside_siembra_lotes_valor', ['sembrados' => $lotesSembrados, 'total' => $lotesTotal]),
                'mono' => true,
            ];
        }
        if ($cultivoPredominante !== null) {
            $items[] = ['label' => __('comercial.propiedades.aside_siembra_cultivo'), 'value' => $cultivoPredominante];
        }

        return [
            'titulo' => __('comercial.propiedades.aside_siembra_titulo'),
            'icono' => 'eco',
            'tieneDatos' => $lotesSembrados > 0,
            'items' => $items,
            'vacioTitulo' => __('comercial.propiedades.aside_siembra_vacio_titulo'),
            'vacioDetalle' => __('comercial.propiedades.aside_siembra_vacio_detalle'),
            'mostrarAccion' => true,
            'accion' => [
                'label' => __('comercial.propiedades.aside_siembra_accion'),
                'href' => route('panel.propiedades.siembra', $propiedad),
            ],
        ];
    }

    /** @param  array<string, mixed>  $geometria  GeoJSON MultiPolygon */
    private function contarVertices(array $geometria): int
    {
        $coordenadas = $geometria['coordinates'] ?? [];
        $total = 0;

        // MultiPolygon: [ [ [ [lng,lat], ... ] , anillos... ], polígonos... ]
        foreach ($coordenadas as $poligono) {
            foreach ($poligono as $anillo) {
                $total += count($anillo);
            }
        }

        return $total;
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
