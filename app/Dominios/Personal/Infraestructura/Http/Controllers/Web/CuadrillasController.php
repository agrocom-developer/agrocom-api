<?php

namespace App\Dominios\Personal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Mantenimiento\Contratos\RecursoCatalogo;
use App\Dominios\Operaciones\Contratos\DatosResumenCuadrilla;
use App\Dominios\Operaciones\Contratos\DronCatalogo;
use App\Dominios\Operaciones\Contratos\LecturaDrones;
use App\Dominios\Operaciones\Contratos\LecturaResumenCuadrilla;
use App\Dominios\Personal\Aplicacion\ActualizarEquipoTrabajo;
use App\Dominios\Personal\Aplicacion\AgregarAccesorioEquipo;
use App\Dominios\Personal\Aplicacion\ArmarCuadrilla;
use App\Dominios\Personal\Aplicacion\AsignarIntegranteEquipo;
use App\Dominios\Personal\Aplicacion\AsignarRecursoEquipo;
use App\Dominios\Personal\Aplicacion\CambiarEstadoEquipoTrabajo;
use App\Dominios\Personal\Aplicacion\DesasignarIntegranteEquipo;
use App\Dominios\Personal\Aplicacion\DesasignarRecursoEquipo;
use App\Dominios\Personal\Aplicacion\EliminarEquipoTrabajo;
use App\Dominios\Personal\Aplicacion\ListarEquiposTrabajo;
use App\Dominios\Personal\Aplicacion\QuitarAccesorioEquipo;
use App\Dominios\Personal\Contratos\DatosRecursoEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\Excepciones\EquipoTrabajoDuplicado;
use App\Dominios\Personal\Dominio\Excepciones\RecursoEquipoInvalido;
use App\Dominios\Personal\Dominio\Excepciones\TransicionEquipoTrabajoNoPermitida;
use App\Dominios\Personal\Dominio\Excepciones\VigenciaEquipoSolapada;
use App\Dominios\Personal\Dominio\MaquinaEstados\TransicionesEquipoTrabajo;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use App\Dominios\Personal\Dominio\RolEquipo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoAccesorio;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Personal\Infraestructura\Http\Requests\ActualizarEquipoTrabajoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\AgregarAccesorioEquipoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\AsignarIntegranteEquipoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\AsignarRecursoEquipoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\CambiarEstadoEquipoTrabajoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\CrearEquipoTrabajoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\FinalizarIntegranteEquipoRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\FinalizarRecursoEquipoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/cuadrillas*` (tarea 72, HU-49, ADR 0015 punto
 * 3; alta de una sola vez y máquina de estados agregadas por la tarea
 * "cuadrillas-estadias", pedido del dueño 19/9/2026): alta y mantenimiento de
 * cuadrillas, con su ficha de integrantes, equipamiento (dron, vehículo,
 * generador, baterías) y accesorios.
 *
 * Cuatro permisos de grano fino
 * (`personal.equipo_trabajo.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Asignar/desasignar integrantes,
 * recursos y accesorios, y cambiar el estado, exigen `.editar`: no es un
 * permiso aparte, es parte de mantener la cuadrilla. Ninguna regla de negocio
 * acá: los casos de uso de `Aplicacion/` hacen el trabajo, incluida la
 * evaluación del solapamiento de vigencias y la máquina de estados.
 *
 * Los catálogos de otros módulos (drones, vehículos, generadores, baterías)
 * se resuelven SIEMPRE por los contratos de lectura ({@see LecturaDrones},
 * {@see LecturaEquipamiento}, ADR 0003 regla 2) — nunca por `DB::table`
 * directo. `DB::table('per_equipos_trabajo')` en `codigosDeEquipos()` es la
 * ÚNICA excepción: es la propia tabla de este módulo, no un cruce.
 */
final class CuadrillasController
{
    private const PERMISO_VER = 'personal.equipo_trabajo.ver';

    private const PERMISO_CREAR = 'personal.equipo_trabajo.crear';

    private const PERMISO_EDITAR = 'personal.equipo_trabajo.editar';

    private const PERMISO_ELIMINAR = 'personal.equipo_trabajo.eliminar';

    private const PORPAGINA_DETALLE = 8;

