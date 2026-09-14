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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT /panel/campanias*` (ADR 0015 punto 1, tarea 69): alta y
 * mantenimiento de campañas **del cliente** (corregido el 8/9/2026). Mismo
 * molde que `ContratosController`: sin `destroy` (la baja es una transición
 * de estado hacia `cerrada`, no un soft delete fuera de la máquina de
 * estados — invariante 7), sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`campania.campania.ver`/`.crear`/`.editar`/`.cambiar_estado`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}.
 * `.cambiar_estado` es exclusivo del rol `dueno` en `SeguridadSeeder` — "solo
 * el dueño cierra una campaña" (ADR 0015, prompt de la tarea 69) — pero eso es
 * dato del catálogo de permisos, no una guarda extra acá. Ninguna regla de
 * negocio acá: los casos de uso de `Aplicacion/` hacen el trabajo.
 *
 * `clientesDisponibles()`/`etiquetasCliente()` leen `com_clientes` con
 * `DB::table` directo (ADR 0003 regla 3, mismo criterio que
 * `GastosController::basesDisponibles()`), sin importar el modelo Eloquent
 * `Cliente` de `Comercial` — cross-módulo, así que solo FK + entero plano.
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
        $clienteId = $request->integer('cliente_id') ?: null;

        $campanias = $listarCampanias->ejecutar($busqueda !== '' ? $busqueda : null, $clienteId);

        return view('campania::pages.campanias.index', [
            ...$this->autorizacion->cascara($request),
            'campanias' => $campanias,
            'clientesDisponibles' => $this->clientesDisponibles(),
            'etiquetasCliente' => $this->etiquetasCliente($campanias->pluck('cliente_id')->unique()->all()),
            'filtros' => ['q' => $busqueda, 'cliente_id' => $clienteId],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('campania::pages.campanias.create', [
            ...$this->autorizacion->cascara($request),
            'clientesDisponibles' => $this->clientesDisponibles(),
        ]);
    }

    public function store(CrearCampaniaRequest $request, CrearCampania $crearCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearCampania->ejecutar(
                (int) $datos['cliente_id'],
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
            'clientesDisponibles' => $this->clientesDisponibles(),
        ]);
    }

    public function update(ActualizarCampaniaRequest $request, Campania $campania, ActualizarCampania $actualizarCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarCampania->ejecutar(
                $campania,
                (int) $datos['cliente_id'],
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

    /** @return Collection<int, string> */
    private function clientesDisponibles(): Collection
    {
        return DB::table('com_clientes')
            ->whereNull('deleted_at')
            ->orderBy('razon_social')
            ->pluck('razon_social', 'id')
            ->mapWithKeys(fn (string $razonSocial, int|string $id): array => [(int) $id => $razonSocial]);
    }

    /**
     * Etiquetas de cliente acotadas a la página actual del listado, mismo
     * criterio de lectura directa que `GastosController::etiquetasTrabajo()`.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasCliente(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('com_clientes')
            ->whereIn('id', $ids)
            ->pluck('razon_social', 'id')
            ->mapWithKeys(fn (string $razonSocial, int|string $id): array => [(int) $id => $razonSocial])
            ->all();
    }
}
