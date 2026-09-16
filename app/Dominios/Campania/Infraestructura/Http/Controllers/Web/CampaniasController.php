<?php

namespace App\Dominios\Campania\Infraestructura\Http\Controllers\Web;

use App\Dominios\Campania\Aplicacion\ActualizarCampania;
use App\Dominios\Campania\Aplicacion\CambiarEstadoCampania;
use App\Dominios\Campania\Aplicacion\CrearCampania;
use App\Dominios\Campania\Aplicacion\ListarCampanias;
use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\Excepciones\CampaniaDuplicada;
use App\Dominios\Campania\Dominio\Excepciones\TransicionCampaniaNoPermitida;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Campania\Infraestructura\Http\Requests\ActualizarCampaniaRequest;
use App\Dominios\Campania\Infraestructura\Http\Requests\CambiarEstadoCampaniaRequest;
use App\Dominios\Campania\Infraestructura\Http\Requests\CrearCampaniaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT /panel/campanias*` (ADR 0015 punto 1, tarea 69): alta y
 * mantenimiento del catálogo de campañas — compartido entre clientes desde
 * la corrección del 15/9/2026. Mismo molde que `ContratosController`: sin
 * `destroy` (la baja es una transición de estado hacia `cerrada`, no un soft
 * delete fuera de la máquina de estados — invariante 7), sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`campania.campania.ver`/`.crear`/`.editar`/`.cambiar_estado`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}.
 * `.cambiar_estado` es exclusivo del rol `dueno` en `SeguridadSeeder` — "solo
 * el dueño cierra una campaña" (ADR 0015, prompt de la tarea 69), y ahora
 * cierra la campaña para TODOS los clientes que la usan, no solo para uno.
 * Ninguna regla de negocio acá: los casos de uso de `Aplicacion/` hacen el
 * trabajo.
 */
final class CampaniasController
{
    private const PERMISO_VER = 'campania.campania.ver';

    private const PERMISO_CREAR = 'campania.campania.crear';

    private const PERMISO_EDITAR = 'campania.campania.editar';

    private const PERMISO_CAMBIAR_ESTADO = 'campania.campania.cambiar_estado';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarCampanias $listarCampanias): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        $campanias = $listarCampanias->ejecutar($busqueda !== '' ? $busqueda : null);

        return view('campania::pages.campanias.index', [
            ...$this->autorizacion->cascara($request),
            'campanias' => $campanias,
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('campania::pages.campanias.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearCampaniaRequest $request, CrearCampania $crearCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearCampania->ejecutar(
                (string) $datos['codigo'],
                $this->cadenaONull($datos['nombre'] ?? null),
                (string) $datos['fecha_inicio'],
                (string) $datos['fecha_fin'],
                (string) $datos['estacion'],
            );
        } catch (CampaniaDuplicada $excepcion) {
            return redirect()
                ->route('panel.campanias.create')
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campanias.index')
            ->with('estado', __('campania.campanias.creado'));
    }

    public function edit(Request $request, Campania $campania): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('campania::pages.campanias.edit', [
            ...$this->autorizacion->cascara($request),
            'campania' => $campania,
            'resumenCampania' => $this->resumenCampania($campania),
        ]);
    }

    public function update(ActualizarCampaniaRequest $request, Campania $campania, ActualizarCampania $actualizarCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarCampania->ejecutar(
                $campania,
                (string) $datos['codigo'],
                $this->cadenaONull($datos['nombre'] ?? null),
                (string) $datos['fecha_inicio'],
                (string) $datos['fecha_fin'],
                (string) $datos['estacion'],
            );
        } catch (CampaniaDuplicada $excepcion) {
            return redirect()
                ->route('panel.campanias.edit', $campania)
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campanias.index')
            ->with('estado', __('campania.campanias.actualizado'));
    }

    public function cambiarEstado(CambiarEstadoCampaniaRequest $request, Campania $campania, CambiarEstadoCampania $cambiarEstadoCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CAMBIAR_ESTADO), 403);

        $hacia = EstadoCampania::from((string) $request->validated('estado'));

        try {
            $cambiarEstadoCampania->ejecutar($campania, $hacia);
        } catch (TransicionCampaniaNoPermitida $excepcion) {
            return redirect()
                ->route('panel.campanias.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campanias.index')
            ->with('estado', __('campania.campanias.estado_cambiado'));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /**
     * Resumen financiero y de trabajo de la campaña, para el aside de
     * `edit.blade.php` (§6.3.1 de docs/diseno/guia_pantalla_panel.md,
     * pedido directo del 15/9/2026: "campaña va más con la parte financiera
     * — cuándo se recaudó, cuánto se gastó — otro vínculo es cuánto trabajo
     * se realizó, cuyo nexo son los contratos"). A diferencia de
     * `ClientesController::resumenRelacionado()`, estas dos tarjetas no
     * alternan con `empty-state`: son magnitudes que siempre tienen un
     * valor (aunque sea cero), no un listado de registros con atajo de
     * alta — por eso el shape es más chico (sin `tieneDatos`; `accion` acá
     * es "ver detalle" en el listado real, no "crear").
     *
     * Todo por `DB::table` directo (ADR 0003 regla 3: referencias por ID
     * sí, lógica cruzada no) — nunca reconstruyendo una regla de negocio de
     * otro módulo. Por eso "trabajo realizado" es una CUENTA de filas de
     * `ope_trabajos` vía la cadena de FKs contrato→orden→trabajo, no una
     * suma de hectáreas filtrada por algún estado de validación: esa regla
     * (qué cuenta como "aplicado") es de `Operaciones`/`Comercial`
     * (`ObtenerAvanceComercial`, `Aplicacion/` ajeno) y no se puede invocar
     * desde acá sin una frontera `Contratos/` propia — pendiente si hace
     * falta más precisión que un conteo.
     *
     * Sumas en `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md), nunca
     * `SUM()` de SQL ni cast a float durante el cálculo — el cast a float
     * (`aMoneda()`) es solo para `number_format()`, mismo criterio de
     * presentación que ya usa `contratos/index.blade.php`.
     *
     * Color y acción de "ver detalle" (pedido directo del 15/9/2026, mismo
     * criterio que la tarjeta "Suscripción" de `panel/organizacion`): el
     * balance y el conteo de contratos van con `variant` (success/danger/
     * neutral, nunca ámbar — regla fija de `sistema_diseno_panel.md` §8)
     * SIN `badge` — texto plano coloreado (`molecules/summary-card`,
     * corregido el mismo día), no la píldora: un badge tiene padding propio
     * que corre el dígito a la izquierda del resto de la columna de cifras
     * de la tarjeta, aunque la píldora en sí calce con el borde derecho
     * (bug real, visto en el panel andando). Cada tarjeta enlaza al listado
     * real ya filtrable por `campania_id` (`GastosController`/
     * `ContratosController` — `panel.facturas.index` NO tiene ese filtro
     * todavía, así que "Recaudado" queda sin acción propia).
     *
     * @return list<array{titulo: string, items: list<array{label: string, value: string, mono?: bool, badge?: bool, variant?: string}>, accion: array{label: string, href: string}}>
     */
    private function resumenCampania(Campania $campania): array
    {
        $montoFacturado = $this->sumarDecimal(
            DB::table('com_facturas')
                ->join('com_contratos', 'com_contratos.id', '=', 'com_facturas.contrato_id')
                ->where('com_contratos.campania_id', $campania->id)
                ->whereNull('com_facturas.deleted_at')
                ->whereNull('com_contratos.deleted_at')
                ->pluck('com_facturas.monto'),
        );

        $montoGastado = $this->sumarDecimal(
            DB::table('fin_gastos')->where('campania_id', $campania->id)->whereNull('deleted_at')->pluck('monto'),
        )->plus($this->sumarDecimal(
            DB::table('fin_combustibles')->where('campania_id', $campania->id)->whereNull('deleted_at')->pluck('monto'),
        ));

        $balance = $montoFacturado->minus($montoGastado);

        $contratosCampania = DB::table('com_contratos')->where('campania_id', $campania->id)->whereNull('deleted_at');
        $totalContratos = (clone $contratosCampania)->count();
        $hectareasContratadas = $this->sumarDecimal((clone $contratosCampania)->pluck('hectareas_contratadas'));

        $totalTrabajos = DB::table('ope_trabajos')
            ->join('ope_ordenes_aplicacion', 'ope_ordenes_aplicacion.id', '=', 'ope_trabajos.orden_id')
            ->join('com_contratos', 'com_contratos.id', '=', 'ope_ordenes_aplicacion.contrato_id')
            ->where('com_contratos.campania_id', $campania->id)
            ->whereNull('ope_trabajos.deleted_at')
            ->whereNull('ope_ordenes_aplicacion.deleted_at')
            ->whereNull('com_contratos.deleted_at')
            ->count();

        return [
            [
                'titulo' => __('campania.campanias.aside_financiero_titulo'),
                'items' => [
                    ['label' => __('campania.campanias.aside_recaudado'), 'value' => $this->aMoneda($montoFacturado), 'mono' => true],
                    ['label' => __('campania.campanias.aside_gastado'), 'value' => $this->aMoneda($montoGastado), 'mono' => true],
                    [
                        'label' => __('campania.campanias.aside_balance'),
                        'value' => $this->aMoneda($balance),
                        'mono' => true,
                        'variant' => $balance->isNegative() ? 'danger' : ($balance->isZero() ? 'neutral' : 'success'),
                    ],
                ],
                'accion' => [
                    'label' => __('campania.campanias.aside_financiero_accion'),
                    'href' => route('panel.gastos.index', ['campania_id' => $campania->id]),
                ],
            ],
            [
                'titulo' => __('campania.campanias.aside_trabajo_titulo'),
                'items' => [
                    [
                        'label' => __('campania.campanias.aside_contratos'),
                        'value' => (string) $totalContratos,
                        'mono' => true,
                        'variant' => $totalContratos > 0 ? 'success' : 'neutral',
                    ],
                    ['label' => __('campania.campanias.aside_hectareas_contratadas'), 'value' => $this->aMoneda($hectareasContratadas), 'mono' => true],
                    ['label' => __('campania.campanias.aside_trabajos'), 'value' => (string) $totalTrabajos, 'mono' => true],
                ],
                'accion' => [
                    'label' => __('campania.campanias.aside_trabajo_accion'),
                    'href' => route('panel.contratos.index', ['campania_id' => $campania->id]),
                ],
            ],
        ];
    }

    /** @param  Collection<int, string>  $valores */
    private function sumarDecimal(Collection $valores): BigDecimal
    {
        return $valores->reduce(fn (BigDecimal $acumulado, string $valor) => $acumulado->plus($valor), BigDecimal::zero());
    }

    private function aMoneda(BigDecimal $valor): string
    {
        return number_format((float) (string) $valor, 2, ',', '.');
    }
}
