<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Mantenimiento\Aplicacion\ActualizarBateria;
use App\Dominios\Mantenimiento\Aplicacion\CrearBateria;
use App\Dominios\Mantenimiento\Aplicacion\EliminarBateria;
use App\Dominios\Mantenimiento\Aplicacion\ListarBaterias;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\Excepciones\BateriaDuplicada;
use App\Dominios\Mantenimiento\Dominio\Excepciones\CorreccionCiclosNoAutorizada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarBateriaRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearBateriaRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\ResumenRelacionadoDeEquipo;
use App\Dominios\Operaciones\Contratos\LecturaRecargasPorBateria;
use App\Dominios\Personal\Contratos\LecturaCuadrillasPorRecurso;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/baterias*` (HU-39, tarea 51): alta y
 * mantenimiento del catálogo de baterías, con sus ciclos acumulados, estado
 * y asignación a base. Mismo molde que `VehiculosController`, sin
 * sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.bateria.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo, incluida la alerta de
 * retiro que calcula `ListarBaterias`.
 *
 * El select de `base_id` se arma con `DB::table('per_bases')` (ADR 0003
 * regla 3, mismo criterio que `VehiculosController`), sin importar el
 * modelo Eloquent `PerBase` de `Personal`.
 */
final class BateriasController
{
    private const PERMISO_VER = 'mantenimiento.bateria.ver';

    private const PERMISO_CREAR = 'mantenimiento.bateria.crear';

    private const PERMISO_EDITAR = 'mantenimiento.bateria.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.bateria.eliminar';

    /**
     * Tono de cada estado, definido UNA vez (§6.3.4 de la guía de pantalla):
     * lo lee el badge del listado. Eje gris↔verde: `activa` es el estado sano
     * y `retirada`/`mantenimiento` son bajas (definitiva y temporal) que no
     * son un problema en sí. El ámbar queda reservado a la columna de alerta.
     * Los tonos son los que la pantalla ya tenía: no se reeligen acá.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'activa' => 'success',
        'retirada' => 'neutral',
        'mantenimiento' => 'neutral',
    ];

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarBaterias $listarBaterias): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $baseQuery = TextoDeFiltro::de($request, 'base_id');
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;
        $estadoQuery = TextoDeFiltro::de($request, 'estado');
        $estado = $estadoQuery !== '' ? EstadoBateria::tryFrom($estadoQuery) : null;

        $baterias = $listarBaterias->ejecutar(
            busqueda: $busqueda !== '' ? $busqueda : null,
            baseId: $baseId,
            estado: $estado?->value,
        );

        return view('mantenimiento::pages.baterias.index', [
            ...$this->autorizacion->cascara($request),
            'baterias' => $baterias,
            'etiquetasBase' => $this->etiquetasBase($baterias->pluck('base_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'basesDisponibles' => $this->basesDisponibles(),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'estadosFiltro' => EstadoBateria::cases(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId, 'estado' => $estado?->value],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.baterias.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoBateria::cases(),
        ]);
    }

    public function store(CrearBateriaRequest $request, CrearBateria $crearBateria): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $bateria = $crearBateria->ejecutar(
                (string) $datos['identificador'],
                (int) $datos['ciclos_inicial'],
                (int) $datos['ciclos_acumulados'],
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoBateria::from((string) $datos['estado']),
            );
        } catch (BateriaDuplicada $excepcion) {
            return redirect()
                ->route('panel.baterias.create')
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.baterias.edit', $bateria)
            ->with('estado', __('mantenimiento.baterias.creado'));
    }

    public function edit(
        Request $request,
        Bateria $bateria,
        ResumenRelacionadoDeEquipo $tarjetas,
        LecturaRecargasPorBateria $lecturaRecargas,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.baterias.edit', [
            ...$this->autorizacion->cascara($request),
            'bateria' => $bateria,
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoBateria::cases(),
            'resumenRelacionado' => $this->resumenRelacionado($bateria, $request, $tarjetas, $lecturaRecargas),
        ]);
    }

    public function update(ActualizarBateriaRequest $request, Bateria $bateria, ActualizarBateria $actualizarBateria): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarBateria->ejecutar(
                $bateria,
                (string) $datos['identificador'],
                (int) $datos['ciclos_acumulados'],
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoBateria::from((string) $datos['estado']),
                $this->stringONull($datos['motivo_correccion'] ?? null),
            );
        } catch (BateriaDuplicada $excepcion) {
            return redirect()
                ->route('panel.baterias.edit', $bateria)
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        } catch (CorreccionCiclosNoAutorizada $excepcion) {
            return redirect()
                ->route('panel.baterias.edit', $bateria)
                ->withInput()
                ->withErrors(['motivo_correccion' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.baterias.edit', $bateria)
            ->with('estado', __('mantenimiento.baterias.actualizado'));
    }

    public function destroy(Request $request, Bateria $bateria, EliminarBateria $eliminarBateria): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarBateria->ejecutar($bateria);

        return redirect()
            ->route('panel.baterias.index')
            ->with('estado', __('mantenimiento.baterias.eliminado'));
    }

    /**
     * Resumen relacionado del aside de `edit()` (solo edición, §6.3.1 de la
     * guía de pantalla): una batería recién creada no puede tener todavía
     * cuadrillas ni recargas. Dos tarjetas, cada una gateada por el permiso de
     * LO QUE MUESTRA contra el ROL ACTIVO (invariante 10), no por
     * `mantenimiento.bateria.*`.
     *
     * No hay órdenes de mantenimiento ni planes: `man_ordenes_mantenimiento`
     * solo admite `dron` y `vehiculo` como equipo (CHECK de la migración) y los
     * planes se cruzan por el modelo de un dron. Inventar esas tarjetas sería
     * mostrar una relación que el esquema no tiene.
     *
     * Las cuadrillas llegan por el contrato de Personal (compartido con el
     * generador y el vehículo, ver `ResumenRelacionadoDeEquipo`); las recargas
     * por el de Operaciones, que las cruza por el TEXTO del identificador
     * (`ope_recargas.bateria_saliente_id`, sin FK). Las recargas no tienen
     * atajo de alta: llegan desde la app de campo cuando el piloto cambia la
     * batería.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(
        Bateria $bateria,
        Request $request,
        ResumenRelacionadoDeEquipo $tarjetas,
        LecturaRecargasPorBateria $lecturaRecargas,
    ): array {
        $resumen = [];

        $cuadrillas = $tarjetas->cuadrillas(
            $request,
            LecturaCuadrillasPorRecurso::TIPO_BATERIA,
            $bateria->id,
            __('mantenimiento.baterias.aside_cuadrillas_vacio_detalle'),
        );

        if ($cuadrillas !== null) {
            $resumen[] = $cuadrillas;
        }

        // Las recargas son parte de las sesiones de vuelo: los permisos de
        // `operaciones.trabajo.*` cubren «trabajos y sesiones».
        if ($this->autorizacion->tienePermiso($request, 'operaciones.trabajo.ver')) {
            $recargas = $lecturaRecargas->deBateria($bateria->identificador);

            $resumen[] = [
                'titulo' => __('mantenimiento.baterias.aside_recargas_titulo'),
                'icono' => 'bolt',
                'tieneDatos' => $recargas->total > 0,
                'items' => [
                    ['label' => __('mantenimiento.baterias.aside_recargas_total'), 'value' => (string) $recargas->total, 'mono' => true],
                    [
                        'label' => __('mantenimiento.baterias.aside_recargas_alertas'),
                        'value' => (string) $recargas->conAlertaTemperatura,
                        'mono' => true,
                        'variant' => $recargas->conAlertaTemperatura > 0 ? 'warning' : 'neutral',
                    ],
                ],
                'vacioTitulo' => __('mantenimiento.baterias.aside_recargas_vacio_titulo'),
                'vacioDetalle' => __('mantenimiento.baterias.aside_recargas_vacio_detalle'),
                'acciones' => [],
            ];
        }

        return $resumen;
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
     * borrada lógicamente después de asignada a una batería queda fuera del
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
