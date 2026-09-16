<?php

namespace App\Dominios\Personal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Personal\Aplicacion\ActualizarEquipoTrabajo;
use App\Dominios\Personal\Aplicacion\AsignarIntegranteEquipo;
use App\Dominios\Personal\Aplicacion\AsignarRecursoEquipo;
use App\Dominios\Personal\Aplicacion\CrearEquipoTrabajo;
use App\Dominios\Personal\Aplicacion\DesasignarIntegranteEquipo;
use App\Dominios\Personal\Aplicacion\DesasignarRecursoEquipo;
use App\Dominios\Personal\Aplicacion\EliminarEquipoTrabajo;
use App\Dominios\Personal\Aplicacion\ListarEquiposTrabajo;
use App\Dominios\Personal\Contratos\DatosRecursoEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\Excepciones\EquipoTrabajoDuplicado;
use App\Dominios\Personal\Dominio\Excepciones\RecursoEquipoInvalido;
use App\Dominios\Personal\Dominio\Excepciones\VigenciaEquipoSolapada;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use App\Dominios\Personal\Dominio\RolEquipo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Personal\Infraestructura\Http\Requests\ActualizarEquipoTrabajoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\AsignarIntegranteEquipoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\AsignarRecursoEquipoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\CrearEquipoTrabajoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\FinalizarIntegranteEquipoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\FinalizarRecursoEquipoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/equipos-trabajo*` (tarea 72, HU-49, ADR 0015
 * punto 3): alta y mantenimiento de equipos de trabajo, con su ficha de
 * integrantes y recursos vigentes a una fecha elegida.
 *
 * Cuatro permisos de grano fino
 * (`personal.equipo_trabajo.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Asignar/desasignar integrantes y
 * recursos exige `.editar`: no es un permiso aparte, es parte de mantener el
 * equipo. Ninguna regla de negocio acá: los casos de uso de `Aplicacion/`
 * hacen el trabajo, incluida la evaluación del solapamiento de vigencias.
 *
 * `dronesDisponibles()`/`vehiculosDisponibles()`/`generadoresDisponibles()`
 * se arman con `DB::table(...)` (ADR 0003 regla 3, mismo criterio que
 * `OrdenesMantenimientoController`), sin importar los modelos Eloquent de
 * `Operaciones`/`Mantenimiento`.
 */
final class EquiposTrabajoController
{
    private const PERMISO_VER = 'personal.equipo_trabajo.ver';

    private const PERMISO_CREAR = 'personal.equipo_trabajo.crear';

    private const PERMISO_EDITAR = 'personal.equipo_trabajo.editar';

    private const PERMISO_ELIMINAR = 'personal.equipo_trabajo.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarEquiposTrabajo $listarEquiposTrabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $baseQuery = $request->string('base_id')->toString();
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;
        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoEquipoTrabajo::tryFrom($estadoQuery) : null;

        $equipos = $listarEquiposTrabajo->ejecutar(
            busqueda: $busqueda !== '' ? $busqueda : null,
            baseId: $baseId,
            estado: $estado?->value,
        );

        return view('personal::pages.equipos-trabajo.index', [
            ...$this->autorizacion->cascara($request),
            'equipos' => $equipos,
            'etiquetasBase' => $this->etiquetasBase($equipos->pluck('base_id')->unique()->values()->all()),
            'basesDisponibles' => $this->basesDisponibles(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId, 'estado' => $estado?->value],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('personal::pages.equipos-trabajo.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoEquipoTrabajo::cases(),
        ]);
    }

