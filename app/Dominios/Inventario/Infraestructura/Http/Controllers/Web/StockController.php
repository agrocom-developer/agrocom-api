<?php

namespace App\Dominios\Inventario\Infraestructura\Http\Controllers\Web;

use App\Dominios\Inventario\Aplicacion\ListarStock;
use App\Dominios\Inventario\Aplicacion\RegistrarMovimientoStock;
use App\Dominios\Inventario\Contratos\Excepciones\StockInsuficiente;
use App\Dominios\Inventario\Dominio\SentidoAjusteInventario;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
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
 */
final class StockController
{
    private const PERMISO_VER = 'inventario.movimiento.ver';

    private const PERMISO_CREAR = 'inventario.movimiento.crear';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaPanelPersonal $lecturaPersonal,
    ) {}

    public function index(Request $request, ListarStock $listarStock): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $baseQuery = $request->string('base_id')->toString();
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
