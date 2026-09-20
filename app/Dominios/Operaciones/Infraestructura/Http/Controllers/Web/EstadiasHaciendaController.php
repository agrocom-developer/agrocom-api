<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Contratos\LecturaPropiedades;
use App\Dominios\Comercial\Contratos\PropiedadCatalogo;
use App\Dominios\Compartido\Infraestructura\Http\PasosDeEstado;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Mantenimiento\Contratos\RecursoCatalogo;
use App\Dominios\Operaciones\Aplicacion\ActualizarEstadiaHacienda;
use App\Dominios\Operaciones\Aplicacion\EliminarEstadiaHacienda;
use App\Dominios\Operaciones\Aplicacion\FinalizarEstadiaHacienda;
use App\Dominios\Operaciones\Aplicacion\ListarEstadiasHacienda;
use App\Dominios\Operaciones\Aplicacion\RegistrarEstadiaHacienda;
use App\Dominios\Operaciones\Dominio\EstadoEstadia;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\EstadiaAbiertaExistente;
use App\Dominios\Operaciones\Dominio\Excepciones\EstadiaYaFinalizada;
use App\Dominios\Operaciones\Dominio\Excepciones\SalidaAnteriorAEntrada;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesEstadia;
use App\Dominios\Operaciones\Dominio\TipoAlojamiento;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ActualizarEstadiaRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\FinalizarEstadiaRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\RegistrarEstadiaRequest;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `/panel/estadias*` (HU-51, tarea 74; reforma 19/9/2026): la oficina ahora
 * registra, edita, finaliza y da de baja estadías del equipo en cada
 * hacienda desde el panel — hasta hoy la pantalla era de solo lectura (la
 * estadía nacía únicamente en la app de campo, todavía sin distribuir). El
 * sync (`POST /api/sync`) sigue funcionando igual, sin tocarse.
 *
 * Cuatro permisos de grano fino (`operaciones.estadia.ver`/`.crear`/
 * `.editar`/`.eliminar`), verificados DENTRO del controlador contra el ROL
 * ACTIVO vía {@see AutorizacionPanelWeb} — mismo criterio que el resto del
 * panel. `.editar` cubre también finalizar (registrar la salida). Ninguna
 * regla de negocio acá: los casos de uso de `Aplicacion/` hacen el trabajo,
 * y la transición de estado la resuelve `TransicionesEstadia` (invariante 7).
 *
 * Los selects de `equipo_trabajo_id`/`propiedad_id`/`vehiculo_id` (y las
 * etiquetas de la tabla) se arman por `Contratos/` (ADR 0003 regla 2) —
 * `Personal\Contratos\LecturaEquipoTrabajo`, `Comercial\Contratos\LecturaPropiedades`
 * y `Mantenimiento\Contratos\LecturaEquipamiento` — nunca `DB::table` directo
 * sobre tablas de otro módulo (como hacía la versión anterior de esta clase).
 *
 * `LecturaEquipoTrabajo` no ofrece "listar todas las cuadrillas" ni "buscar
 * por texto" (solo `vigentesAFecha()`, `porIds()`, `integrantesAFecha()`) —
 * por eso el universo de cuadrillas que ofrece el filtro/select de esta
 * pantalla es "vigentes hoy" ∪ "ya tienen alguna estadía registrada" (esto
 * último resuelto contra la propia tabla de Operaciones, `ope_estadias_hacienda`,
 * que si es de este módulo), y la búsqueda por código/nombre de cuadrilla
 * solo mira ese mismo universo — ver `equiposDisponibles()`/
 * `equipoIdsQueCoinciden()` más abajo. `LecturaPropiedades`, en cambio, SÍ
 * ofrece `disponibles()`/`idsQueCoinciden()` (contrato escrito para esta
 * tarea), así que el catálogo de propiedades es el completo.
 */
final class EstadiasHaciendaController
{
    private const PERMISO_VER = 'operaciones.estadia.ver';

    private const PERMISO_CREAR = 'operaciones.estadia.crear';

    private const PERMISO_EDITAR = 'operaciones.estadia.editar';

