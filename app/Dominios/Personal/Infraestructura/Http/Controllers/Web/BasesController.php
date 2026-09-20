<?php

namespace App\Dominios\Personal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Inventario\Contratos\LecturaStockPorBase;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamientoPorBase;
use App\Dominios\Personal\Aplicacion\ActualizarBase;
use App\Dominios\Personal\Aplicacion\CrearBase;
use App\Dominios\Personal\Aplicacion\EliminarBase;
use App\Dominios\Personal\Aplicacion\ListarBases;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Http\Requests\ActualizarBaseRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\CrearBaseRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/bases*` (HU-26, tarea 37): alta y
 * mantenimiento de bases operativas. Mismo molde que `DronesController`
 * (HU-27, tarea 36) — ABM simple, sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`personal.base.ver`/`.crear`/`.editar`/`.eliminar`), verificados DENTRO
 * del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} —
 * mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 */
final class BasesController
{
    private const PERMISO_VER = 'personal.base.ver';

    private const PERMISO_CREAR = 'personal.base.crear';

    private const PERMISO_EDITAR = 'personal.base.editar';

    private const PERMISO_ELIMINAR = 'personal.base.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarBases $listarBases): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('personal::pages.bases.index', [
            ...$this->autorizacion->cascara($request),
            'bases' => $listarBases->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('personal::pages.bases.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearBaseRequest $request, CrearBase $crearBase): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $base = $crearBase->ejecutar(
            (string) $datos['nombre'],
            $this->cadenaONull($datos['ubicacion'] ?? null),
            $this->cadenaONull($datos['latitud'] ?? null),
            $this->cadenaONull($datos['longitud'] ?? null),
        );

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.bases.edit', $base)
            ->with('estado', __('personal.bases.creado'));
    }

    public function edit(
        Request $request,
        PerBase $base,
        LecturaEquipamientoPorBase $lecturaEquipamiento,
        LecturaStockPorBase $lecturaStock,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('personal::pages.bases.edit', [
            ...$this->autorizacion->cascara($request),
            'base' => $base,
            'resumenRelacionado' => $this->resumenRelacionado($base, $request, $lecturaEquipamiento, $lecturaStock),
        ]);
    }

    public function update(ActualizarBaseRequest $request, PerBase $base, ActualizarBase $actualizarBase): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        $actualizarBase->ejecutar(
            $base,
            (string) $datos['nombre'],
            $this->cadenaONull($datos['ubicacion'] ?? null),
            $this->cadenaONull($datos['latitud'] ?? null),
            $this->cadenaONull($datos['longitud'] ?? null),
        );

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.bases.edit', $base)
            ->with('estado', __('personal.bases.actualizado'));
    }

    public function destroy(Request $request, PerBase $base, EliminarBase $eliminarBase): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarBase->ejecutar($base);

        return redirect()
            ->route('panel.bases.index')
            ->with('estado', __('personal.bases.eliminado'));
    }

    /**
     * Resumen relacionado del aside de `edit()` (solo edición, §6.3.1 de la
     * guía de pantalla): una base recién creada no puede tener todavía
     * personas, cuadrillas, equipamiento ni stock. Cuatro tarjetas, cada una
     * gateada por el permiso de LO QUE MUESTRA contra el ROL ACTIVO
     * (invariante 10), no por `personal.base.*`: ver cuántas personas tiene la
     * base es ver personas. Una categoría sin `.ver` NI `.crear` se omite
     * del todo; con `.crear` pero sin `.ver` se ofrece el atajo sin revelar
     * conteos.
     *
     * Personas y Cuadrillas son del mismo módulo (Eloquent directo).
     * Equipamiento y Stock son de Mantenimiento e Inventario: llegan por sus
     * contratos de lectura (ADR 0003, regla 2), nunca por sus tablas.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(
        PerBase $base,
        Request $request,
        LecturaEquipamientoPorBase $lecturaEquipamiento,
        LecturaStockPorBase $lecturaStock,
    ): array {
        $resumen = [];

        // Memento de navegación: los atajos de alta apilan ESTA ficha como
        // origen, así el "Volver" de la pantalla de destino regresa acá y no
        // al listado de su propio módulo. Ver RecordarOrigenNavegacion.
        $origenNavegacion = ['volver_a' => route('panel.bases.edit', $base), 'volver_texto' => $base->nombre];

        // 1) Personas asignadas a la base (mismo módulo).
        $puedeVerPersonas = $this->autorizacion->tienePermiso($request, 'personal.persona.ver');
        $puedeCrearPersonas = $this->autorizacion->tienePermiso($request, 'personal.persona.crear');

        if ($puedeVerPersonas || $puedeCrearPersonas) {
            $totalPersonas = $puedeVerPersonas ? $base->personas()->count() : 0;
            $pilotos = $puedeVerPersonas ? $base->personas()->where('rol', RolOperativoPersona::Piloto)->count() : 0;

            $resumen[] = [
                'titulo' => __('personal.bases.aside_personas_titulo'),
                'icono' => 'badge',
                'tieneDatos' => $totalPersonas > 0,
                'items' => [
                    ['label' => __('personal.bases.aside_personas_total'), 'value' => (string) $totalPersonas, 'mono' => true],
                    ['label' => __('personal.bases.aside_personas_pilotos'), 'value' => (string) $pilotos, 'mono' => true],
                ],
                'vacioTitulo' => __('personal.bases.aside_personas_vacio_titulo'),
                'vacioDetalle' => __('personal.bases.aside_personas_vacio_detalle'),
                'acciones' => $puedeCrearPersonas ? [[
                    'label' => __('personal.bases.aside_personas_accion'),
                    'href' => route('panel.personas.create', ['base_id' => $base->id, ...$origenNavegacion]),
                    'icono' => 'add',
                ]] : [],
            ];
        }

        // 2) Cuadrillas que salen de la base (mismo módulo).
        $puedeVerCuadrillas = $this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.ver');
        $puedeCrearCuadrillas = $this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.crear');

        if ($puedeVerCuadrillas || $puedeCrearCuadrillas) {
            $cuadrillas = EquipoTrabajo::query()->where('base_id', $base->id);
            $totalCuadrillas = $puedeVerCuadrillas ? (clone $cuadrillas)->count() : 0;
            $cuadrillasActivas = $puedeVerCuadrillas ? (clone $cuadrillas)->where('estado', EstadoEquipoTrabajo::Activo)->count() : 0;

            $acciones = [];

            if ($puedeVerCuadrillas && $totalCuadrillas > 0) {
                $acciones[] = [
                    'label' => __('personal.bases.aside_cuadrillas_accion_ver'),
                    'href' => route('panel.cuadrillas.index', ['base_id' => $base->id]),
                    'icono' => 'list',
                ];
            }

            if ($puedeCrearCuadrillas) {
                $acciones[] = [
                    'label' => __('personal.bases.aside_cuadrillas_accion_crear'),
                    'href' => route('panel.cuadrillas.create', $origenNavegacion),
                    'icono' => 'add',
                ];
            }

            $resumen[] = [
                'titulo' => __('personal.bases.aside_cuadrillas_titulo'),
                'icono' => 'groups',
                'tieneDatos' => $totalCuadrillas > 0,
                'items' => [
                    ['label' => __('personal.bases.aside_cuadrillas_total'), 'value' => (string) $totalCuadrillas, 'mono' => true],
                    [
                        'label' => __('personal.bases.aside_cuadrillas_activas'),
                        'value' => (string) $cuadrillasActivas,
                        'mono' => true,
                        'variant' => $cuadrillasActivas > 0 ? 'success' : 'neutral',
                    ],
                ],
                'vacioTitulo' => __('personal.bases.aside_cuadrillas_vacio_titulo'),
                'vacioDetalle' => __('personal.bases.aside_cuadrillas_vacio_detalle'),
                'acciones' => $acciones,
            ];
        }

        // 3) Equipamiento con esta base (Mantenimiento, por contrato). Un
        // permiso por tipo: cada fila del resumen solo aparece si el rol
        // puede ver ese catálogo; sin ninguno, la tarjeta se omite.
        $tiposVisibles = collect([
            'vehiculos' => 'mantenimiento.vehiculo.ver',
            'generadores' => 'mantenimiento.generador.ver',
            'baterias' => 'mantenimiento.bateria.ver',
        ])->filter(fn (string $permiso): bool => $this->autorizacion->tienePermiso($request, $permiso));

        if ($tiposVisibles->isNotEmpty()) {
            $equipamiento = $lecturaEquipamiento->deBase($base->id);
            $cifras = ['vehiculos' => $equipamiento->vehiculos, 'generadores' => $equipamiento->generadores, 'baterias' => $equipamiento->baterias];
            $items = [];

            foreach ($tiposVisibles->keys() as $tipo) {
                $items[] = ['label' => __('personal.bases.aside_equipamiento_'.$tipo), 'value' => (string) $cifras[$tipo], 'mono' => true];
            }

            $resumen[] = [
                'titulo' => __('personal.bases.aside_equipamiento_titulo'),
                'icono' => 'local_shipping',
                'tieneDatos' => $tiposVisibles->keys()->sum(fn (string $tipo): int => $cifras[$tipo]) > 0,
                'items' => $items,
                'vacioTitulo' => __('personal.bases.aside_equipamiento_vacio_titulo'),
                'vacioDetalle' => __('personal.bases.aside_equipamiento_vacio_detalle'),
                'acciones' => [],
            ];
        }

        // 4) Stock de repuestos en la base (Inventario, por contrato).
        $puedeVerStock = $this->autorizacion->tienePermiso($request, 'inventario.movimiento.ver');
        $puedeRegistrarStock = $this->autorizacion->tienePermiso($request, 'inventario.movimiento.crear');

        if ($puedeVerStock || $puedeRegistrarStock) {
            $stock = $puedeVerStock ? $lecturaStock->deBase($base->id) : null;
            $repuestos = $stock->repuestos ?? 0;
            $bajoMinimo = $stock->bajoMinimo ?? 0;

            $acciones = [];

            if ($puedeVerStock && $repuestos > 0) {
                $acciones[] = [
                    'label' => __('personal.bases.aside_stock_accion_ver'),
                    'href' => route('panel.stock.index', ['base_id' => $base->id]),
                    'icono' => 'list',
                ];
            }

            if ($puedeRegistrarStock) {
                $acciones[] = [
                    'label' => __('personal.bases.aside_stock_accion_registrar'),
                    'href' => route('panel.stock.movimientos.create', $origenNavegacion),
                    'icono' => 'add',
                ];
            }

            $resumen[] = [
                'titulo' => __('personal.bases.aside_stock_titulo'),
                'icono' => 'warehouse',
                'tieneDatos' => $repuestos > 0,
                'items' => [
                    ['label' => __('personal.bases.aside_stock_repuestos'), 'value' => (string) $repuestos, 'mono' => true],
                    [
                        'label' => __('personal.bases.aside_stock_bajo_minimo'),
                        'value' => (string) $bajoMinimo,
                        'mono' => true,
                        'variant' => $bajoMinimo > 0 ? 'warning' : 'neutral',
                    ],
                ],
                'vacioTitulo' => __('personal.bases.aside_stock_vacio_titulo'),
                'vacioDetalle' => __('personal.bases.aside_stock_vacio_detalle'),
                'acciones' => $acciones,
            ];
        }

        return $resumen;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