    public function store(CrearEquipoTrabajoRequest $request, CrearEquipoTrabajo $crearEquipoTrabajo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $equipoTrabajo = $crearEquipoTrabajo->ejecutar(
                (string) $datos['codigo'],
                $this->cadenaONull($datos['nombre'] ?? null),
                (int) $datos['base_id'],
                EstadoEquipoTrabajo::from((string) $datos['estado']),
                (string) $datos['desde'],
                $this->cadenaONull($datos['hasta'] ?? null),
            );
        } catch (EquipoTrabajoDuplicado $excepcion) {
            return redirect()
                ->route('panel.equipos-trabajo.create')
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.equipos-trabajo.edit', $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.creado'));
    }

    public function edit(Request $request, EquipoTrabajo $equipoTrabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('personal::pages.equipos-trabajo.edit', [
            ...$this->autorizacion->cascara($request),
            'equipo' => $equipoTrabajo,
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoEquipoTrabajo::cases(),
        ]);
    }

    public function update(ActualizarEquipoTrabajoRequest $request, EquipoTrabajo $equipoTrabajo, ActualizarEquipoTrabajo $actualizarEquipoTrabajo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarEquipoTrabajo->ejecutar(
                $equipoTrabajo,
                (string) $datos['codigo'],
                $this->cadenaONull($datos['nombre'] ?? null),
                (int) $datos['base_id'],
                EstadoEquipoTrabajo::from((string) $datos['estado']),
                (string) $datos['desde'],
                $this->cadenaONull($datos['hasta'] ?? null),
            );
        } catch (EquipoTrabajoDuplicado $excepcion) {
            return redirect()
                ->route('panel.equipos-trabajo.edit', $equipoTrabajo)
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.equipos-trabajo.edit', $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.actualizado'));
    }

    public function destroy(Request $request, EquipoTrabajo $equipoTrabajo, EliminarEquipoTrabajo $eliminarEquipoTrabajo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarEquipoTrabajo->ejecutar($equipoTrabajo);

        return redirect()
            ->route('panel.equipos-trabajo.index')
            ->with('estado', __('personal.equipos_trabajo.eliminado'));
    }

    /**
     * Ficha del equipo (HU-49, punto 6): integrantes y recursos vigentes a
     * una fecha elegida (selector, default hoy) — tiene que poder responder
     * "quiénes lo integraban el 14 de marzo", no solo "quiénes lo integran
     * hoy". Usa el propio contrato de lectura `LecturaEquipoTrabajo`
     * (aunque esté DENTRO del mismo módulo que lo implementa) porque es
     * exactamente la pregunta que ese contrato existe para responder — nada
     * distinto de lo que necesitarán las tareas 73/74 desde afuera.
     */
    public function show(Request $request, EquipoTrabajo $equipoTrabajo, LecturaEquipoTrabajo $lectura): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $fechaQuery = $request->string('fecha')->toString();
        $fecha = $fechaQuery !== '' ? $fechaQuery : now()->toDateString();

        $recursos = $lectura->recursosAFecha($equipoTrabajo->id, $fecha);

        return view('personal::pages.equipos-trabajo.show', [
            ...$this->autorizacion->cascara($request),
            'equipo' => $equipoTrabajo,
            'nombreBase' => $equipoTrabajo->base->nombre,
            'fecha' => $fecha,
            'integrantes' => $lectura->integrantesAFecha($equipoTrabajo->id, $fecha),
            'recursos' => $recursos,
            'etiquetasRecurso' => $this->etiquetasRecurso($recursos),
            'personasDisponibles' => $this->personasDisponibles(),
            'roles' => RolEquipo::cases(),
            'tiposRecurso' => RecursoTipoEquipo::cases(),
            'dronesDisponibles' => $this->dronesDisponibles(),
            'vehiculosDisponibles' => $this->vehiculosDisponibles(),
            'generadoresDisponibles' => $this->generadoresDisponibles(),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
        ]);
    }

    public function asignarIntegrante(AsignarIntegranteEquipoRequest $request, EquipoTrabajo $equipoTrabajo, AsignarIntegranteEquipo $asignarIntegranteEquipo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $resultado = $asignarIntegranteEquipo->ejecutar(
                $equipoTrabajo,
                (int) $datos['persona_id'],
                RolEquipo::from((string) $datos['rol_equipo']),
                (string) $datos['desde'],
                $this->cadenaONull($datos['hasta'] ?? null),
            );
        } catch (VigenciaEquipoSolapada $excepcion) {
            return redirect()
                ->route('panel.equipos-trabajo.show', $equipoTrabajo)
                ->withErrors(['persona_id' => $excepcion->getMessage()]);
        }

        $redireccion = $this->volverAFicha($request, $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.integrante_asignado'));

        if ($resultado->tieneAviso()) {
            $redireccion->with('aviso', __('personal.equipos_trabajo.aviso_solapamiento', [
                'equipos' => $this->codigosDeEquipos($resultado->equiposEnAviso),
            ]));
        }

        return $redireccion;
    }

    public function desasignarIntegrante(FinalizarIntegranteEquipoRequest $request, EquipoTrabajo $equipoTrabajo, EquipoIntegrante $integrante, DesasignarIntegranteEquipo $desasignarIntegranteEquipo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);
        abort_unless($integrante->equipo_trabajo_id === $equipoTrabajo->id, 404);

        $desasignarIntegranteEquipo->ejecutar($integrante, (string) $request->validated('hasta'));

        return $this->volverAFicha($request, $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.integrante_finalizado'));
    }

    public function asignarRecurso(AsignarRecursoEquipoRequest $request, EquipoTrabajo $equipoTrabajo, AsignarRecursoEquipo $asignarRecursoEquipo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $resultado = $asignarRecursoEquipo->ejecutar(
                $equipoTrabajo,
                RecursoTipoEquipo::from((string) $datos['recurso_tipo']),
                (int) $datos['recurso_id'],
                (string) $datos['desde'],
                $this->cadenaONull($datos['hasta'] ?? null),
            );
        } catch (RecursoEquipoInvalido|VigenciaEquipoSolapada $excepcion) {
            return redirect()
                ->route('panel.equipos-trabajo.show', $equipoTrabajo)
                ->withErrors(['recurso_id' => $excepcion->getMessage()]);
        }

        $redireccion = $this->volverAFicha($request, $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.recurso_asignado'));

        if ($resultado->tieneAviso()) {
            $redireccion->with('aviso', __('personal.equipos_trabajo.aviso_solapamiento', [
                'equipos' => $this->codigosDeEquipos($resultado->equiposEnAviso),
            ]));
        }

        return $redireccion;
    }

    public function desasignarRecurso(FinalizarRecursoEquipoRequest $request, EquipoTrabajo $equipoTrabajo, EquipoRecurso $recurso, DesasignarRecursoEquipo $desasignarRecursoEquipo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);
        abort_unless($recurso->equipo_trabajo_id === $equipoTrabajo->id, 404);

        $desasignarRecursoEquipo->ejecutar($recurso, (string) $request->validated('hasta'));

        return $this->volverAFicha($request, $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.recurso_finalizado'));
    }

    private function volverAFicha(Request $request, EquipoTrabajo $equipoTrabajo): RedirectResponse
    {
        $fecha = $request->string('fecha')->toString();

        return redirect()->route('panel.equipos-trabajo.show', array_filter([
            'equipoTrabajo' => $equipoTrabajo->id,
            'fecha' => $fecha !== '' ? $fecha : null,
        ]));
    }

    /** @param  list<int>  $idsEquipo */
    private function codigosDeEquipos(array $idsEquipo): string
    {
        return DB::table('per_equipos_trabajo')
            ->whereIn('id', $idsEquipo)
            ->orderBy('codigo')
            ->pluck('codigo')
            ->implode(', ');
    }

    /**
     * Etiquetas legibles de los recursos de la ficha, resueltas por tipo en
     * un único `whereIn` por tabla (mismo criterio de agrupación que
     * `OrdenesMantenimientoController::etiquetasEquipo()`).
     *
     * @param  list<DatosRecursoEquipo>  $recursos
     * @return array<string, string> clave `"{tipo}:{id}"` => etiqueta.
     */
    private function etiquetasRecurso(array $recursos): array
    {
        $idsPorTipo = collect($recursos)->groupBy('recursoTipo')->map(
            fn (Collection $grupo) => $grupo->pluck('recursoId')->unique()->values()->all(),
        );

        $tablaPorTipo = [
            'dron' => 'ope_drones',
            'vehiculo' => 'man_vehiculos',
            'generador' => 'man_generadores',
        ];

        $etiquetas = [];

        foreach ($tablaPorTipo as $tipo => $tabla) {
            $ids = $idsPorTipo->get($tipo, []);

            if ($ids === []) {
                continue;
            }

            foreach (DB::table($tabla)->whereIn('id', $ids)->pluck('identificador', 'id') as $id => $identificador) {
                $etiquetas["{$tipo}:{$id}"] = (string) $identificador;
            }
        }

        return $etiquetas;
    }

    /** @return Collection<int, string> */
    private function basesDisponibles(): Collection
    {
        return PerBase::query()->orderBy('nombre')->pluck('nombre', 'id');
    }

    /**
     * Etiquetas legibles para la columna "Base" del listado.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasBase(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return PerBase::query()->whereIn('id', $ids)->pluck('nombre', 'id')->map(fn ($valor) => (string) $valor)->all();
    }

    /** @return Collection<int, string> */
    private function personasDisponibles(): Collection
    {
        return PerPersona::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id');
    }

    /** @return Collection<int, string> */
    private function dronesDisponibles(): Collection
    {
        return DB::table('ope_drones')
            ->whereNull('deleted_at')
            ->orderBy('identificador')
            ->pluck('identificador', 'id');
    }

    /** @return Collection<int, string> */
    private function vehiculosDisponibles(): Collection
    {
        return DB::table('man_vehiculos')
            ->whereNull('deleted_at')
            ->where('estado', 'activo')
            ->orderBy('identificador')
            ->pluck('identificador', 'id');
    }

    /** @return Collection<int, string> */
    private function generadoresDisponibles(): Collection
    {
        return DB::table('man_generadores')
            ->whereNull('deleted_at')
            ->where('estado', 'activo')
            ->orderBy('identificador')
            ->pluck('identificador', 'id');
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
