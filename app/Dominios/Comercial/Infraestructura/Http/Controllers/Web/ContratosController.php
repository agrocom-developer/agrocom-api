<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Campania\Contratos\DatosCampania;
use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Aplicacion\ActualizarContrato;
use App\Dominios\Comercial\Aplicacion\CambiarEstadoContrato;
use App\Dominios\Comercial\Aplicacion\CrearContrato;
use App\Dominios\Comercial\Aplicacion\ListarContratos;
use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\Excepciones\ActivacionContratoNoDisponible;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaCerrada;
use App\Dominios\Comercial\Dominio\Excepciones\LoteAjenoAlCliente;
use App\Dominios\Comercial\Dominio\Excepciones\LotesDePropiedadAgotados;
use App\Dominios\Comercial\Dominio\Excepciones\TransicionContratoNoPermitida;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarContratoRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CambiarEstadoContratoRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearContratoRequest;
use App\Dominios\Operaciones\Contratos\LecturaResumenOrdenesContrato;
use App\Dominios\Operaciones\Contratos\LecturaTrabajosPorContrato;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET/POST/PUT /panel/contratos*` (HU-23, tarea 34): alta y mantenimiento de
 * contratos con sus lotes. Mismo molde que `ClientesController` (HU-22,
 * tarea 33) — ver `prompts/33-abm-clientes.md` para el detalle de las
 * decisiones que este controlador reutiliza.
 *
 * Sin `destroy`: la baja de un contrato es una transición de estado
 * (`cambiarEstado` hacia `cancelado`), no un soft delete fuera de la máquina
 * de estados — invariante 7 de CLAUDE.md. Cuatro permisos de grano fino
 * (`comercial.contrato.ver`/`.crear`/`.editar`/`.cambiar_estado`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb}. Ninguna regla de negocio acá: los casos de
 * uso de `Aplicacion/` hacen el trabajo, incluido el cálculo de
 * `monto_total` y el upsert de contrato+lotes en una sola transacción.
 *
 * Sin ventanas de contrato (retiradas el 16/9/2026, reemplazo completo por
 * horario a nivel de lote: ver el docblock de `Aplicacion/CrearContrato`):
 * ya no hay `ventanas` en el request ni `VentanasContratoSolapadas` que
 * atrapar.
 *
 * `normalizarLotes()` (lotes agregados en la tarea "contratos-lotes",
 * 16/9/2026, ampliado con horario por lote el mismo día): transforma el
 * array crudo `lotes` del request — `[['lote_id' => '5', 'hora_inicio' =>
 * '06:00', 'hora_fin' => '10:00'], ...]`, ya validado por
 * `CrearContratoRequest`/`ActualizarContratoRequest` — al shape tipado que
 * espera `Aplicacion/CrearContrato`/`ActualizarContrato::ejecutar()`
 * (`lote_id` a `int`, `hora_inicio`/`hora_fin` a `?string`, cadena vacía
 * tratada como `null`). Las dos guardas de negocio, "el lote es del
 * cliente" y "la propiedad no está agotada", viven en
 * `Aplicacion/CrearContrato`/`Aplicacion/ActualizarContrato`, nunca acá.
 *
 * `campaniasDisponibles()` lee el catálogo de campañas vía
 * {@see LecturaCampania::todas()} (ADR 0003 regla 2) — NO con `DB::table`
 * directo: esta clase vivió un tiempo con esa forma (justificada, en su
 * momento, como la regla 3 del ADR — referencias cruzadas por ID), pero es
 * exactamente el mismo agujero que encontró y corrigió
 * `CampaniasController::resumenCampania()` (HU-95): `DB::table('tabla_ajena')`
 * no deja rastro de `Node\Name` para el analizador AST de
 * `tests/Unit/ArquitecturaModulosTest.php`, así que cruza la frontera del
 * módulo sin que el gate lo vea. Sin `cliente_id` (ADR 0015, corregido el
 * 15/9/2026): la campaña es un catálogo compartido, todas las campañas están
 * disponibles para cualquier cliente.
 */
final class ContratosController
{
    private const PERMISO_VER = 'comercial.contrato.ver';

    private const PERMISO_CREAR = 'comercial.contrato.crear';

    private const PERMISO_EDITAR = 'comercial.contrato.editar';

    private const PERMISO_CAMBIAR_ESTADO = 'comercial.contrato.cambiar_estado';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarContratos $listarContratos, LecturaCampania $lecturaCampania): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $campaniaId = $request->integer('campania_id') ?: null;
        $clienteId = $request->integer('cliente_id') ?: null;
        $propiedadId = $request->integer('propiedad_id') ?: null;

        $contratos = $listarContratos->ejecutar($busqueda !== '' ? $busqueda : null, $campaniaId, $clienteId, $propiedadId);

        return view('comercial::pages.contratos.index', [
            ...$this->autorizacion->cascara($request),
            'contratos' => $contratos,
            'campaniasDisponibles' => $this->campaniasDisponibles($lecturaCampania),
            'clientesDisponibles' => $this->clientesActivos(),
            'propiedadesDisponibles' => $this->propiedadesActivas(),
            'filtros' => ['q' => $busqueda, 'campania_id' => $campaniaId, 'cliente_id' => $clienteId, 'propiedad_id' => $propiedadId],
        ]);
    }

    public function create(Request $request, LecturaCampania $lecturaCampania): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.contratos.create', [
            ...$this->autorizacion->cascara($request),
            'clientesDisponibles' => $this->clientesActivos(),
            'campaniasDisponibles' => $this->campaniasDisponibles($lecturaCampania),
            // Acceso directo desde el aside de `panel.clientes.edit` (tarea
            // "resumen de cliente"): con ?cliente_id=, el formulario arranca
            // con ese cliente ya elegido — ver _formulario.blade.php.
            'clienteIdPreseleccionado' => $request->integer('cliente_id') ?: null,
            'propiedadesYLotesPorCliente' => $this->propiedadesYLotesPorCliente(),
        ]);
    }

    public function store(CrearContratoRequest $request, CrearContrato $crearContrato): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();
        $datos['brinda_alimentacion'] = $request->boolean('brinda_alimentacion');
        $datos['brinda_hospedaje'] = $request->boolean('brinda_hospedaje');
        $datos['brinda_combustible'] = $request->boolean('brinda_combustible');

        $lotes = $this->normalizarLotes($datos['lotes'] ?? []);
        unset($datos['lotes']);

        try {
            $contrato = $crearContrato->ejecutar(
                $this->normalizarDatosContrato($datos),
                $lotes,
            );
        } catch (CampaniaCerrada $excepcion) {
            return redirect()
                ->route('panel.contratos.create')
                ->withInput()
                ->withErrors(['campania_id' => $excepcion->getMessage()]);
        } catch (LoteAjenoAlCliente|LotesDePropiedadAgotados $excepcion) {
            return redirect()
                ->route('panel.contratos.create')
                ->withInput()
                ->withErrors(['lotes' => $excepcion->getMessage()]);
        } catch (\Throwable $excepcion) {
            // Cualquier falla no prevista (restricción de base de datos, carrera
            // por doble clic, etc.): sin este catch, el error quedaba solo en
            // el pipeline de excepciones del framework y el usuario se quedaba
            // con el formulario completo pero sin contrato ni aviso — ver
            // incidente del 16/9/2026. `report()` lo manda al log configurado
            // (mismo canal que cualquier excepción no capturada) y el usuario
            // ve un aviso genérico en vez de una página en blanco.
            report($excepcion);

            return redirect()
                ->route('panel.contratos.create')
                ->withInput()
                ->withErrors(['error' => __('http.error_servidor')]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.contratos.edit', $contrato)
            ->with('estado', __('comercial.contratos.creado'));
    }

    public function edit(
        Request $request,
        Contrato $contrato,
        LecturaCampania $lecturaCampania,
        LecturaResumenOrdenesContrato $lecturaResumenOrdenes,
        LecturaTrabajosPorContrato $lecturaTrabajos,
        ObtenerAvanceComercial $obtenerAvance,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.contratos.edit', [
            ...$this->autorizacion->cascara($request),
            'contrato' => $contrato->load('lotes.lote'),
            'clientesDisponibles' => $this->clientesActivos(),
            'campaniasDisponibles' => $this->campaniasDisponibles($lecturaCampania),
            'propiedadesYLotesPorCliente' => $this->propiedadesYLotesPorCliente(),
            'resumenContrato' => $this->resumenContrato($contrato, $request, $lecturaResumenOrdenes, $lecturaTrabajos, $obtenerAvance),
        ]);
    }

    public function update(ActualizarContratoRequest $request, Contrato $contrato, ActualizarContrato $actualizarContrato): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();
        $datos['brinda_alimentacion'] = $request->boolean('brinda_alimentacion');
        $datos['brinda_hospedaje'] = $request->boolean('brinda_hospedaje');
        $datos['brinda_combustible'] = $request->boolean('brinda_combustible');

        $lotes = $this->normalizarLotes($datos['lotes'] ?? []);
        unset($datos['lotes']);

        try {
            $actualizarContrato->ejecutar(
                $contrato,
                $this->normalizarDatosContrato($datos),
                $lotes,
            );
        } catch (CampaniaCerrada $excepcion) {
            return redirect()
                ->route('panel.contratos.edit', $contrato)
                ->withInput()
                ->withErrors(['campania_id' => $excepcion->getMessage()]);
        } catch (LoteAjenoAlCliente|LotesDePropiedadAgotados $excepcion) {
            return redirect()
                ->route('panel.contratos.edit', $contrato)
                ->withInput()
                ->withErrors(['lotes' => $excepcion->getMessage()]);
        } catch (\Throwable $excepcion) {
            // Mismo catch-all que store() — ver ese docblock.
            report($excepcion);

            return redirect()
                ->route('panel.contratos.edit', $contrato)
                ->withInput()
                ->withErrors(['error' => __('http.error_servidor')]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.contratos.edit', $contrato)
            ->with('estado', __('comercial.contratos.actualizado'));
    }

    public function cambiarEstado(CambiarEstadoContratoRequest $request, Contrato $contrato, CambiarEstadoContrato $cambiarEstadoContrato): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CAMBIAR_ESTADO), 403);

        $hacia = EstadoContrato::from((string) $request->validated('estado'));

        try {
            $cambiarEstadoContrato->ejecutar($contrato, $hacia);
        } catch (TransicionContratoNoPermitida|ActivacionContratoNoDisponible $excepcion) {
            return redirect()
                ->route('panel.contratos.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        } catch (\Throwable $excepcion) {
            // Mismo catch-all que store()/update() — reutiliza la clave
            // 'estado', ya cableada en contratos/index.blade.php.
            report($excepcion);

            return redirect()
                ->route('panel.contratos.index')
                ->withErrors(['estado' => __('http.error_servidor')]);
        }

        return redirect()
            ->route('panel.contratos.index')
            ->with('estado', __('comercial.contratos.estado_cambiado'));
    }

    /** @return Collection<int, string> */
    private function clientesActivos(): Collection
    {
        return Cliente::query()->orderBy('razon_social')->pluck('razon_social', 'id');
    }

    /**
     * Todas las propiedades, para el filtro del listado (tarea
     * "listado-contratos-acciones", 16/9/2026) — id => nombre, sin
     * `cliente_id` (a diferencia de `LotesController::propiedadesActivas()`,
     * que sí lo necesita para el cascade cliente → propiedad del formulario;
     * acá los dos selects, cliente y propiedad, son filtros independientes).
     *
     * @return Collection<int, string>
     */
    private function propiedadesActivas(): Collection
    {
        return Propiedad::query()->orderBy('nombre')->pluck('nombre', 'id');
    }

    /**
     * Todas las campañas del catálogo (ADR 0015, corregido el 15/9/2026: la
     * campaña es compartida, no hay que filtrarla por cliente), id => código
     * — misma forma para el filtro del listado y los selects del formulario
     * (antes eran dos lecturas separadas; `LecturaCampania::todas()` ya
     * entrega lo mismo para las dos, no hace falta duplicarlas).
     *
     * @return Collection<int, string>
     */
    private function campaniasDisponibles(LecturaCampania $lecturaCampania): Collection
    {
        return collect($lecturaCampania->todas())
            ->mapWithKeys(fn (DatosCampania $campania): array => [$campania->id => $campania->codigo]);
    }

    /**
     * @param  array<string, mixed>  $datos  validados, sin `lotes`
     * @return array<string, mixed> listo para `Aplicacion/CrearContrato`/`ActualizarContrato`
     */
    private function normalizarDatosContrato(array $datos): array
    {
        return [
            'cliente_id' => (int) $datos['cliente_id'],
            'campania_id' => (int) $datos['campania_id'],
            'hectareas_contratadas' => (string) $datos['hectareas_contratadas'],
            'aplicaciones_previstas' => (int) $datos['aplicaciones_previstas'],
            'precio_ha' => (string) $datos['precio_ha'],
            'adelanto_monto' => $this->cadenaONull($datos['adelanto_monto'] ?? null),
            'fecha_inicio' => (string) $datos['fecha_inicio'],
            'fecha_fin' => $this->cadenaONull($datos['fecha_fin'] ?? null),
            'brinda_alimentacion' => (bool) $datos['brinda_alimentacion'],
            'brinda_hospedaje' => (bool) $datos['brinda_hospedaje'],
            'brinda_combustible' => (bool) $datos['brinda_combustible'],
            'observaciones_logistica' => $this->cadenaONull($datos['observaciones_logistica'] ?? null),
        ];
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /**
     * Resumen del aside de `edit()` (tarea "resumen de contrato", sept/2026):
     * mismo criterio que `ClientesController::resumenRelacionado()` — sin
     * datos de aplicación todavía, un `empty-state` con el atajo a crear una
     * orden (idéntico al que ya ofrece el aside de cliente, misma ruta sin
     * preseleccionar nada: `OrdenesController::create()` no acepta
     * `contrato_id` por query, así que ninguno de los dos aside lo pasa).
     * Con datos, dos tarjetas — mismo lenguaje que
     * `CampaniasController::resumenCampania()` pero acotadas A UN contrato:
     * "Facturación" (monto contratado vs. facturado, vía
     * {@see ObtenerAvanceComercial}, ya filtrable por `contratoId`) y
     * "Aplicación" (hectáreas aplicadas + trabajos, vía
     * {@see LecturaTrabajosPorContrato}, mismo caso de uso que campañas con
     * `[$contrato->id]` como único elemento).
     *
     * Sin acción "ver más" en las tarjetas con datos: ni `panel.ordenes.index`
     * ni `panel.facturas.index` aceptan filtrar por `contrato_id` hoy —
     * agregar ese filtro es una tarea de esas pantallas, no de este resumen.
     *
     * `null` si el usuario no tiene ni `.ver` ni `.crear` de órdenes (mismo
     * criterio de permisos que clientes: sin ninguno de los dos, la tarjeta
     * no aporta nada).
     *
     * @return array{tieneDatos: false, icono: string, titulo: string, detalle: string, mostrarAccion: bool, accion: array{label: string, href: string}}|array{tieneDatos: true, tarjetas: list<array{titulo: string, items: list<array{label: string, value: string, mono: bool, variant?: string}>}>}|null
     */
    private function resumenContrato(
        Contrato $contrato,
        Request $request,
        LecturaResumenOrdenesContrato $lecturaResumenOrdenes,
        LecturaTrabajosPorContrato $lecturaTrabajos,
        ObtenerAvanceComercial $obtenerAvance,
    ): ?array {
        $puedeVerOrdenes = $this->autorizacion->tienePermiso($request, 'operaciones.orden.ver');
        $puedeCrearOrdenes = $this->autorizacion->tienePermiso($request, 'operaciones.orden.crear');

        if (! $puedeVerOrdenes && ! $puedeCrearOrdenes) {
            return null;
        }

        $totalOrdenes = $puedeVerOrdenes ? $lecturaResumenOrdenes->resumen([$contrato->id])['total'] : 0;

        if (! $puedeVerOrdenes || $totalOrdenes === 0) {
            return [
                'tieneDatos' => false,
                'icono' => 'assignment',
                'titulo' => __('comercial.contratos.aside_vacio_titulo'),
                'detalle' => __('comercial.contratos.aside_vacio_detalle'),
                'mostrarAccion' => $puedeCrearOrdenes,
                'accion' => [
                    'label' => __('comercial.contratos.aside_vacio_accion'),
                    'href' => route('panel.ordenes.create'),
                ],
            ];
        }

        $avance = $obtenerAvance->ejecutar(contratoId: $contrato->id)[0] ?? null;
        $totalTrabajos = $lecturaTrabajos->total([$contrato->id]);

        $montoContratado = BigDecimal::of($contrato->monto_total);
        $montoFacturado = BigDecimal::of($avance['montoFacturado'] ?? '0');
        $saldoPendiente = $montoContratado->minus($montoFacturado);

        return [
            'tieneDatos' => true,
            'tarjetas' => [
                [
                    'titulo' => __('comercial.contratos.aside_facturacion_titulo'),
                    'items' => [
                        ['label' => __('comercial.contratos.aside_monto_contratado'), 'value' => $this->aMoneda($montoContratado), 'mono' => true],
                        ['label' => __('comercial.contratos.aside_monto_facturado'), 'value' => $this->aMoneda($montoFacturado), 'mono' => true],
                        [
                            'label' => __('comercial.contratos.aside_saldo_pendiente'),
                            'value' => $this->aMoneda($saldoPendiente),
                            'mono' => true,
                            'variant' => $saldoPendiente->isZero() ? 'success' : 'neutral',
                        ],
                    ],
                ],
                [
                    'titulo' => __('comercial.contratos.aside_aplicacion_titulo'),
                    'items' => [
                        ['label' => __('comercial.contratos.aside_hectareas_contratadas'), 'value' => $avance['hectareasContratadas'] ?? '0.00', 'mono' => true],
                        ['label' => __('comercial.contratos.aside_hectareas_aplicadas'), 'value' => $avance['hectareasAplicadas'] ?? '0.00', 'mono' => true],
                        ['label' => __('comercial.contratos.aside_trabajos'), 'value' => (string) $totalTrabajos, 'mono' => true],
                    ],
                ],
            ],
        ];
    }

    private function aMoneda(BigDecimal $valor): string
    {
        return number_format((float) (string) $valor, 2, ',', '.');
    }

    /**
     * Transforma el array crudo `lotes` del request — ya validado por
     * `CrearContratoRequest`/`ActualizarContratoRequest` (cada fila trae
     * `lote_id` y, opcionalmente, `hora_inicio`/`hora_fin`) — al shape
     * tipado que espera
     * `Aplicacion/CrearContrato`/`ActualizarContrato::ejecutar()`: castea
     * `lote_id` a `int`, y `hora_inicio`/`hora_fin` a `?string`, tratando
     * cadena vacía como `null` (mismo criterio que `cadenaONull()` para el
     * resto del formulario).
     *
     * @param  list<array<string, mixed>>  $lotesCrudos
     * @return list<array{lote_id: int, hora_inicio: ?string, hora_fin: ?string}>
     */
    private function normalizarLotes(array $lotesCrudos): array
    {
        return array_map(fn (array $lote): array => [
            'lote_id' => (int) $lote['lote_id'],
            'hora_inicio' => $this->cadenaONull($lote['hora_inicio'] ?? null),
            'hora_fin' => $this->cadenaONull($lote['hora_fin'] ?? null),
        ], $lotesCrudos);
    }

    /**
     * Propiedades y sus lotes, agrupados por cliente, para precargar en el
     * formulario de contrato (tarea "contratos-lotes", estrategia 'a': datos
     * embebidos en el HTML). Estructura JSON embebida en un data-attribute:
     * `{ [cliente_id]: { [propiedad_id]: { nombre, lotes: [{ id, codigo,
     * hectareas, desnivel, desnivel_label, limpieza, limpieza_label }] } } }`.
     *
     * `desnivel`/`limpieza` viajan CRUDOS (para que el modal de selección de
     * lotes del JS los pueda usar como filtro/clase CSS el día de mañana) más
     * su `_label` ya traducido (ADR 0013: el átomo/JS no decide textos de
     * negocio) — `null` cuando el lote no tiene el dato cargado (columnas
     * nullable, ver migración `add_desnivel_limpieza_a_com_lotes_table`).
     *
     * @return array<int, array<int, array{nombre: string, lotes: list<array{id: int, codigo: string, hectareas: string, desnivel: ?string, desnivel_label: ?string, limpieza: ?string, limpieza_label: ?string}>}>>
     */
    private function propiedadesYLotesPorCliente(): array
    {
        $propiedades = Propiedad::query()
            ->with(['lotes' => fn ($query) => $query->whereNull('deleted_at')])
            ->whereNull('deleted_at')
            ->get(['id', 'cliente_id', 'nombre']);

        $result = [];
        foreach ($propiedades as $propiedad) {
            $clienteId = (int) $propiedad->cliente_id;
            $propiedadId = (int) $propiedad->id;

            if (! isset($result[$clienteId])) {
                $result[$clienteId] = [];
            }

            $result[$clienteId][$propiedadId] = [
                'nombre' => $propiedad->nombre,
                'lotes' => $propiedad->lotes->map(fn ($lote) => [
                    'id' => (int) $lote->id,
                    'codigo' => $lote->codigo,
                    'hectareas' => (string) $lote->hectareas,
                    'desnivel' => $lote->desnivel,
                    'desnivel_label' => $lote->desnivel ? __("comercial.lotes.lote_desnivel_{$lote->desnivel}") : null,
                    'limpieza' => $lote->limpieza,
                    'limpieza_label' => $lote->limpieza ? __("comercial.lotes.lote_limpieza_{$lote->limpieza}") : null,
                ])->values()->all(),
            ];
        }

        return $result;
    }
}
