<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Finanzas\Contratos\LecturaCombustiblePorRecurso;
use App\Dominios\Mantenimiento\Aplicacion\ActualizarGenerador;
use App\Dominios\Mantenimiento\Aplicacion\CrearGenerador;
use App\Dominios\Mantenimiento\Aplicacion\EliminarGenerador;
use App\Dominios\Mantenimiento\Aplicacion\ListarGeneradores;
use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use App\Dominios\Mantenimiento\Dominio\Excepciones\GeneradorDuplicado;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarGeneradorRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearGeneradorRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\ResumenRelacionadoDeEquipo;
use App\Dominios\Personal\Contratos\LecturaCuadrillasPorRecurso;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/generadores*` (tarea 72, HU-49): ABM mínimo
 * del catálogo de generadores. Mismo molde que `VehiculosController`, sin
 * sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.generador.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 *
 * El select de `base_id` se arma con `DB::table('per_bases')` (ADR 0003
 * regla 3, mismo criterio que `VehiculosController`), sin importar el
 * modelo Eloquent `PerBase` de `Personal`.
 */
final class GeneradoresController
{
    private const PERMISO_VER = 'mantenimiento.generador.ver';

    private const PERMISO_CREAR = 'mantenimiento.generador.crear';

    private const PERMISO_EDITAR = 'mantenimiento.generador.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.generador.eliminar';

    /**
     * Tono de cada estado, definido UNA vez (§6.3.4 de la guía de pantalla):
     * lo lee el badge del listado. Eje gris↔verde, y rojo para la baja
     * definitiva: `activo` es el estado sano, `taller` una baja temporal que
     * no es un problema en sí. Los tonos son los que la pantalla ya tenía: no
     * se reeligen acá.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'activo' => 'success',
        'taller' => 'neutral',
        'de_baja' => 'danger',
    ];

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarGeneradores $listarGeneradores): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $baseQuery = TextoDeFiltro::de($request, 'base_id');
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;
        $estadoQuery = TextoDeFiltro::de($request, 'estado');
        $estado = $estadoQuery !== '' ? EstadoGenerador::tryFrom($estadoQuery) : null;

        $generadores = $listarGeneradores->ejecutar(
            busqueda: $busqueda !== '' ? $busqueda : null,
            baseId: $baseId,
            estado: $estado?->value,
        );

        return view('mantenimiento::pages.generadores.index', [
            ...$this->autorizacion->cascara($request),
            'generadores' => $generadores,
            'etiquetasBase' => $this->etiquetasBase($generadores->pluck('base_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'basesDisponibles' => $this->basesDisponibles(),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'estadosFiltro' => EstadoGenerador::cases(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId, 'estado' => $estado?->value],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.generadores.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoGenerador::cases(),
        ]);
    }

    public function store(CrearGeneradorRequest $request, CrearGenerador $crearGenerador): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $generador = $crearGenerador->ejecutar(
                (string) $datos['identificador'],
                $this->stringONull($datos['modelo'] ?? null),
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoGenerador::from((string) $datos['estado']),
                $this->stringONull($datos['horas_inicial'] ?? null),
                $this->stringONull($datos['horas_actual'] ?? null),
            );
        } catch (GeneradorDuplicado $excepcion) {
            return redirect()
                ->route('panel.generadores.create')
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.generadores.edit', $generador)
            ->with('estado', __('mantenimiento.generadores.creado'));
    }

    public function edit(Request $request, Generador $generador, ResumenRelacionadoDeEquipo $tarjetas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.generadores.edit', [
            ...$this->autorizacion->cascara($request),
            'generador' => $generador,
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoGenerador::cases(),
            'resumenRelacionado' => $this->resumenRelacionado($generador, $request, $tarjetas),
        ]);
    }

    public function update(ActualizarGeneradorRequest $request, Generador $generador, ActualizarGenerador $actualizarGenerador): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarGenerador->ejecutar(
                $generador,
                (string) $datos['identificador'],
                $this->stringONull($datos['modelo'] ?? null),
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoGenerador::from((string) $datos['estado']),
                $this->stringONull($datos['horas_inicial'] ?? null),
                $this->stringONull($datos['horas_actual'] ?? null),
            );
        } catch (GeneradorDuplicado $excepcion) {
            return redirect()
                ->route('panel.generadores.edit', $generador)
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.generadores.edit', $generador)
            ->with('estado', __('mantenimiento.generadores.actualizado'));
    }

    public function destroy(Request $request, Generador $generador, EliminarGenerador $eliminarGenerador): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarGenerador->ejecutar($generador);

        return redirect()
            ->route('panel.generadores.index')
            ->with('estado', __('mantenimiento.generadores.eliminado'));
    }

    /**
     * Resumen relacionado del aside de `edit()` (solo edición, §6.3.1 de la
     * guía de pantalla): un generador recién creado no puede tener todavía
     * cuadrillas ni cargas de combustible. Dos tarjetas, cada una gateada por
     * el permiso de LO QUE MUESTRA contra el ROL ACTIVO (invariante 10), no por
     * `mantenimiento.generador.*`; las arma `ResumenRelacionadoDeEquipo` con los
     * contratos de Personal y de Finanzas (ADR 0003, regla 2).
     *
     * No hay órdenes de mantenimiento ni planes: `man_ordenes_mantenimiento`
     * solo admite `dron` y `vehiculo` como equipo (CHECK de la migración) y los
     * planes se cruzan por el modelo de un dron. Inventar esas tarjetas sería
     * mostrar una relación que el esquema no tiene.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(Generador $generador, Request $request, ResumenRelacionadoDeEquipo $tarjetas): array
    {
        // Memento de navegación: los atajos de alta apilan ESTA ficha como
        // origen, así el "Volver" de la pantalla de destino regresa acá y no
        // al listado de su propio módulo. Ver RecordarOrigenNavegacion.
        $origenNavegacion = ['volver_a' => route('panel.generadores.edit', $generador), 'volver_texto' => $generador->identificador];

        return array_values(array_filter([
            $tarjetas->cuadrillas(
                $request,
                LecturaCuadrillasPorRecurso::TIPO_GENERADOR,
                $generador->id,
                __('mantenimiento.generadores.aside_cuadrillas_vacio_detalle'),
            ),
            $tarjetas->combustible(
                $request,
                LecturaCombustiblePorRecurso::TIPO_GENERADOR,
                $generador->id,
                $origenNavegacion,
                __('mantenimiento.generadores.aside_combustible_vacio_detalle'),
            ),
        ]));
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function stringONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /** @return Collection<int, string> */
    private function basesDisponibles(): Collection
    {
        return DB::table('per_bases')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('nombre', 'id');
    }

    /**
     * Etiquetas legibles para la columna "Base" del listado (mismo criterio
     * de lectura directa por `DB::table` que `basesDisponibles()`). Una base
     * borrada lógicamente después de asignada a un generador queda fuera del
     * mapa a propósito: la vista cae al `#id` crudo.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasBase(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_bases')
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->map(fn ($nombre) => (string) $nombre)
            ->all();
    }
}
