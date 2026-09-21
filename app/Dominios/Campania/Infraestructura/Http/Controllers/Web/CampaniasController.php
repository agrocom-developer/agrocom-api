<?php

namespace App\Dominios\Campania\Infraestructura\Http\Controllers\Web;

use App\Dominios\Campania\Aplicacion\ActualizarCampania;
use App\Dominios\Campania\Aplicacion\CambiarEstadoCampania;
use App\Dominios\Campania\Aplicacion\CrearCampania;
use App\Dominios\Campania\Aplicacion\EliminarCampania;
use App\Dominios\Campania\Aplicacion\ListarCampanias;
use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\Excepciones\CampaniaDuplicada;
use App\Dominios\Campania\Dominio\Excepciones\TransicionCampaniaNoPermitida;
use App\Dominios\Campania\Dominio\MaquinaEstados\TransicionesCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Campania\Infraestructura\Http\Requests\ActualizarCampaniaRequest;
use App\Dominios\Campania\Infraestructura\Http\Requests\CambiarEstadoCampaniaRequest;
use App\Dominios\Campania\Infraestructura\Http\Requests\CrearCampaniaRequest;
use App\Dominios\Comercial\Contratos\LecturaResumenComercialCampania;
use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;
use App\Dominios\Finanzas\Contratos\LecturaGastoPorCampania;
use App\Dominios\Operaciones\Contratos\LecturaTrabajosPorContrato;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/campanias*` (ADR 0015 punto 1, tarea 69): alta y
 * mantenimiento del catálogo de campañas — compartido entre clientes desde
 * la corrección del 15/9/2026. Cinco permisos de grano fino
 * (`campania.campania.ver`/`.crear`/`.editar`/`.cambiar_estado`/`.eliminar`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb}. `.cambiar_estado` es exclusivo del rol `dueno`
 * en `SeguridadSeeder` — "solo el dueño cierra una campaña" (ADR 0015, prompt
 * de la tarea 69), y ahora cierra la campaña para TODOS los clientes que la
 * usan, no solo para uno. `.eliminar` es soft delete sin guarda de "tiene
 * contratos asociados", mismo criterio que `comercial.cultivo.eliminar`.
 * Ninguna regla de negocio acá: los casos de uso de `Aplicacion/` hacen el
 * trabajo.
 */
final class CampaniasController
{
    private const PERMISO_VER = 'campania.campania.ver';

    private const PERMISO_CREAR = 'campania.campania.crear';

    private const PERMISO_EDITAR = 'campania.campania.editar';

    private const PERMISO_CAMBIAR_ESTADO = 'campania.campania.cambiar_estado';

    private const PERMISO_ELIMINAR = 'campania.campania.eliminar';

    /**
     * Tono de cada estado (mismo valor que `atoms/badge`): lo comparten el
     * badge de la columna "Estado" del listado y el paso de
     * `molecules/step-arrow` de la ficha de edición, para que los dos hablen
     * con el mismo color.
     *
     * 'cerrada' en alert/rojo-700 "#880000" (18/9/2026, novena vuelta — se
     * probó distintivo-2/magenta primero, el usuario lo corrigió: había
     * confundido "magenta" con este tono). Sigue distinto de 'danger' (rojo-600
     * "#bb0000", el botón "Eliminar") a propósito: cerrar un ciclo de negocio
     * no es lo mismo que borrar el registro, pero ambos son rojos — más oscuro
     * el de "alert".
     */
    private const array TONO_POR_ESTADO = [
        'planificada' => 'neutral',
        'abierta' => 'success',
        'cerrada' => 'alert',
    ];

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarCampanias $listarCampanias): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $estado = $request->string('estado')->toString() ?: null;
        $estacion = $request->string('estacion')->toString() ?: null;

        $campanias = $listarCampanias->ejecutar($busqueda !== '' ? $busqueda : null, $estado, $estacion);

        return view('campania::pages.campanias.index', [
            ...$this->autorizacion->cascara($request),
            'campanias' => $campanias,
            'estadosFiltro' => EstadoCampania::cases(),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'filtros' => ['q' => $busqueda, 'estado' => $estado, 'estacion' => $estacion],
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
            $campania = $crearCampania->ejecutar(
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

        // Se queda en la ficha de edición de la campaña recién creada (no
        // vuelve al listado), mismo criterio que ClientesController::store():
        // ahí vive el resumen financiero/de trabajo (resumenCampania()), y
        // volver al listado para entrar de nuevo es un clic de más siempre.
        return redirect()
            ->route('panel.campanias.edit', $campania)
            ->with('estado', __('campania.campanias.creado'));
    }

    public function edit(
        Request $request,
        Campania $campania,
        LecturaResumenComercialCampania $lecturaComercial,
        LecturaGastoPorCampania $lecturaGasto,
        LecturaTrabajosPorContrato $lecturaTrabajos,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $pasosEstado = PasosDeEstado::armar(
            ruta: [EstadoCampania::Planificada, EstadoCampania::Abierta, EstadoCampania::Cerrada],
            actual: $campania->estado,
            permitida: TransicionesCampania::permitida(...),
            tonos: self::TONO_POR_ESTADO,
            claveEtiqueta: 'campania.campania.estado',
            prefijoModal: 'campania-estado-modal',
            puedeCambiar: $this->autorizacion->tienePermiso($request, self::PERMISO_CAMBIAR_ESTADO),
        );

        return view('campania::pages.campanias.edit', [
            ...$this->autorizacion->cascara($request),
            'campania' => $campania,
            'pasosEstado' => $pasosEstado,
            'ayudaEstado' => PasosDeEstado::ayuda($pasosEstado, 'campania.campania.estado_ayuda'),
            // `null` mientras la campaña sigue `planificada`: todavía no
            // admite contratos ni gastos (invariante de negocio, no falta de
            // datos), así que el resumen financiero/de trabajo no tiene nada
            // real que mostrar — el formulario lo cambia por un empty-state.
            'resumenCampania' => $campania->estado === EstadoCampania::Planificada
                ? null
                : $this->resumenCampania($campania, $lecturaComercial, $lecturaGasto, $lecturaTrabajos),
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

        // Se queda en la propia ficha de edición (no vuelve al listado),
        // mismo criterio que ClientesController::update(): ahí vive el
        // resumen financiero/de trabajo, y de ahí es más común seguir
        // editando o encadenar una acción relacionada que volver al listado.
        return redirect()
            ->route('panel.campanias.edit', $campania)
            ->with('estado', __('campania.campanias.actualizado'));
    }

    public function cambiarEstado(CambiarEstadoCampaniaRequest $request, Campania $campania, CambiarEstadoCampania $cambiarEstadoCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CAMBIAR_ESTADO), 403);

        $hacia = EstadoCampania::from((string) $request->validated('estado'));

        // Vuelve a la pantalla de la que vino — el listado (con sus filtros) o
        // los pasos de la ficha de edición — en vez de mandar siempre al
        // listado: cambiar el estado desde el formulario no debería sacarte de él.
        try {
            $cambiarEstadoCampania->ejecutar($campania, $hacia);
        } catch (TransicionCampaniaNoPermitida $excepcion) {
            return redirect()
                ->back(fallback: route('panel.campanias.index'))
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->back(fallback: route('panel.campanias.index'))
            ->with('estado', __('campania.campanias.estado_cambiado'));
    }

    public function destroy(Request $request, Campania $campania, EliminarCampania $eliminarCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarCampania->ejecutar($campania);

        return redirect()
            ->route('panel.campanias.index')
            ->with('estado', __('campania.campanias.eliminada'));
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
     * Cross-módulo vía `Contratos/` (ADR 0003 regla 2), corregido el
     * 16/9/2026 — la versión original de esta tarea hacía `DB::table`
     * directo sobre `com_facturas`/`com_contratos`/`fin_gastos`/
     * `fin_combustibles`/`ope_trabajos`/`ope_ordenes_aplicacion` con joins
     * crudos entre tablas de tres módulos ajenos: pasaba el arch test
     * (`ArquitecturaModulosTest`) porque `DB::table` no deja rastro de
     * `Node\Name` para el analizador AST, pero violaba la frontera igual.
     * Ahora {@see LecturaResumenComercialCampania} (Comercial: facturado,
     * contratos, hectáreas contratadas y los `contratoIds` que necesita
     * Operaciones), {@see LecturaGastoPorCampania} (Finanzas: gasto +
     * combustible) y {@see LecturaTrabajosPorContrato} (Operaciones: cuenta
     * de trabajos por la cadena contrato→orden→trabajo, resuelta DENTRO de
     * Operaciones) hacen cada una lo suyo sin que Campania conozca sus
     * tablas ni modelos Eloquent. "Trabajo realizado" sigue siendo una
     * CUENTA de filas, no una suma de hectáreas filtrada por validación: esa
     * regla es de `Operaciones`/`Comercial` (`ObtenerAvanceComercial`) y no
     * se invoca desde acá sin ampliar esa frontera — pendiente si hace falta
     * más precisión que un conteo.
     *
     * Sumas en `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md) — cada
     * contrato ya devuelve sus montos como `string` decimal, nunca `float`;
     * el cast a float (`aMoneda()`) es solo para `number_format()`, mismo
     * criterio de presentación que ya usa `contratos/index.blade.php`.
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
    private function resumenCampania(
        Campania $campania,
        LecturaResumenComercialCampania $lecturaComercial,
        LecturaGastoPorCampania $lecturaGasto,
        LecturaTrabajosPorContrato $lecturaTrabajos,
    ): array {
        $resumenComercial = $lecturaComercial->resumen($campania->id);
        $montoFacturado = BigDecimal::of($resumenComercial->facturado);
        $montoGastado = BigDecimal::of($lecturaGasto->totalGastado($campania->id));
        $balance = $montoFacturado->minus($montoGastado);

        $totalContratos = $resumenComercial->totalContratos;
        $hectareasContratadas = BigDecimal::of($resumenComercial->hectareasContratadas);
        $totalTrabajos = $lecturaTrabajos->total($resumenComercial->contratoIds);

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

    private function aMoneda(BigDecimal $valor): string
    {
        return number_format((float) (string) $valor, 2, ',', '.');
    }
}