    private const PERMISO_ELIMINAR = 'operaciones.estadia.eliminar';

    /**
     * Tono de cada estado, definido UNA vez (§6.3.4 de la guía de pantalla):
     * lo comparten el badge del listado, el paso de `molecules/step-arrow` y
     * el modal de confirmación de "Finalizar".
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        // «En curso» en `warning` es el tono que esta pantalla ya tenía
        // decidido desde HU-51: no se reelige acá.
        'en_curso' => 'warning',
        'finalizada' => 'success',
    ];

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaEquipoTrabajo $equipos,
        private readonly LecturaPropiedades $propiedades,
        private readonly LecturaEquipamiento $equipamiento,
    ) {}

    public function index(Request $request, ListarEstadiasHacienda $listarEstadias): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $q = $request->string('q')->toString();
        $busqueda = $q !== '' ? $q : null;
        $desde = $request->filled('desde') ? $request->string('desde')->toString() : null;
        $hasta = $request->filled('hasta') ? $request->string('hasta')->toString() : null;
        $equipoTrabajoId = $request->integer('equipo_trabajo_id') ?: null;
        $propiedadId = $request->integer('propiedad_id') ?: null;
        $estado = $request->string('estado')->toString() ?: null;
        $tipoAlojamiento = $request->string('tipo_alojamiento')->toString() ?: null;

        $equipoIdsCoincidentes = $busqueda !== null ? $this->equipoIdsQueCoinciden($busqueda) : [];
        $propiedadIdsCoincidentes = $busqueda !== null ? $this->propiedades->idsQueCoinciden($busqueda) : [];

        $estadias = $listarEstadias->ejecutar(
            $desde,
            $hasta,
            $equipoTrabajoId,
            $propiedadId,
            $estado,
            $tipoAlojamiento,
            $busqueda,
            $equipoIdsCoincidentes,
            $propiedadIdsCoincidentes,
        );

        $equipoIdsPagina = $estadias->pluck('equipo_trabajo_id')->unique()->values()->all();
        $propiedadIdsPagina = $estadias->pluck('propiedad_id')->unique()->values()->all();
        $vehiculoIdsPagina = $estadias->pluck('vehiculo_id')->filter()->unique()->values()->all();

        $diasPorEquipo = $listarEstadias->diasEfectivosPorEquipo($desde, $hasta, $equipoTrabajoId, $propiedadId, $estado, $tipoAlojamiento, $busqueda, $equipoIdsCoincidentes, $propiedadIdsCoincidentes);
        $diasPorPropiedad = $listarEstadias->diasEfectivosPorPropiedad($desde, $hasta, $equipoTrabajoId, $propiedadId, $estado, $tipoAlojamiento, $busqueda, $equipoIdsCoincidentes, $propiedadIdsCoincidentes);

        return view('operaciones::pages.estadias.index', [
            ...$this->autorizacion->cascara($request),
            'estadias' => $estadias,
            // Etiquetas SOLO para los ids que se pintan —los de la página y los del
            // desglose de días—, nunca el catálogo entero (§6.2 de la guía de pantalla).
            'etiquetasEquipo' => $this->etiquetasEquipoPorIds(array_values(array_unique([...$equipoIdsPagina, ...array_keys($diasPorEquipo)]))),
            'etiquetasPropiedad' => $this->etiquetasPropiedadPorIds(array_values(array_unique([...$propiedadIdsPagina, ...array_keys($diasPorPropiedad)]))),
            'etiquetasVehiculo' => $this->etiquetasVehiculoPorIds($vehiculoIdsPagina),
            'equiposDisponibles' => $this->equiposDisponibles(),
            'propiedadesDisponibles' => $this->propiedadesDisponiblesMapa(),
            'resumen' => $listarEstadias->resumen($desde, $hasta, $equipoTrabajoId, $propiedadId, $estado, $tipoAlojamiento, $busqueda, $equipoIdsCoincidentes, $propiedadIdsCoincidentes),
            'diasPorEquipo' => $diasPorEquipo,
            'diasPorPropiedad' => $diasPorPropiedad,
            'filtros' => [
                'q' => $busqueda,
                'desde' => $desde,
                'hasta' => $hasta,
                'equipo_trabajo_id' => $equipoTrabajoId,
                'propiedad_id' => $propiedadId,
                'estado' => $estado,
                'tipo_alojamiento' => $tipoAlojamiento,
            ],
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'estadosFiltro' => EstadoEstadia::cases(),
            'tiposAlojamientoFiltro' => TipoAlojamiento::cases(),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    /**
     * `?equipo_trabajo_id=`/`?propiedad_id=` preseleccionan la cuadrilla o la
     * propiedad (accesos directos desde la ficha de la cuadrilla y de la
     * propiedad — el memento de navegación `?volver_a=&volver_texto=` lo
     * resuelve `RecordarOrigenNavegacion`/`molecules/boton-volver` solos, sin
     * nada que este controlador tenga que hacer).
     */
    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('operaciones::pages.estadias.create', [
            ...$this->autorizacion->cascara($request),
            'equiposDisponibles' => $this->equiposDisponibles(),
            'propiedadesDisponibles' => $this->propiedadesDisponiblesMapa(),
            'vehiculosDisponibles' => $this->vehiculosDisponiblesMapa(),
            'tiposAlojamiento' => TipoAlojamiento::cases(),
            'equipoTrabajoIdPreseleccionado' => $request->integer('equipo_trabajo_id') ?: null,
            'propiedadIdPreseleccionado' => $request->integer('propiedad_id') ?: null,
            // Solo para OFRECER el acceso rápido «Nueva» de cada select: el
            // alta la autoriza el módulo dueño de cada pantalla.
            'puedeCrearCuadrilla' => $this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.crear'),
            'puedeCrearPropiedad' => $this->autorizacion->tienePermiso($request, 'comercial.propiedad.crear'),
        ]);
    }

    public function store(RegistrarEstadiaRequest $request, RegistrarEstadiaHacienda $registrarEstadia): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $estadia = $registrarEstadia->ejecutar(
                (int) $datos['equipo_trabajo_id'],
                (int) $datos['propiedad_id'],
                (string) $datos['entrada'],
                TipoAlojamiento::from((string) $datos['tipo_alojamiento']),
                $this->enteroONull($datos['vehiculo_id'] ?? null),
                $this->cadenaONull($datos['observacion'] ?? null),
                $this->cadenaONull($datos['salida'] ?? null),
            );
        } catch (EquipoTrabajoNoVigente $excepcion) {
            return $this->volverACrear($request)->withErrors(['equipo_trabajo_id' => $excepcion->getMessage()]);
        } catch (SalidaAnteriorAEntrada $excepcion) {
            return $this->volverACrear($request)->withErrors(['salida' => $excepcion->getMessage()]);
        } catch (EstadiaAbiertaExistente $excepcion) {
            return $this->volverACrear($request)->withErrors(['equipo_trabajo_id' => $excepcion->getMessage()]);
        }

        // Se queda en la ficha de edición de la estadía recién creada (§6.3.2
        // de la guía de pantalla), no vuelve al listado.
        return redirect()
            ->route('panel.estadias.edit', $estadia)
            ->with('estado', __('operaciones.estadias.creada'));
    }

    public function edit(Request $request, EstadiaHacienda $estadia): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $actual = $estadia->estado();
        $puedeCambiarEstado = $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR);

        $pasosEstado = PasosDeEstado::armar(
            ruta: [EstadoEstadia::EnCurso, EstadoEstadia::Finalizada],
            actual: $actual,
            permitida: TransicionesEstadia::permitida(...),
            tonos: self::TONO_POR_ESTADO,
            claveEtiqueta: 'operaciones.estadias.estado',
            prefijoModal: 'estadia-estado-modal',
            puedeCambiar: $puedeCambiarEstado,
        );

        return view('operaciones::pages.estadias.edit', [
            ...$this->autorizacion->cascara($request),
            'estadia' => $estadia,
            'pasosEstado' => $pasosEstado,
            'ayudaEstado' => PasosDeEstado::ayuda($pasosEstado, 'operaciones.estadias.estado_ayuda'),
            'soloLectura' => $actual === EstadoEstadia::Finalizada,
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            // La cuadrilla de una estadía no se cambia: el select va
            // deshabilitado y solo necesita la etiqueta de la suya.
            'equiposDisponibles' => $this->etiquetasEquipoPorIds([$estadia->equipo_trabajo_id]),
            'propiedadesDisponibles' => $this->propiedadesDisponiblesMapa(),
            'vehiculosDisponibles' => $this->vehiculosDisponiblesMapa(),
            'tiposAlojamiento' => TipoAlojamiento::cases(),
            'resumenRelacionado' => $this->resumenRelacionado($estadia, $request),
        ]);
    }

    public function update(ActualizarEstadiaRequest $request, EstadiaHacienda $estadia, ActualizarEstadiaHacienda $actualizarEstadia): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarEstadia->ejecutar(
                $estadia,
                (int) $datos['propiedad_id'],
                (string) $datos['entrada'],
                TipoAlojamiento::from((string) $datos['tipo_alojamiento']),
                $this->enteroONull($datos['vehiculo_id'] ?? null),
                $this->cadenaONull($datos['observacion'] ?? null),
            );
        } catch (EstadiaYaFinalizada $excepcion) {
            return redirect()
                ->route('panel.estadias.edit', $estadia)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.estadias.edit', $estadia)
            ->with('estado', __('operaciones.estadias.actualizada'));
    }

    /**
     * `POST /panel/estadias/{estadia}/finalizar` (§6.3.4 de la guía de
     * pantalla): el paso de la ficha de edición. Vuelve a la pantalla de
     * origen (`redirect()->back()`), no al listado.
     */
    public function finalizar(FinalizarEstadiaRequest $request, EstadiaHacienda $estadia, FinalizarEstadiaHacienda $finalizarEstadia): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        try {
            $finalizarEstadia->ejecutar($estadia, (string) $request->validated('salida'));
        } catch (EstadiaYaFinalizada|SalidaAnteriorAEntrada $excepcion) {
            return redirect()
                ->back(fallback: route('panel.estadias.edit', $estadia))
                ->withErrors(['salida' => $excepcion->getMessage()]);
        }

        return redirect()
            ->back(fallback: route('panel.estadias.edit', $estadia))
            ->with('estado', __('operaciones.estadias.finalizada'));
    }

    public function destroy(Request $request, EstadiaHacienda $estadia, EliminarEstadiaHacienda $eliminarEstadia): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarEstadia->ejecutar($estadia);

        return redirect()
            ->route('panel.estadias.index')
            ->with('estado', __('operaciones.estadias.eliminada'));
    }

    private function volverACrear(Request $request): RedirectResponse
    {
        return redirect()
            ->route('panel.estadias.create', $request->only(['equipo_trabajo_id', 'propiedad_id']))
            ->withInput();
    }

    /**
     * Resumen relacionado del aside (solo edición, §6.3.1 de la guía de
     * pantalla): las tres tarjetas más cercanas a una estadía — la cuadrilla,
     * la propiedad y la propia duración — cada una gateada por el permiso
     * del módulo AJENO que describe (nunca el de esta pantalla, que ya se
     * verificó arriba). "Duración" es de la propia estadía, sin permiso
     * ajeno que verificar.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(EstadiaHacienda $estadia, Request $request): array
    {
        $resumen = [];

        $resumenCuadrilla = $this->resumenCuadrilla($estadia, $request);
        if ($resumenCuadrilla !== null) {
            $resumen[] = $resumenCuadrilla;
        }

        $resumenPropiedad = $this->resumenPropiedad($estadia, $request);
        if ($resumenPropiedad !== null) {
            $resumen[] = $resumenPropiedad;
        }

        $resumen[] = $this->resumenDuracion($estadia);

        return $resumen;
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}|null */
    private function resumenCuadrilla(EstadiaHacienda $estadia, Request $request): ?array
    {
        $puedeVer = $this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.ver');
        $puedeEditar = $this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.editar');

        if (! $puedeVer && ! $puedeEditar) {
            return null;
        }

        $equipo = $this->equipos->porIds([$estadia->equipo_trabajo_id])[$estadia->equipo_trabajo_id] ?? null;
        $integrantes = $this->equipos->integrantesAFecha($estadia->equipo_trabajo_id, $estadia->entrada->toDateString());

        $items = [];
        if ($equipo !== null) {
            $items[] = ['label' => __('operaciones.estadias.aside_cuadrilla_codigo'), 'value' => $this->etiquetaEquipo($equipo)];
        }
        foreach ($integrantes as $integrante) {
            $items[] = [
                'label' => __('operaciones.estadias.aside_cuadrilla_rol.'.$integrante->rolEquipo),
                'value' => $integrante->nombrePersona,
            ];
        }

        $acciones = [];
        if ($puedeEditar) {
            $acciones[] = ['label' => __('operaciones.estadias.aside_cuadrilla_accion'), 'href' => route('panel.cuadrillas.edit', $estadia->equipo_trabajo_id), 'icono' => 'groups'];
        } elseif ($puedeVer) {
            $acciones[] = ['label' => __('operaciones.estadias.aside_cuadrilla_accion'), 'href' => route('panel.cuadrillas.show', $estadia->equipo_trabajo_id), 'icono' => 'groups'];
        }

        return [
            'titulo' => __('operaciones.estadias.aside_cuadrilla_titulo'),
            'icono' => 'groups',
            'tieneDatos' => $equipo !== null,
            'items' => $items,
            'vacioTitulo' => __('operaciones.estadias.aside_cuadrilla_vacio_titulo'),
            'vacioDetalle' => __('operaciones.estadias.aside_cuadrilla_vacio_detalle'),
            'acciones' => $acciones,
        ];
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}|null */
    private function resumenPropiedad(EstadiaHacienda $estadia, Request $request): ?array
    {
        $puedeVer = $this->autorizacion->tienePermiso($request, 'comercial.propiedad.ver');
        $puedeEditar = $this->autorizacion->tienePermiso($request, 'comercial.propiedad.editar');

        if (! $puedeVer && ! $puedeEditar) {
            return null;
        }

        $propiedad = $this->propiedades->porIds([$estadia->propiedad_id])[$estadia->propiedad_id] ?? null;

        $items = $propiedad !== null ? [
            ['label' => __('operaciones.estadias.aside_propiedad_nombre'), 'value' => $propiedad->nombre],
            ['label' => __('operaciones.estadias.aside_propiedad_cliente'), 'value' => $propiedad->clienteNombre],
        ] : [];

        $acciones = $puedeEditar && $propiedad !== null
            ? [['label' => __('operaciones.estadias.aside_propiedad_accion'), 'href' => route('panel.propiedades.edit', $propiedad->id), 'icono' => 'domain']]
            : [];

        return [
            'titulo' => __('operaciones.estadias.aside_propiedad_titulo'),
            'icono' => 'domain',
            'tieneDatos' => $propiedad !== null,
            'items' => $items,
            'vacioTitulo' => __('operaciones.estadias.aside_propiedad_vacio_titulo'),
            'vacioDetalle' => __('operaciones.estadias.aside_propiedad_vacio_detalle'),
            'acciones' => $acciones,
        ];
    }

    /** @return array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>} */
    private function resumenDuracion(EstadiaHacienda $estadia): array
    {
        $items = [
            ['label' => __('operaciones.estadias.aside_duracion_entrada'), 'value' => $estadia->entrada->format('d/m/Y H:i'), 'mono' => true],
        ];

        if ($estadia->salida !== null) {
            $dias = $estadia->entrada->diffInSeconds($estadia->salida) / 86400;

            $items[] = ['label' => __('operaciones.estadias.aside_duracion_salida'), 'value' => $estadia->salida->format('d/m/Y H:i'), 'mono' => true];
            $items[] = ['label' => __('operaciones.estadias.aside_duracion_dias'), 'value' => number_format($dias, 2, ',', '.'), 'mono' => true];
        } else {
            $diasEnCurso = (int) $estadia->entrada->diffInDays(CarbonImmutable::now());

            $items[] = [
                'label' => __('operaciones.estadias.aside_duracion_en_curso'),
                'value' => trans_choice('operaciones.estadias.aside_duracion_en_curso_valor', $diasEnCurso, ['dias' => $diasEnCurso]),
                'mono' => true,
            ];
        }

        return [
            'titulo' => __('operaciones.estadias.aside_duracion_titulo'),
            'icono' => 'schedule',
            'tieneDatos' => true,
            'items' => $items,
            'vacioTitulo' => __('operaciones.estadias.aside_duracion_titulo'),
            'vacioDetalle' => '',
            'acciones' => [],
        ];
    }

    private function etiquetaEquipo(DatosEquipoTrabajo $equipo): string
    {
        return $equipo->nombre !== null ? "{$equipo->codigo} — {$equipo->nombre}" : $equipo->codigo;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasEquipoPorIds(array $ids): array
    {
        return collect($this->equipos->porIds($ids))
            ->map(fn (DatosEquipoTrabajo $equipo): string => $this->etiquetaEquipo($equipo))
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasPropiedadPorIds(array $ids): array
    {
        return collect($this->propiedades->porIds($ids))
            ->map(fn (PropiedadCatalogo $propiedad): string => $propiedad->etiqueta())
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasVehiculoPorIds(array $ids): array
    {
        return collect($this->equipamiento->porIds(LecturaEquipamiento::TIPO_VEHICULO, $ids))
            ->map(fn (RecursoCatalogo $vehiculo): string => $vehiculo->etiqueta())
            ->all();
    }

    /**
     * Cuadrillas que puede ofrecer el filtro y el select de alta: vigentes
     * HOY (pueden recibir una estadía nueva) ∪ las que ya tienen alguna
     * estadía registrada (para poder filtrar el histórico aunque la
     * cuadrilla ya no esté vigente) — ver docblock de la clase.
     *
     * @return array<int, string>
     */
    private function equiposDisponibles(): array
    {
        $idsVigentesHoy = array_map(
            static fn (DatosEquipoTrabajo $equipo): int => $equipo->id,
            $this->equipos->vigentesAFecha(now()->toDateString()),
        );

        $ids = array_values(array_unique([...$idsVigentesHoy, ...$this->equipoIdsEnEstadias()]));

        return $this->etiquetasEquipoPorIds($ids);
    }

    /** @return array<int, string> */
    private function propiedadesDisponiblesMapa(): array
    {
        return collect($this->propiedades->disponibles())
            ->mapWithKeys(fn (PropiedadCatalogo $propiedad): array => [$propiedad->id => $propiedad->etiqueta()])
            ->all();
    }

    /** @return array<int, string> */
    private function vehiculosDisponiblesMapa(): array
    {
        return collect($this->equipamiento->vehiculosDisponibles())
            ->mapWithKeys(fn (RecursoCatalogo $vehiculo): array => [$vehiculo->id => $vehiculo->etiqueta()])
            ->all();
    }

    /**
     * Ids de cuadrilla cuyo código o nombre coincide con `$texto`, dentro del
     * universo de cuadrillas que YA tienen alguna estadía (ver docblock de la
     * clase: `LecturaEquipoTrabajo` no ofrece búsqueda por texto).
     *
     * @return list<int>
     */
    private function equipoIdsQueCoinciden(string $texto): array
    {
        $ids = $this->equipoIdsEnEstadias();

        if ($ids === []) {
            return [];
        }

        $texto = mb_strtolower($texto);

        return collect($this->equipos->porIds($ids))
            ->filter(fn (DatosEquipoTrabajo $equipo): bool => str_contains(mb_strtolower($equipo->codigo), $texto)
                || ($equipo->nombre !== null && str_contains(mb_strtolower($equipo->nombre), $texto)))
            ->map(fn (DatosEquipoTrabajo $equipo): int => $equipo->id)
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function equipoIdsEnEstadias(): array
    {
        return EstadiaHacienda::query()
            ->distinct()
            ->pluck('equipo_trabajo_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
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
