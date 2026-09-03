<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Aplicacion\CalcularDisponibleAnticipo;
use App\Dominios\Finanzas\Aplicacion\EliminarAnticipo;
use App\Dominios\Finanzas\Aplicacion\ListarAnticipos;
use App\Dominios\Finanzas\Aplicacion\RegistrarAnticipo;
use App\Dominios\Finanzas\Dominio\Excepciones\AnticipoExcedeTope;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\CrearAnticipoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/DELETE /panel/anticipos*` (HU-29, tarea 41): "como encargado,
 * quiero registrar anticipos validando el tope, para no adelantar más de lo
 * devengado". ABM acotado, sin edición ni máquina de estados (a diferencia
 * de HU-34, rendiciones, que sí la va a tener): solo alta y baja.
 *
 * Tres permisos de grano fino
 * (`finanzas.anticipo.ver`/`.crear`/`.eliminar`), verificados DENTRO del
 * controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} — mismo
 * criterio que el resto del panel. Ninguna regla de negocio acá: el tope lo
 * valida `Aplicacion/RegistrarAnticipo`.
 *
 * El select de `persona_id` se arma con `DB::table` directo (ADR 0003 regla
 * 3, mismo criterio que los selects de `OrdenesController` y
 * `UsuariosController::personasSinCuenta()`), sin importar el modelo
 * Eloquent de `Personal`.
 */
final class AnticiposController
{
    private const PERMISO_VER = 'finanzas.anticipo.ver';

    private const PERMISO_CREAR = 'finanzas.anticipo.crear';

    private const PERMISO_ELIMINAR = 'finanzas.anticipo.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarAnticipos $listarAnticipos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $personaId = $request->integer('persona_id') ?: null;
        $periodo = $request->string('periodo')->toString();

        $anticipos = $listarAnticipos->ejecutar($personaId, $periodo !== '' ? $periodo : null);

        return view('finanzas::pages.anticipos.index', [
            ...$this->autorizacion->cascara($request),
            'anticipos' => $anticipos,
            'etiquetasPersona' => $this->etiquetasPersona($anticipos->pluck('persona_id')->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'personasDisponibles' => $this->personasDisponibles(),
            'filtros' => ['persona_id' => $personaId, 'periodo' => $periodo],
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    public function create(Request $request, CalcularDisponibleAnticipo $calcularDisponible): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $personasDisponibles = $this->personasDisponibles();
        $personaConsultaId = $request->integer('persona_id') ?: null;

        $consultaDisponible = null;
        if ($personaConsultaId !== null && $personasDisponibles->has($personaConsultaId)) {
            $consultaDisponible = [
                'personaId' => $personaConsultaId,
                'personaNombre' => $personasDisponibles->get($personaConsultaId),
                'disponible' => $calcularDisponible->ejecutar($personaConsultaId, Carbon::now()->toDateString()),
            ];
        }

        return view('finanzas::pages.anticipos.create', [
            ...$this->autorizacion->cascara($request),
            'personasDisponibles' => $personasDisponibles,
            'consultaDisponible' => $consultaDisponible,
        ]);
    }

    public function store(CrearAnticipoRequest $request, RegistrarAnticipo $registrarAnticipo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $registrarAnticipo->ejecutar(
                (int) $datos['persona_id'],
                (string) $datos['monto'],
                (string) $datos['fecha'],
                $this->cadenaONull($datos['motivo'] ?? null),
            );
        } catch (AnticipoExcedeTope $excepcion) {
            return redirect()->back()->withInput()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.anticipos.index')
            ->with('estado', __('finanzas.anticipos.creado'));
    }

    public function destroy(Request $request, Anticipo $anticipo, EliminarAnticipo $eliminarAnticipo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarAnticipo->ejecutar($anticipo);

        return redirect()
            ->route('panel.anticipos.index')
            ->with('estado', __('finanzas.anticipos.eliminado'));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /** @return Collection<int, string> */
    private function personasDisponibles(): Collection
    {
        return DB::table('per_personas')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->mapWithKeys(fn (string $nombre, int|string $id): array => [(int) $id => $nombre]);
    }

    /**
     * Etiquetas legibles para la columna "Persona" del listado, mismo
     * criterio de lectura directa por `DB::table` que `personasDisponibles()`
     * — ver docblock de clase. Una persona borrada lógicamente después de
     * registrar un anticipo queda fuera del mapa a propósito: la vista cae
     * al `#id` crudo, no hace falta un JOIN con `deleted_at` nulo cuando lo
     * único que se pinta es una etiqueta histórica.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasPersona(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_personas')
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }
}
