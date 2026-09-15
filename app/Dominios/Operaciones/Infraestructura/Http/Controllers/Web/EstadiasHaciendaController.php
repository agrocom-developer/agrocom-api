<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\ListarEstadiasHacienda;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET /panel/estadias` (HU-51, tarea 74): consulta de estadías del equipo en
 * cada hacienda, con filtro por rango de fechas, equipo y propiedad, y el
 * total de días efectivos por equipo y por propiedad dentro de ese mismo
 * filtro. Solo lectura — la estadía nace en la app de campo (`POST
 * /api/sync`), el panel nunca abre ni cierra una (ver "Qué NO hacer" del
 * prompt de la tarea).
 *
 * Un único permiso (`operaciones.estadia.ver`) gatea toda la pantalla,
 * verificado DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que `TrabajosController`.
 *
 * Los selects de `equipo_trabajo_id`/`propiedad_id` (y las etiquetas de la
 * tabla) se arman con `DB::table` directo (ADR 0003 regla 3, mismo criterio
 * que `GastosController::equiposDisponibles()`), sin importar los modelos
 * Eloquent de `Personal`/`Comercial`/`Mantenimiento`.
 */
final class EstadiasHaciendaController
{
    private const PERMISO = 'operaciones.estadia.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarEstadiasHacienda $listarEstadias): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $desde = $request->filled('desde') ? $request->string('desde')->toString() : null;
        $hasta = $request->filled('hasta') ? $request->string('hasta')->toString() : null;
        $equipoTrabajoId = $request->integer('equipo_trabajo_id') ?: null;
        $propiedadId = $request->integer('propiedad_id') ?: null;

        $equiposDisponibles = $this->equiposDisponibles();
        $propiedadesDisponibles = $this->propiedadesDisponibles();

        return view('operaciones::pages.estadias.index', [
            ...$this->autorizacion->cascara($request),
            'estadias' => $listarEstadias->ejecutar($desde, $hasta, $equipoTrabajoId, $propiedadId),
            'etiquetasEquipo' => $equiposDisponibles->all(),
            'etiquetasPropiedad' => $propiedadesDisponibles->all(),
            'etiquetasVehiculo' => $this->vehiculosDisponibles()->all(),
            'equiposDisponibles' => $equiposDisponibles,
            'propiedadesDisponibles' => $propiedadesDisponibles,
            'diasPorEquipo' => $listarEstadias->diasEfectivosPorEquipo($desde, $hasta, $equipoTrabajoId, $propiedadId),
            'diasPorPropiedad' => $listarEstadias->diasEfectivosPorPropiedad($desde, $hasta, $equipoTrabajoId, $propiedadId),
            'filtros' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'equipo_trabajo_id' => $equipoTrabajoId,
                'propiedad_id' => $propiedadId,
            ],
        ]);
    }

    /** @return Collection<int, string> */
    private function equiposDisponibles(): Collection
    {
        return DB::table('per_equipos_trabajo')
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->mapWithKeys(fn (object $equipo): array => [
                (int) $equipo->id => $equipo->nombre !== null ? "{$equipo->codigo} — {$equipo->nombre}" : $equipo->codigo,
            ]);
    }

    /** @return Collection<int, string> */
    private function propiedadesDisponibles(): Collection
    {
        return DB::table('com_propiedades')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->mapWithKeys(fn (string $nombre, int|string $id): array => [(int) $id => $nombre]);
    }

    /** @return Collection<int, string> */
    private function vehiculosDisponibles(): Collection
    {
        return DB::table('man_vehiculos')
            ->whereNull('deleted_at')
            ->orderBy('identificador')
            ->pluck('identificador', 'id')
            ->mapWithKeys(fn (string $identificador, int|string $id): array => [(int) $id => $identificador]);
    }
}
