<?php

namespace App\Dominios\Inventario\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Inventario\Aplicacion\ListarMovimientosStock;
use App\Dominios\Inventario\Aplicacion\ListarStock;
use App\Dominios\Inventario\Aplicacion\RegistrarMovimientoStock;
use App\Dominios\Inventario\Contratos\Excepciones\StockInsuficiente;
use App\Dominios\Inventario\Dominio\SentidoAjusteInventario;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Http\Requests\RegistrarMovimientoRequest;
use App\Dominios\Personal\Contratos\LecturaPanelPersonal;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET /panel/stock` y `GET/POST /panel/stock/movimientos*` (HU-36, tarea
 * 52): stock agregado por `(repuesto, base)` con alerta de mínimo, y alta de
 * los cuatro tipos de movimiento (`compra`/`salida`/`ajuste`/`traslado`).
 * Sin sub-entidad: el listado de `inv_stock` es de solo lectura desde acá,
 * toda mutación pasa por `RegistrarMovimientoStock` — nunca un `edit`/`destroy`
 * directo sobre una fila de stock (es un agregado derivado, no un ABM).
 *
 * Dos permisos de grano fino (`inventario.movimiento.ver`/`.crear`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que el resto del panel.
 * Ninguna regla de negocio acá: `RegistrarMovimientoStock` hace el trabajo,
 * incluida la guarda de "nunca negativo".
 *
 * Las bases son de `Personal`: el filtro, los selectores del alta y el nombre de
 * la columna «Base» llegan por {@see LecturaPanelPersonal} (ADR 0003, regla 2),
 * sin leer `per_bases` ni importar el modelo `PerBase`.
 *
 * La franja de KPI del listado la resuelve `ListarStock::resumen()` con el
 * mismo filtro que la tabla; el movimiento de stock no se edita ni se borra
 * —es un asiento—, así que la fila no lleva acciones y `store()` vuelve al
 * listado con su aviso (no hay `edit()` al que volver).
 *
 * `movimientos()` (tarea 133) es la mitad de LECTURA que faltaba: el saldo
 * de arriba es un agregado, este es el detalle de cada asiento que lo
 * explica. Mismo permiso `.ver`, misma ausencia de acciones por la misma
 * razón (un asiento no se edita ni se borra) — {@see ListarMovimientosStock}
 * hace la consulta, este método solo resuelve filtros y nombres de base.
 */
final class StockController
{
    private const PERMISO_VER = 'inventario.movimiento.ver';

    private const PERMISO_CREAR = 'inventario.movimiento.crear';

    /**
     * Tono del badge de cada tipo de movimiento (§6.3.4 de la guía de
     * pantalla), lo lee `stock/movimientos/index.blade.php`. Las cuatro son
     * categorías del negocio, no un estado bueno/malo: eje gris↔verde para
     * `salida` (consumo normal) y `compra` (entrada normal), `info` para el
     * ajuste administrativo y `distintivo-1` para el traslado, que no suma
     * ni resta la existencia total. Nunca ámbar: no es una alerta.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_TIPO = [
        'compra' => 'success',
        'salida' => 'neutral',
        'ajuste' => 'info',
        'traslado' => 'distintivo-1',
    ];

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaPanelPersonal $lecturaPersonal,
    ) {}

    public function index(Request $request, ListarStock $listarStock): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $baseQuery = TextoDeFiltro::de($request, 'base_id');
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;
        $textoBusqueda = $busqueda !== '' ? $busqueda : null;

        $stock = $listarStock->ejecutar(busqueda: $textoBusqueda, baseId: $baseId);

        return view('inventario::pages.stock.index', [
            ...$this->autorizacion->cascara($request),
            'stock' => $stock,
            'resumen' => $listarStock->resumen(busqueda: $textoBusqueda, baseId: $baseId),
            'etiquetasBase' => $this->lecturaPersonal->nombresDeBases($stock->pluck('base_id')->unique()->values()->all()),
            'basesDisponibles' => $this->lecturaPersonal->basesDisponibles(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId],
        ]);
    }

    public function movimientos(Request $request, ListarMovimientosStock $listarMovimientos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $cascara = $this->autorizacion->cascara($request);

        $repuestoQuery = TextoDeFiltro::de($request, 'repuesto_id');
        $repuestoId = $repuestoQuery !== '' ? (int) $repuestoQuery : null;
        $baseQuery = TextoDeFiltro::de($request, 'base_id');
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;

        $movimientos = $listarMovimientos->ejecutar(
            repuestoId: $repuestoId,
            baseId: $baseId,
            zonaHoraria: (string) ($cascara['zonaHoraria'] ?? config('app.timezone')),
        );

        $basesIds = $movimientos->getCollection()
            ->flatMap(fn (MovimientoStock $movimiento): array => [$movimiento->base_id, $movimiento->base_destino_id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('inventario::pages.stock.movimientos.index', [
            ...$cascara,
            'movimientos' => $movimientos,
            'etiquetasBase' => $this->lecturaPersonal->nombresDeBases($basesIds),
            'basesDisponibles' => $this->lecturaPersonal->basesDisponibles(),
            'repuestosDisponibles' => $this->repuestosDisponibles(),
            'tonoPorTipo' => self::TONO_POR_TIPO,
            'filtros' => ['repuesto_id' => $repuestoId, 'base_id' => $baseId],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        // `?repuesto_id=` llega desde el resumen de la ficha de un repuesto: solo
        // deja ese repuesto ya elegido, el resto del formulario sigue igual.
        $repuestoIdInicial = $request->integer('repuesto_id');

        return view('inventario::pages.stock.create', [
            ...$this->autorizacion->cascara($request),
            'repuestosDisponibles' => $this->repuestosDisponibles(),
            'basesDisponibles' => $this->lecturaPersonal->basesDisponibles(),
            'tipos' => TipoMovimientoInventario::cases(),
            'sentidos' => SentidoAjusteInventario::cases(),
            'repuestoIdInicial' => $repuestoIdInicial > 0 ? $repuestoIdInicial : null,
        ]);
    }

    public function store(RegistrarMovimientoRequest $request, RegistrarMovimientoStock $registrarMovimiento): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();
        $tipo = TipoMovimientoInventario::from((string) $datos['tipo']);
        $repuesto = Repuesto::query()->findOrFail((int) $datos['repuesto_id']);

        try {
            $registrarMovimiento->ejecutar(
                tipo: $tipo,
                repuesto: $repuesto,
                baseId: (int) $datos['base_id'],
                cantidad: (string) $datos['cantidad'],
                baseDestinoId: $this->enteroONull($datos['base_destino_id'] ?? null),
                sentido: isset($datos['sentido']) && $datos['sentido'] !== '' ? SentidoAjusteInventario::from((string) $datos['sentido']) : null,
                costoUnitario: $this->decimalONull($datos['costo_unitario'] ?? null),
                motivo: $this->textoONull($datos['motivo'] ?? null),
            );
        } catch (StockInsuficiente $excepcion) {
            return redirect()
                ->route('panel.stock.movimientos.create')
                ->withInput()
                ->withErrors(['cantidad' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.stock.index')
            ->with('estado', __('inventario.stock.movimiento_registrado'));
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function decimalONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    private function textoONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /** @return Collection<int, string> */
    private function repuestosDisponibles(): Collection
    {
        return Repuesto::query()
            ->orderBy('codigo')
            ->get()
            ->mapWithKeys(fn (Repuesto $repuesto): array => [$repuesto->id => $this->etiquetaRepuesto($repuesto)]);
    }

    /**
     * Método aparte (en vez de interpolar en línea dentro del `mapWithKeys`
     * de arriba) para que Larastan tipe el resultado como `string` liso: el
     * tipo de retorno declarado ensancha el `non-falsy-string` que
     * infeririía de la interpolación in situ, que no matchea con
     * `Collection<int, string>` (`TValue` no es covariante en esa clase).
     */
    private function etiquetaRepuesto(Repuesto $repuesto): string
    {
        return "{$repuesto->codigo} — {$repuesto->descripcion}";
    }
}