    /**
     * Tono de cada estado (mismo valor que `atoms/badge`): lo comparten el
     * badge de la columna "Estado" del listado, el paso de
     * `molecules/step-arrow` de la ficha de edición y el modal de cambio de
     * estado, para que los tres hablen con el mismo color (§6.3.4 de
     * docs/diseno/guia_pantalla_panel.md).
     */
    private const array TONO_POR_ESTADO = [
        'activo' => 'success',
        'inactivo' => 'neutral',
    ];

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaDrones $lecturaDrones,
        private readonly LecturaEquipamiento $lecturaEquipamiento,
    ) {}

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

        $equipoIds = $equipos->pluck('id')->all();

        return view('personal::pages.cuadrillas.index', [
            ...$this->autorizacion->cascara($request),
            'equipos' => $equipos,
            'etiquetasBase' => $this->etiquetasBase($equipos->pluck('base_id')->unique()->values()->all()),
            'basesDisponibles' => $this->basesDisponibles(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoEquipoTrabajo::cases(),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'integrantesPorEquipo' => $this->integrantesVigentesPorEquipo($equipoIds),
            'dronPorEquipo' => $this->dronVigentePorEquipo($equipoIds),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('personal::pages.cuadrillas.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'pilotosDisponibles' => $this->pilotosDisponibles(),
            'ayudantesDisponibles' => $this->ayudantesDisponibles(),
            'dronesDisponibles' => $this->dronesDisponibles(),
            'vehiculosDisponibles' => $this->vehiculosDisponibles(),
            'generadoresDisponibles' => $this->generadoresDisponibles(),
            'bateriasDisponibles' => $this->bateriasDisponibles(),
            'puedeCrearBase' => $this->autorizacion->tienePermiso($request, 'personal.base.crear'),
            'puedeCrearPersona' => $this->autorizacion->tienePermiso($request, 'personal.persona.crear'),
            'puedeCrearDron' => $this->autorizacion->tienePermiso($request, 'operaciones.dron.crear'),
            'puedeCrearVehiculo' => $this->autorizacion->tienePermiso($request, 'mantenimiento.vehiculo.crear'),
            'puedeCrearGenerador' => $this->autorizacion->tienePermiso($request, 'mantenimiento.generador.crear'),
            'puedeCrearBateria' => $this->autorizacion->tienePermiso($request, 'mantenimiento.bateria.crear'),
            // Memento de navegación (llegada desde «Crear cuadrilla» del alta
            // de Orden de Trabajo, u otro origen encadenado): ver
            // `RecordarOrigenNavegacion`.
            'volverA' => $request->query('volver_a'),
        ]);
    }

    public function store(CrearEquipoTrabajoRequest $request, ArmarCuadrilla $armarCuadrilla): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $resultado = $armarCuadrilla->ejecutar(
                (string) $datos['codigo'],
                $this->cadenaONull($datos['nombre'] ?? null),
                (int) $datos['base_id'],
                (string) $datos['desde'],
                $this->cadenaONull($datos['hasta'] ?? null),
                (int) $datos['piloto_id'],
                (int) $datos['ayudante_id'],
                $this->enteroONull($datos['ayudante2_id'] ?? null),
                (int) $datos['dron_id'],
                $this->enteroONull($datos['vehiculo_id'] ?? null),
                $this->enteroONull($datos['generador_id'] ?? null),
                array_map('intval', $datos['bateria_ids'] ?? []),
            );
        } catch (EquipoTrabajoDuplicado $excepcion) {
            return redirect()
                ->route('panel.cuadrillas.create')
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        } catch (RecursoEquipoInvalido|VigenciaEquipoSolapada $excepcion) {
            return redirect()
                ->route('panel.cuadrillas.create')
                ->withInput()
                ->withErrors(['dron_id' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        $redireccion = redirect()
            ->route('panel.cuadrillas.edit', $resultado->equipo)
            ->with('estado', __('personal.equipos_trabajo.creado'));

        if ($resultado->tieneAviso()) {
            $redireccion->with('aviso', __('personal.equipos_trabajo.aviso_solapamiento', [
                'equipos' => $this->codigosDeEquipos($resultado->equiposEnAviso),
            ]));
        }

        return $redireccion;
    }

    /**
     * Ficha de edición (HU-49, ampliada por "cuadrillas-estadias", 19/9/2026):
     * datos descriptivos, pasos de estado (`step-arrow`) y tres tablas de
     * detalle paginadas server-side (integrantes, equipamiento, accesorios),
     * cada una con su propio nombre de página (§6.3.4/§6.2 de
     * docs/diseno/guia_pantalla_panel.md).
     */
    public function edit(Request $request, EquipoTrabajo $equipoTrabajo, LecturaResumenCuadrilla $lecturaResumenCuadrilla): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $hoy = now()->toDateString();

        $integrantes = EquipoIntegrante::query()
            ->where('equipo_trabajo_id', $equipoTrabajo->id)
            ->with('persona')
            ->orderByRaw('CASE WHEN hasta IS NULL OR hasta >= ? THEN 0 ELSE 1 END', [$hoy])
            ->orderByDesc('desde')
            ->paginate(self::PORPAGINA_DETALLE, ['*'], 'integrantes_page')
            ->withQueryString();

        $equipamientoPaginado = EquipoRecurso::query()
            ->where('equipo_trabajo_id', $equipoTrabajo->id)
            ->orderByRaw('CASE WHEN hasta IS NULL OR hasta >= ? THEN 0 ELSE 1 END', [$hoy])
            ->orderByDesc('desde')
            ->paginate(self::PORPAGINA_DETALLE, ['*'], 'equipamiento_page')
            ->withQueryString();

        $etiquetasEquipamientoPagina = $this->etiquetasRecursoDeFilas($equipamientoPaginado->getCollection());

        $equipamiento = $equipamientoPaginado->through(fn (EquipoRecurso $recurso): array => [
            'id' => $recurso->id,
            'tipo' => $recurso->recurso_tipo->value,
            'etiqueta' => $etiquetasEquipamientoPagina["{$recurso->recurso_tipo->value}:{$recurso->recurso_id}"] ?? "#{$recurso->recurso_id}",
            'desde' => $recurso->desde->toDateString(),
            'hasta' => $recurso->hasta?->toDateString(),
            'vigente' => $recurso->hasta === null || $recurso->hasta->toDateString() >= $hoy,
        ]);

        $pasosEstado = PasosDeEstado::armar(
            ruta: [EstadoEquipoTrabajo::Activo, EstadoEquipoTrabajo::Inactivo],
            actual: $equipoTrabajo->estado,
            permitida: TransicionesEquipoTrabajo::permitida(...),
            tonos: self::TONO_POR_ESTADO,
            claveEtiqueta: 'personal.equipos_trabajo.estado',
            prefijoModal: 'cuadrilla-estado-modal',
            puedeCambiar: $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
        );

        return view('personal::pages.cuadrillas.edit', [
            ...$this->autorizacion->cascara($request),
            'equipo' => $equipoTrabajo,
            'basesDisponibles' => $this->basesDisponibles(),
            'personasDisponibles' => $this->personasDisponibles(),
            // `rolesEquipo` y no `roles`: ese nombre es de la cáscara del panel
            // (los roles del usuario para el selector del header).
            'rolesEquipo' => RolEquipo::cases(),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'puedeCrearBase' => $this->autorizacion->tienePermiso($request, 'personal.base.crear'),
            'puedeCrearPersona' => $this->autorizacion->tienePermiso($request, 'personal.persona.crear'),
            'opcionesEquipamiento' => [
                RecursoTipoEquipo::Dron->value => $this->dronesDisponibles()->all(),
                RecursoTipoEquipo::Vehiculo->value => $this->vehiculosDisponibles()->all(),
                RecursoTipoEquipo::Generador->value => $this->generadoresDisponibles()->all(),
                RecursoTipoEquipo::Bateria->value => $this->bateriasDisponibles()->all(),
            ],
            'pasosEstado' => $pasosEstado,
            'ayudaEstado' => PasosDeEstado::ayuda($pasosEstado, 'personal.equipos_trabajo.estado_ayuda'),
            'integrantes' => $integrantes,
            'equipamiento' => $equipamiento,
            'contadorIntegrantesVigentes' => $this->contarIntegrantesVigentes($equipoTrabajo->id, $hoy),
            'contadorBateriasVigentes' => $this->contarRecursosVigentes($equipoTrabajo->id, $hoy, RecursoTipoEquipo::Bateria),
            'resumenRelacionado' => $this->resumenRelacionado($equipoTrabajo, $request, $lecturaResumenCuadrilla),
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
                (string) $datos['desde'],
                $this->cadenaONull($datos['hasta'] ?? null),
            );
        } catch (EquipoTrabajoDuplicado $excepcion) {
            return redirect()
                ->route('panel.cuadrillas.edit', $equipoTrabajo)
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.cuadrillas.edit', $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.actualizado'));
    }

    /**
     * Cambio de estado (`activo ⇄ inactivo`, tarea "cuadrillas-estadias",
     * 19/9/2026, §6.3.4): pasa por `CambiarEstadoEquipoTrabajo`, que a su vez
     * pasa por la tabla de transiciones (invariante 7). Vuelve a la pantalla
     * de origen, nunca al listado.
     */
    public function cambiarEstado(CambiarEstadoEquipoTrabajoRequest $request, EquipoTrabajo $equipoTrabajo, CambiarEstadoEquipoTrabajo $cambiarEstadoEquipoTrabajo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $hacia = EstadoEquipoTrabajo::from((string) $request->validated('estado'));

        try {
            $cambiarEstadoEquipoTrabajo->ejecutar($equipoTrabajo, $hacia);
        } catch (TransicionEquipoTrabajoNoPermitida $excepcion) {
            return redirect()
                ->back(fallback: route('panel.cuadrillas.index'))
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->back(fallback: route('panel.cuadrillas.index'))
            ->with('estado', __('personal.equipos_trabajo.estado_cambiado'));
    }

    public function destroy(Request $request, EquipoTrabajo $equipoTrabajo, EliminarEquipoTrabajo $eliminarEquipoTrabajo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarEquipoTrabajo->ejecutar($equipoTrabajo);

        return redirect()
            ->route('panel.cuadrillas.index')
            ->with('estado', __('personal.equipos_trabajo.eliminado'));
    }

    /**
     * Ficha histórica (HU-49, punto 6): integrantes y recursos vigentes a
     * una fecha elegida (selector, default hoy) — tiene que poder responder
     * "quiénes lo integraban el 14 de marzo", no solo "quiénes lo integran
     * hoy". Usa el propio contrato de lectura `LecturaEquipoTrabajo`
     * (aunque esté DENTRO del mismo módulo que lo implementa) porque es
     * exactamente la pregunta que ese contrato existe para responder — nada
     * distinto de lo que necesitan otros módulos desde afuera.
     */
    public function show(Request $request, EquipoTrabajo $equipoTrabajo, LecturaEquipoTrabajo $lectura): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $fechaQuery = $request->string('fecha')->toString();
        $fecha = $fechaQuery !== '' ? $fechaQuery : now()->toDateString();

        $recursos = $lectura->recursosAFecha($equipoTrabajo->id, $fecha);

        return view('personal::pages.cuadrillas.show', [
            ...$this->autorizacion->cascara($request),
            'equipo' => $equipoTrabajo,
            'nombreBase' => $equipoTrabajo->base->nombre,
            'fecha' => $fecha,
            'integrantes' => $lectura->integrantesAFecha($equipoTrabajo->id, $fecha),
            'recursos' => $recursos,
            'etiquetasRecurso' => $this->etiquetasRecurso($recursos),
            'bateriasVigentes' => collect($recursos)->filter(
                fn (DatosRecursoEquipo $recurso): bool => $recurso->recursoTipo === RecursoTipoEquipo::Bateria->value,
            )->count(),
            'accesorios' => EquipoAccesorio::query()
                ->where('equipo_trabajo_id', $equipoTrabajo->id)
                ->with('accesorio')
                ->orderBy('id')
                ->get(),
            'personasDisponibles' => $this->personasDisponibles(),
            'roles' => RolEquipo::cases(),
            'tiposRecurso' => RecursoTipoEquipo::cases(),
            'dronesDisponibles' => $this->dronesDisponibles(),
            'vehiculosDisponibles' => $this->vehiculosDisponibles(),
            'generadoresDisponibles' => $this->generadoresDisponibles(),
            'bateriasDisponibles' => $this->bateriasDisponibles(),
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
            return $this->volverAOrigen($request, $equipoTrabajo)
                ->withErrors(['persona_id' => $excepcion->getMessage()]);
        }

        $redireccion = $this->volverAOrigen($request, $equipoTrabajo)
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

        return $this->volverAOrigen($request, $equipoTrabajo)
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
            return $this->volverAOrigen($request, $equipoTrabajo)
                ->withErrors(['recurso_id' => $excepcion->getMessage()]);
        }

        $redireccion = $this->volverAOrigen($request, $equipoTrabajo)
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

        return $this->volverAOrigen($request, $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.recurso_finalizado'));
    }

    /**
     * `POST /panel/cuadrillas/{equipoTrabajo}/accesorios` (tarea
     * "cuadrillas-estadias", 19/9/2026).
     */
    public function agregarAccesorio(AgregarAccesorioEquipoRequest $request, EquipoTrabajo $equipoTrabajo, AgregarAccesorioEquipo $agregarAccesorioEquipo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        $agregarAccesorioEquipo->ejecutar(
            $equipoTrabajo,
            $this->enteroONull($datos['accesorio_id'] ?? null),
            $this->cadenaONull($datos['nombre_nuevo'] ?? null),
            (int) $datos['cantidad'],
            $this->cadenaONull($datos['observacion'] ?? null),
        );

        return $this->volverAOrigen($request, $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.accesorio_agregado'));
    }

    /**
     * `DELETE /panel/cuadrillas/{equipoTrabajo}/accesorios/{accesorio}`
     * (tarea "cuadrillas-estadias", 19/9/2026). `$accesorio` es la fila de
     * `per_equipo_accesorios` (el accesorio ASIGNADO a esta cuadrilla), no la
     * fila del catálogo `per_accesorios`.
     */
    public function quitarAccesorio(Request $request, EquipoTrabajo $equipoTrabajo, EquipoAccesorio $accesorio, QuitarAccesorioEquipo $quitarAccesorioEquipo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);
        abort_unless($accesorio->equipo_trabajo_id === $equipoTrabajo->id, 404);

        $quitarAccesorioEquipo->ejecutar($accesorio);

        return $this->volverAOrigen($request, $equipoTrabajo)
            ->with('estado', __('personal.equipos_trabajo.accesorio_quitado'));
    }

    /**
     * Vuelve a la pantalla de ORIGEN de la acción de detalle (asignar/quitar
     * integrante, recurso o accesorio) — corrección 19/9/2026: antes siempre
     * volvía a `show`, aunque la acción se hubiera disparado desde un modal
     * de `edit`. Un campo oculto `origen` (`edit`/lo que sea distinto de
     * `edit` cae a `show`, el comportamiento de siempre) lo decide cada
     * formulario.
     */
    private function volverAOrigen(Request $request, EquipoTrabajo $equipoTrabajo): RedirectResponse
    {
        if ($request->input('origen') === 'edit') {
            return redirect()->route('panel.cuadrillas.edit', $equipoTrabajo);
        }

        $fecha = $request->string('fecha')->toString();

        return redirect()->route('panel.cuadrillas.show', array_filter([
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
     * Etiquetas legibles de una lista de recursos ya resueltos a DTO (la
     * ficha histórica `show()`, vía `LecturaEquipoTrabajo::recursosAFecha()`).
     *
     * @param  list<DatosRecursoEquipo>  $recursos
     * @return array<string, string> clave `"{tipo}:{id}"` => etiqueta.
     */
    private function etiquetasRecurso(array $recursos): array
    {
        $idsPorTipo = collect($recursos)->groupBy('recursoTipo')->map(
            fn (Collection $grupo) => $grupo->pluck('recursoId')->unique()->values()->all(),
        )->all();

        return $this->etiquetasPorTipoYId($idsPorTipo);
    }

    /**
     * Misma resolución que `etiquetasRecurso()`, para una página de la tabla
     * de equipamiento de `edit()` (filas Eloquent, no DTO).
     *
     * @param  Collection<int, EquipoRecurso>  $filas
     * @return array<string, string> clave `"{tipo}:{id}"` => etiqueta.
     */
    private function etiquetasRecursoDeFilas(Collection $filas): array
    {
        $idsPorTipo = $filas->groupBy(fn (EquipoRecurso $recurso): string => $recurso->recurso_tipo->value)
            ->map(fn (Collection $grupo) => $grupo->pluck('recurso_id')->unique()->values()->all())
            ->all();

        return $this->etiquetasPorTipoYId($idsPorTipo);
    }

    /**
     * Núcleo compartido: por cada tipo de recurso, resuelve sus ids contra el
     * contrato de lectura del módulo dueño (`LecturaDrones` en Operaciones;
     * `LecturaEquipamiento` en Mantenimiento) — nunca `DB::table` cruzado
     * (ADR 0003 regla 3, corregido el 19/9/2026).
     *
     * @param  array<string, list<int>>  $idsPorTipo
     * @return array<string, string> clave `"{tipo}:{id}"` => etiqueta.
     */
    private function etiquetasPorTipoYId(array $idsPorTipo): array
    {
        $etiquetas = [];

        $idsDron = $idsPorTipo[RecursoTipoEquipo::Dron->value] ?? [];
        if ($idsDron !== []) {
            foreach ($this->lecturaDrones->porIds($idsDron) as $id => $dron) {
                /** @var DronCatalogo $dron */
                $etiquetas[RecursoTipoEquipo::Dron->value.":{$id}"] = $dron->etiqueta();
            }
        }

        $tiposMantenimiento = [
            RecursoTipoEquipo::Vehiculo->value => LecturaEquipamiento::TIPO_VEHICULO,
            RecursoTipoEquipo::Generador->value => LecturaEquipamiento::TIPO_GENERADOR,
            RecursoTipoEquipo::Bateria->value => LecturaEquipamiento::TIPO_BATERIA,
        ];

        foreach ($tiposMantenimiento as $tipoEquipo => $tipoContrato) {
            $ids = $idsPorTipo[$tipoEquipo] ?? [];

            if ($ids === []) {
                continue;
            }

            foreach ($this->lecturaEquipamiento->porIds($tipoContrato, $ids) as $id => $recurso) {
                /** @var RecursoCatalogo $recurso */
                $etiquetas["{$tipoEquipo}:{$id}"] = $recurso->etiqueta();
            }
        }

        return $etiquetas;
    }

    /**
     * Integrantes vigentes HOY de cada equipo de la página del listado,
     * resueltos a nombre (piloto y ayudantes) — una sola consulta para toda
     * la página, sin N+1.
     *
     * @param  list<int>  $equipoIds
     * @return array<int, array{piloto: string|null, ayudantes: list<string>}>
     */
    private function integrantesVigentesPorEquipo(array $equipoIds): array
    {
        if ($equipoIds === []) {
            return [];
        }

        $hoy = now()->toDateString();

        return EquipoIntegrante::query()
            ->whereIn('equipo_trabajo_id', $equipoIds)
            ->where('desde', '<=', $hoy)
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
            ->with('persona')
            ->orderBy('desde')
            ->get()
            ->groupBy('equipo_trabajo_id')
            ->map(fn (Collection $filas): array => [
                'piloto' => $filas->first(fn (EquipoIntegrante $fila): bool => $fila->rol_equipo === RolEquipo::Piloto)?->persona->nombre,
                'ayudantes' => $filas->filter(fn (EquipoIntegrante $fila): bool => $fila->rol_equipo === RolEquipo::Auxiliar)
                    ->pluck('persona.nombre')
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Dron vigente HOY de cada equipo de la página del listado, ya resuelto a
     * etiqueta — una consulta a `per_equipo_recursos` más una a
     * `LecturaDrones::porIds()`, sin N+1.
     *
     * @param  list<int>  $equipoIds
     * @return array<int, string>
     */
    private function dronVigentePorEquipo(array $equipoIds): array
    {
        if ($equipoIds === []) {
            return [];
        }

        $hoy = now()->toDateString();

        $recursos = EquipoRecurso::query()
            ->whereIn('equipo_trabajo_id', $equipoIds)
            ->where('recurso_tipo', RecursoTipoEquipo::Dron->value)
            ->where('desde', '<=', $hoy)
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
            ->get();

        $catalogo = $this->lecturaDrones->porIds($recursos->pluck('recurso_id')->unique()->values()->all());

        $porEquipo = [];

        foreach ($recursos as $recurso) {
            $dron = $catalogo[$recurso->recurso_id] ?? null;
            $porEquipo[$recurso->equipo_trabajo_id] ??= $dron?->etiqueta();
        }

        return $porEquipo;
    }

    private function contarIntegrantesVigentes(int $equipoId, string $hoy): int
    {
        return EquipoIntegrante::query()
            ->where('equipo_trabajo_id', $equipoId)
            ->where('desde', '<=', $hoy)
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
            ->count();
    }

    private function contarRecursosVigentes(int $equipoId, string $hoy, RecursoTipoEquipo $tipo): int
    {
        return EquipoRecurso::query()
            ->where('equipo_trabajo_id', $equipoId)
            ->where('recurso_tipo', $tipo->value)
            ->where('desde', '<=', $hoy)
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
            ->count();
    }

    /**
     * Resumen relacionado del aside de `edit()` (solo edición, §6.3.1 de la
     * guía de pantalla): tres tarjetas, cada una gateada por el permiso del
     * módulo AJENO que describe — nunca el de esta pantalla, que ya se
     * verificó para poder estar acá.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(EquipoTrabajo $equipoTrabajo, Request $request, LecturaResumenCuadrilla $lecturaResumenCuadrilla): array
    {
        $resumen = [];

        $puedeVerEstadias = $this->autorizacion->tienePermiso($request, 'operaciones.estadia.ver');
        $puedeCrearEstadia = $this->autorizacion->tienePermiso($request, 'operaciones.estadia.crear');
        $puedeVerTrabajos = $this->autorizacion->tienePermiso($request, 'operaciones.trabajo.ver');

        $datos = $puedeVerEstadias || $puedeCrearEstadia || $puedeVerTrabajos
            ? $lecturaResumenCuadrilla->deCuadrilla($equipoTrabajo->id)
            : null;

        // 1) Estadías en hacienda (Operaciones) — las rutas las crea otro
        // módulo en paralelo: `Route::has()` como resguardo mientras no
        // existan todas.
        if (($puedeVerEstadias || $puedeCrearEstadia) && $datos instanceof DatosResumenCuadrilla) {
            $acciones = [];

            if ($puedeVerEstadias && Route::has('panel.estadias.index')) {
                $acciones[] = [
                    'label' => __('personal.equipos_trabajo.aside_estadias_accion_ver'),
                    'href' => route('panel.estadias.index', ['equipo_trabajo_id' => $equipoTrabajo->id]),
                    'icono' => 'list',
                ];
            }

            if ($puedeCrearEstadia && Route::has('panel.estadias.create')) {
                $acciones[] = [
                    'label' => __('personal.equipos_trabajo.aside_estadias_accion_crear'),
                    'href' => route('panel.estadias.create', ['equipo_trabajo_id' => $equipoTrabajo->id]),
                    'icono' => 'add',
                ];
            }

            $resumen[] = [
                'titulo' => __('personal.equipos_trabajo.aside_estadias_titulo'),
                'icono' => 'holiday_village',
                'tieneDatos' => $datos->estadiasTotal > 0,
                'items' => [
                    ['label' => __('personal.equipos_trabajo.aside_estadias_total'), 'value' => (string) $datos->estadiasTotal, 'mono' => true],
                    [
                        'label' => __('personal.equipos_trabajo.aside_estadias_en_curso'),
                        'value' => (string) $datos->estadiasEnCurso,
                        'mono' => true,
                        'variant' => $datos->estadiasEnCurso > 0 ? 'success' : 'neutral',
                    ],
                ],
                'vacioTitulo' => __('personal.equipos_trabajo.aside_estadias_vacio_titulo'),
                'vacioDetalle' => __('personal.equipos_trabajo.aside_estadias_vacio_detalle'),
                'acciones' => $acciones,
            ];
        }

        // 2) Órdenes de trabajo (Operaciones).
        if ($puedeVerTrabajos && $datos instanceof DatosResumenCuadrilla) {
            $resumen[] = [
                'titulo' => __('personal.equipos_trabajo.aside_trabajos_titulo'),
                'icono' => 'agriculture',
                'tieneDatos' => $datos->trabajosTotal > 0,
                'items' => [
                    ['label' => __('personal.equipos_trabajo.aside_trabajos_total'), 'value' => (string) $datos->trabajosTotal, 'mono' => true],
                    [
                        'label' => __('personal.equipos_trabajo.aside_trabajos_abiertos'),
                        'value' => (string) $datos->trabajosAbiertos,
                        'mono' => true,
                        'variant' => $datos->trabajosAbiertos > 0 ? 'success' : 'neutral',
                    ],
                ],
                'vacioTitulo' => __('personal.equipos_trabajo.aside_trabajos_vacio_titulo'),
                'vacioDetalle' => __('personal.equipos_trabajo.aside_trabajos_vacio_detalle'),
                'acciones' => [[
                    'label' => __('personal.equipos_trabajo.aside_trabajos_accion'),
                    'href' => route('panel.trabajos.index'),
                    'icono' => 'list',
                ]],
            ];
        }

        // 3) Base de la cuadrilla (propio del módulo, mismo criterio de
        // gating que las otras dos: el permiso de lo que la acción hace).
        if ($this->autorizacion->tienePermiso($request, 'personal.base.editar')) {
            $resumen[] = [
                'titulo' => __('personal.equipos_trabajo.aside_base_titulo'),
                'icono' => 'location_on',
                'tieneDatos' => true,
                'items' => [
                    ['label' => __('personal.equipos_trabajo.aside_base_nombre'), 'value' => $equipoTrabajo->base->nombre],
                    ['label' => __('personal.equipos_trabajo.aside_base_ubicacion'), 'value' => $equipoTrabajo->base->ubicacion ?? __('personal.bases.sin_ubicacion')],
                ],
                'vacioTitulo' => __('personal.equipos_trabajo.aside_base_titulo'),
                'vacioDetalle' => '',
                'acciones' => [[
                    'label' => __('personal.equipos_trabajo.aside_base_accion'),
                    'href' => route('panel.bases.edit', $equipoTrabajo->base),
                    'icono' => 'edit',
                ]],
            ];
        }

        return $resumen;
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

    /**
     * Personas que pueden ocupar el puesto de piloto en el alta de una sola
     * vez: activas, con rol operativo `piloto` (§ "Alta de una sola vez",
     * tarea "cuadrillas-estadias").
     *
     * @return Collection<int, string>
     */
    private function pilotosDisponibles(): Collection
    {
        return PerPersona::query()
            ->where('activo', true)
            ->where('rol', RolOperativoPersona::Piloto->value)
            ->orderBy('nombre')
            ->pluck('nombre', 'id');
    }

    /**
     * Personas que pueden ocupar un puesto de ayudante: activas, CUALQUIER
     * rol operativo — a propósito más ancho que `pilotosDisponibles()`: un
     * piloto sin asignar puede cubrir un puesto de ayudante, así que figura
     * en las dos listas.
     *
     * @return Collection<int, string>
     */
    private function ayudantesDisponibles(): Collection
    {
        return $this->personasDisponibles();
    }

    /** @return Collection<int, string> */
    private function dronesDisponibles(): Collection
    {
        return collect($this->lecturaDrones->disponibles())
            ->mapWithKeys(fn (DronCatalogo $dron): array => [$dron->id => $dron->etiqueta()]);
    }

    /** @return Collection<int, string> */
    private function vehiculosDisponibles(): Collection
    {
        return collect($this->lecturaEquipamiento->vehiculosDisponibles())
            ->mapWithKeys(fn (RecursoCatalogo $recurso): array => [$recurso->id => $recurso->etiqueta()]);
    }

    /** @return Collection<int, string> */
    private function generadoresDisponibles(): Collection
    {
        return collect($this->lecturaEquipamiento->generadoresDisponibles())
            ->mapWithKeys(fn (RecursoCatalogo $recurso): array => [$recurso->id => $recurso->etiqueta()]);
    }

    /** @return Collection<int, string> */
    private function bateriasDisponibles(): Collection
    {
        return collect($this->lecturaEquipamiento->bateriasDisponibles())
            ->mapWithKeys(fn (RecursoCatalogo $recurso): array => [$recurso->id => $recurso->etiqueta()]);
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
