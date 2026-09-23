<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Contratos\AvanceClientePanel;
use App\Dominios\Comercial\Contratos\EstadoCuentaContratoPanel;
use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use App\Dominios\Comercial\Contratos\LecturaPropiedades;
use App\Dominios\Comercial\Contratos\LotePanel;
use App\Dominios\Finanzas\Contratos\LecturaPanelFinanzas;
use App\Dominios\Inventario\Contratos\LecturaPanelInventario;
use App\Dominios\Inventario\Contratos\StockPanel;
use App\Dominios\Operaciones\Contratos\AlertaPanel;
use App\Dominios\Operaciones\Contratos\EquipoPersonaPanel;
use App\Dominios\Operaciones\Contratos\EvidenciaPanel;
use App\Dominios\Operaciones\Contratos\GranularidadVuelos;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Operaciones\Contratos\OrdenAplicacionPanel;
use App\Dominios\Operaciones\Contratos\ResumenEquipoTrabajoPanel;
use App\Dominios\Operaciones\Contratos\ResumenLotePanel;
use App\Dominios\Operaciones\Contratos\SesionPanel;
use App\Dominios\Operaciones\Contratos\TandaDeEquipoPanel;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\DatosIntegranteEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Dominio\SeccionDashboard;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Caso de uso: armar el dashboard del ROL ACTIVO (tarea 67).
 *
 * Su única responsabilidad es DECIDIR y COMPONER: qué secciones puede ver
 * este rol ({@see SeccionDashboard}) y pedirle a cada módulo dueño su parte
 * por contrato. No consulta ninguna tabla — ni siquiera las de `Seguridad`.
 * Los nombres que cruzan módulos los resuelve {@see CatalogoNombresPanel} y
 * el GeoJSON lo arma {@see ArmarMapaOperativo}.
 *
 * Por qué "un dashboard por rol" y no una vista por rol: el rol activo no
 * elige plantilla, elige subconjunto. Un dueño ve diez secciones y un piloto
 * dos, pero es el mismo armado y la misma página — no hay cuatro Blades que
 * se desincronizan cuando cambia una sección.
 *
 * Los permisos se evalúan contra el ROL ACTIVO
 * (`SecUser::tienePermisoEnRol()`), jamás contra la unión de los roles del
 * usuario (invariante 10 de CLAUDE.md): un dueño operando como piloto ve el
 * dashboard del piloto.
 */
final class ArmarDashboard
{
    private const SESIONES_RECIENTES = 8;

    private const COLA_VALIDACION = 8;

    private const DIAS_SERIE_HECTAREAS = 14;

    private const EVIDENCIAS_GALERIA = 8;

    private const STOCK_CRITICO = 5;

    private const CONTRATOS_AVANCE = 5;

    private const ALERTAS = 5;

    private const DEVENGOS = 10;

    private const ANTICIPOS = 5;

    private const ESTADO_CUENTAS = 5;

    private const SEMANAS_RESUMEN_VUELOS = 8;

    private const MESES_RESUMEN_VUELOS = 6;

    /** Órdenes ya terminadas que acompañan a las abiertas en el estado de órdenes (tarea 138). */
    private const ORDENES_TERMINADAS = 4;

    private const PERMISO_CREAR_CAMPANIA = 'campania.campania.crear';

    public function __construct(
        private readonly LecturaPanelOperaciones $operaciones,
        private readonly LecturaPanelComercial $comercial,
        private readonly LecturaPanelInventario $inventario,
        private readonly LecturaPanelFinanzas $finanzas,
        private readonly LecturaCampania $campania,
        private readonly CatalogoNombresPanel $nombres,
        private readonly ArmarMapaOperativo $mapa,
        private readonly LecturaEquipoTrabajo $cuadrillas,
        private readonly LecturaPropiedades $propiedades,
        private readonly CatalogoEquipamientoPanel $equipamiento,
    ) {}

    /**
     * @return array{secciones: array<string, mixed>, visibles: list<string>, hayCampaniaAbierta: bool, puedeCrearCampania: bool}
     */
    public function ejecutar(SecUser $usuario, int $idRolActivo): array
    {
        $secciones = [];

        foreach (SeccionDashboard::cases() as $seccion) {
            if (! $this->puedeVer($usuario, $idRolActivo, $seccion)) {
                continue;
            }

            $contenido = $this->contenido($seccion, $usuario->persona_id);

            // Una sección sin contenido no se muestra vacía: se omite. Es el
            // mismo criterio que la tarea 60 aplicó a los badges del menú —
            // un cero decorativo dice menos que la ausencia.
            if ($contenido !== null) {
                $secciones[$seccion->value] = $contenido;
            }
        }

        return [
            'secciones' => $secciones,
            'visibles' => array_keys($secciones),
            // Solo los usa `_sin-secciones.blade.php` cuando el tablero queda
            // vacío, para distinguir la causa más común en una instalación
            // nueva —ninguna campaña abierta todavía, por eso no hay
            // contratos, órdenes ni sesiones que mostrar— del caso genérico
            // de un rol sin secciones habilitadas.
            'hayCampaniaAbierta' => $this->campania->abiertas() !== [],
            'puedeCrearCampania' => $usuario->tienePermisoEnRol(self::PERMISO_CREAR_CAMPANIA, $idRolActivo),
        ];
    }

    private function puedeVer(SecUser $usuario, int $idRolActivo, SeccionDashboard $seccion): bool
    {
        if ($seccion->requierePersona() && $usuario->persona_id === null) {
            return false;
        }

        $permiso = $seccion->permiso();

        return $permiso === null || $usuario->tienePermisoEnRol($permiso, $idRolActivo);
    }

    private function contenido(SeccionDashboard $seccion, ?int $personaId): mixed
    {
        return match ($seccion) {
            SeccionDashboard::Mapa => $this->mapa->ejecutar($this->nombres->lotes()),
            SeccionDashboard::DistribucionSesiones => $this->distribucion(),
            SeccionDashboard::HectareasPorDia => $this->hectareasPorDia(),
            SeccionDashboard::ColaValidacion => $this->sesiones($this->operaciones->colaValidacion(self::COLA_VALIDACION)),
            SeccionDashboard::ResumenPorLote => $this->resumenPorLote(),
            SeccionDashboard::Multimedia => $this->multimedia(),
            SeccionDashboard::Pausas => $this->pausas(),
            SeccionDashboard::DiasEnHacienda => $this->diasEnHacienda(),
            SeccionDashboard::Stock => $this->stock(),
            SeccionDashboard::AvanceClientes => $this->avanceClientes(),
            SeccionDashboard::Alertas => $this->alertas(),
            SeccionDashboard::MisSesiones => $this->misSesiones($personaId),
            SeccionDashboard::MisEquipos => $this->misEquipos($personaId),
            SeccionDashboard::MiLiquidacion => $this->miLiquidacion($personaId),
            SeccionDashboard::EstadoCuentas => $this->estadoCuentas(),
            SeccionDashboard::TrabajosPorEquipo => $this->trabajosPorEquipo(),
            SeccionDashboard::ProgresoCampania => $this->progresoCampania(),
            SeccionDashboard::EstadoOrdenesAplicacion => $this->estadoOrdenesAplicacion(),
            SeccionDashboard::ResumenVuelos => $this->resumenVuelos(),
            SeccionDashboard::RecursosEnUso => $this->recursosEnUso(),
            SeccionDashboard::OrdenesTrabajoPorCuadrilla => $this->ordenesTrabajoPorCuadrilla(),
            SeccionDashboard::OrdenesConEquipamiento => $this->ordenesConEquipamiento(),
        };
    }

    /**
     * Agrupamiento de secciones en tabs, por rol activo (tarea 135). Es una
     * TABLA, no una vista por rol: el rol activo no elige plantilla, elige
     * qué claves de {@see SeccionDashboard} se agrupan bajo qué pestaña —
     * mismo criterio que {@see SeccionDashboard::permiso()}. Cada tab se
     * muestra solo si alguna de sus claves quedó en `visibles` (lo decide la
     * vista, con el mismo `array_intersect` de siempre).
     *
     * El caso `default` es el agrupamiento original de la tarea 67
     * (resumen/mapa/lotes/multimedia): sigue siendo el de cualquier rol que
     * todavía no tenga el suyo propio (la 139 agrega el del administrador).
     *
     * Piloto y auxiliar comparten el agrupamiento (tarea 137): ven las mismas
     * tres secciones —todas acotadas a su `persona_id`— y solo cambia de
     * quién son. `auxiliar` es la clave de `sec_role`; el panel lo rotula
     * «Ayudante» (`seguridad.rol.meta.auxiliar.nombre`).
     *
     * @return list<array{id: string, label: string, claves: list<string>}>
     */
    public function tabsPara(string $rolClave): array
    {
        return match ($rolClave) {
            'dueno' => [
                [
                    'id' => 'estado-cuentas',
                    'label' => __('seguridad.dashboard.tab_estado_cuentas'),
                    'claves' => [SeccionDashboard::EstadoCuentas->value],
                ],
                [
                    'id' => 'trabajos-equipo',
                    'label' => __('seguridad.dashboard.tab_trabajos_equipo'),
                    'claves' => [SeccionDashboard::TrabajosPorEquipo->value],
                ],
                [
                    'id' => 'progreso-campania',
                    'label' => __('seguridad.dashboard.tab_progreso_campania'),
                    'claves' => [SeccionDashboard::ProgresoCampania->value],
                ],
            ],
            'encargado_operaciones' => [
                [
                    'id' => 'estado-aplicaciones',
                    'label' => __('seguridad.dashboard.tab_estado_aplicaciones'),
                    'claves' => [SeccionDashboard::EstadoOrdenesAplicacion->value],
                ],
                [
                    'id' => 'proceso-trabajos',
                    'label' => __('seguridad.dashboard.tab_proceso_trabajos'),
                    'claves' => [SeccionDashboard::ColaValidacion->value, SeccionDashboard::Pausas->value],
                ],
                [
                    'id' => 'resumen-vuelos',
                    'label' => __('seguridad.dashboard.tab_resumen_vuelos'),
                    'claves' => [SeccionDashboard::ResumenVuelos->value],
                ],
            ],
            'piloto', 'auxiliar' => [
                [
                    'id' => 'mis-devengos',
                    'label' => __('seguridad.dashboard.tab_mis_devengos'),
                    'claves' => [SeccionDashboard::MiLiquidacion->value],
                ],
                [
                    'id' => 'mis-trabajos',
                    'label' => __('seguridad.dashboard.tab_mis_trabajos'),
                    'claves' => [SeccionDashboard::MisSesiones->value, SeccionDashboard::MisEquipos->value],
                ],
            ],
            // Lo que el jefe de campo coordina (tarea 138): con qué recursos
            // cuenta, qué órdenes de trabajo tiene cada cuadrilla y cómo van
            // todas las órdenes de aplicación con su equipamiento. Es solo
            // lectura: las acciones viven en sus pantallas.
            'jefe_campo' => [
                [
                    'id' => 'recursos',
                    'label' => __('seguridad.dashboard.tab_recursos'),
                    'claves' => [SeccionDashboard::RecursosEnUso->value, SeccionDashboard::Stock->value],
                ],
                [
                    'id' => 'ordenes-cuadrillas',
                    'label' => __('seguridad.dashboard.tab_ordenes_cuadrillas'),
                    'claves' => [SeccionDashboard::OrdenesTrabajoPorCuadrilla->value],
                ],
                [
                    'id' => 'ordenes-equipamiento',
                    'label' => __('seguridad.dashboard.tab_ordenes_equipamiento'),
                    'claves' => [SeccionDashboard::OrdenesConEquipamiento->value],
                ],
            ],
            default => [
                [
                    'id' => 'resumen',
                    'label' => __('seguridad.dashboard.tab_resumen'),
                    'claves' => [
                        SeccionDashboard::Alertas->value, SeccionDashboard::DistribucionSesiones->value,
                        SeccionDashboard::HectareasPorDia->value, SeccionDashboard::ColaValidacion->value,
                        SeccionDashboard::MisSesiones->value, SeccionDashboard::MisEquipos->value,
                        SeccionDashboard::MiLiquidacion->value, SeccionDashboard::Pausas->value,
                        SeccionDashboard::Stock->value, SeccionDashboard::AvanceClientes->value,
                        SeccionDashboard::DiasEnHacienda->value,
                    ],
                ],
                ['id' => 'mapa', 'label' => __('seguridad.dashboard.tab_mapa'), 'claves' => [SeccionDashboard::Mapa->value]],
                ['id' => 'lotes', 'label' => __('seguridad.dashboard.tab_resumen_lote'), 'claves' => [SeccionDashboard::ResumenPorLote->value]],
                ['id' => 'multimedia', 'label' => __('seguridad.dashboard.tab_multimedia'), 'claves' => [SeccionDashboard::Multimedia->value]],
            ],
        };
    }

    /** @return list<array<string, mixed>>|null */
    private function distribucion(): ?array
    {
        $distribucion = $this->operaciones->distribucionPorEstado();

        $total = array_sum(array_column($distribucion, 'valor'));

        return $total > 0 ? $distribucion : null;
    }

    /** @return array{fechas: list<string>, valores: list<string>}|null */
    private function hectareasPorDia(): ?array
    {
        $serie = $this->operaciones->hectareasPorPeriodo(GranularidadVuelos::Dia, self::DIAS_SERIE_HECTAREAS);

        $huboVuelo = array_filter($serie, fn (array $dia) => $dia['hectareas'] !== '0.00');

        if ($huboVuelo === []) {
            return null;
        }

        return [
            'fechas' => array_column($serie, 'fecha'),
            'valores' => array_column($serie, 'hectareas'),
        ];
    }

    /**
     * Órdenes de aplicación por estado (tab del encargado, tarea 136). Son
     * las ÓRDENES, con su propia máquina de estados (ADR 0022), no las
     * sesiones de vuelo de {@see distribucion()}: son otra tabla y otros
     * estados. Sin ninguna orden la sección se omite, como las demás.
     *
     * @return array{total: int, estados: list<array{estado: string, tono: string, valor: int}>}|null
     */
    private function estadoOrdenesAplicacion(): ?array
    {
        $estados = $this->operaciones->distribucionOrdenesPorEstado();

        $total = array_sum(array_column($estados, 'valor'));

        return $total > 0 ? ['total' => $total, 'estados' => $estados] : null;
    }

    /**
     * Hectáreas validadas de los vuelos, con las tres granularidades ya
     * calculadas (tab del encargado, tarea 136): el selector de la vista
     * alterna entre ellas sin volver al servidor. Cada una es la MISMA
     * consulta de {@see hectareasPorDia()} con otra agrupación; el total del
     * período se suma acá con BigDecimal (invariante 6), no en la vista.
     *
     * @return array<string, array{fechas: list<string>, valores: list<string>, total: string}>|null
     */
    private function resumenVuelos(): ?array
    {
        $resumen = [];
        $huboVuelo = false;

        foreach (GranularidadVuelos::cases() as $granularidad) {
            $serie = $this->operaciones->hectareasPorPeriodo($granularidad, $this->periodosDeVentana($granularidad));

            $total = BigDecimal::zero();

            foreach ($serie as $periodo) {
                $total = $total->plus(BigDecimal::of($periodo['hectareas']));
            }

            $huboVuelo = $huboVuelo || $total->isPositive();

            $resumen[$granularidad->value] = [
                'fechas' => array_column($serie, 'fecha'),
                'valores' => array_column($serie, 'hectareas'),
                'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
            ];
        }

        return $huboVuelo ? $resumen : null;
    }

    /** Cuántos períodos muestra el resumen de vuelos en cada granularidad. */
    private function periodosDeVentana(GranularidadVuelos $granularidad): int
    {
        return match ($granularidad) {
            GranularidadVuelos::Dia => self::DIAS_SERIE_HECTAREAS,
            GranularidadVuelos::Semana => self::SEMANAS_RESUMEN_VUELOS,
            GranularidadVuelos::Mes => self::MESES_RESUMEN_VUELOS,
        };
    }

    /** @return list<array<string, mixed>>|null */
    private function resumenPorLote(): ?array
    {
        $resumen = $this->operaciones->resumenPorLote();

        if ($resumen === []) {
            return null;
        }

        $filas = [];

        foreach ($resumen as $loteId => $avance) {
            $filas[] = $this->filaLote($loteId, $avance);
        }

        return $filas;
    }

    /**
     * @return array{sesiones: list<array<string, mixed>>, totales: array{sesiones: int, hectareas: string, sesionesValidadas: int}}|null
     */
    private function misSesiones(?int $personaId): ?array
    {
        if ($personaId === null) {
            return null;
        }

        $sesiones = $this->sesiones($this->operaciones->sesionesRecientes(self::SESIONES_RECIENTES, $personaId));

        if ($sesiones === null) {
            return null;
        }

        return [
            'sesiones' => $sesiones,
            'totales' => $this->operaciones->totalesDelMesPorPersona($personaId),
        ];
    }

    /**
     * Los drones que la persona operó este mes. Sin permiso propio: es su
     * propio trabajo visto desde el equipo.
     *
     * @return list<EquipoPersonaPanel>|null
     */
    private function misEquipos(?int $personaId): ?array
    {
        if ($personaId === null) {
            return null;
        }

        $equipos = $this->operaciones->equiposDePersonaDelMes($personaId);

        return $equipos === [] ? null : $equipos;
    }

    /**
     * Liquidación del mes: lo devengado, los anticipos ya cobrados a cuenta y
     * el saldo entre ambos.
     *
     * Las dos mitades van juntas a propósito: el devengado solo no es lo que
     * la persona va a recibir, y mostrarlo suelto invita a un reclamo que el
     * saldo responde de antemano.
     *
     * @return array<string, mixed>|null
     */
    private function miLiquidacion(?int $personaId): ?array
    {
        if ($personaId === null) {
            return null;
        }

        $liquidacion = $this->finanzas->devengosDelMes($personaId, self::DEVENGOS);
        $anticipos = $this->finanzas->anticiposDelMes($personaId, self::ANTICIPOS);

        if ($liquidacion['devengos'] === [] && $anticipos['anticipos'] === []) {
            return null;
        }

        return [...$liquidacion, 'anticipos' => $anticipos];
    }

    /**
     * Enriquecimiento común de una lista de sesiones: le pega el lote (de
     * Comercial) y el piloto (de Personal) a lo que Operaciones devolvió con
     * ids pelados. Precarga los nombres antes del bucle — de a uno serían
     * tantas consultas como sesiones.
     *
     * @param  list<SesionPanel>  $sesiones
     * @return list<array<string, mixed>>|null
     */
    private function sesiones(array $sesiones): ?array
    {
        if ($sesiones === []) {
            return null;
        }

        $this->nombres->precargarPersonas(array_map(fn (SesionPanel $s) => $s->pilotoId, $sesiones));

        return array_map(function (SesionPanel $sesion): array {
            $lote = $this->nombres->lote($sesion->loteId);

            return [
                'id' => $sesion->id,
                'trabajoId' => $sesion->trabajoId,
                'estado' => $sesion->estado,
                'tono' => $sesion->tono,
                'hectareas' => $sesion->hectareas,
                'inicio' => $sesion->inicio,
                'fin' => $sesion->fin,
                'lote' => $lote->codigo ?? "#{$sesion->loteId}",
                'propiedad' => $lote?->propiedadNombre,
                'cliente' => $lote?->clienteNombre,
                'piloto' => $this->nombres->persona($sesion->pilotoId),
                'dron' => $sesion->dronCodigo,
                'minutosVuelo' => $sesion->minutosVuelo,
                'tieneCapturaRc' => $sesion->tieneCapturaRc,
                'anulada' => $sesion->anulada,
            ];
        }, $sesiones);
    }

    /** @return list<array<string, mixed>>|null */
    private function multimedia(): ?array
    {
        $agrupadas = $this->operaciones->sesionesConCapturas(self::EVIDENCIAS_GALERIA);

        if ($agrupadas === []) {
            return null;
        }

        $this->nombres->precargarPersonas(array_map(fn (array $fila) => $fila['sesion']->pilotoId, $agrupadas));

        return array_map(function (array $fila): array {
            $sesion = $fila['sesion'];
            $lote = $this->nombres->lote($sesion->loteId);

            return [
                'sesionId' => $sesion->id,
                'fecha' => $sesion->inicio,
                'piloto' => $this->nombres->persona($sesion->pilotoId),
                'lote' => $lote->codigo ?? "#{$sesion->loteId}",
                'cliente' => $lote?->clienteNombre,
                'dron' => $sesion->dronCodigo,
                'hectareas' => $sesion->hectareas,
                'minutosVuelo' => $sesion->minutosVuelo,
                'litros' => $sesion->litrosConsumidos,
                'capturas' => array_map(fn (EvidenciaPanel $captura) => [
                    'url' => $captura->url,
                    'tipo' => $captura->tipo,
                ], $fila['capturas']),
            ];
        }, $agrupadas);
    }

    /**
     * Una fila del resumen por lote: el avance operativo (Operaciones) más
     * la identidad y las hectáreas totales del lote (Comercial). El
     * porcentaje se calcula acá y no en la vista — es una regla, no formato.
     *
     * @return array<string, mixed>
     */
    private function filaLote(int $loteId, ResumenLotePanel $avance): array
    {
        $lote = $this->nombres->lote($loteId);
        $totales = $lote !== null ? (float) $lote->hectareas : 0.0;
        $aplicadas = (float) $avance->hectareasAplicadas;

        return [
            'loteId' => $loteId,
            'codigo' => $lote->codigo ?? "#{$loteId}",
            'propiedad' => $lote?->propiedadNombre,
            'cliente' => $lote?->clienteNombre,
            'hectareasLote' => $lote?->hectareas,
            'hectareasAplicadas' => $avance->hectareasAplicadas,
            'hectareasPendientes' => number_format(max(0.0, $totales - $aplicadas), 2, '.', ''),
            'pctCompletado' => $totales > 0.0 ? min(100.0, round($aplicadas / $totales * 100, 1)) : 0.0,
            'sesiones' => $avance->sesiones,
            'sesionesValidadas' => $avance->sesionesValidadas,
            'litros' => $avance->litrosConsumidos,
            'minutosVuelo' => $avance->minutosVuelo,
            'tono' => $avance->tono,
            'ultimaSesion' => $avance->ultimaSesion,
        ];
    }

    /** @return array{total_minutos: int, por_causa: array<string, int>}|null */
    private function pausas(): ?array
    {
        $pausas = $this->operaciones->pausasPorCausaDelMes();

        return $pausas['total_minutos'] > 0 ? $pausas : null;
    }

    /**
     * Días efectivos en hacienda del mes, por cuadrilla y por propiedad, de
     * mayor a menor. Las cuentas son de Operaciones; los nombres, de Personal
     * y de Comercial, cada uno por su contrato. Sin estadías en el mes la
     * sección se omite, como las demás.
     *
     * @return array{total_dias: float, en_curso: int, por_cuadrilla: list<array{nombre: string, dias: float}>, por_propiedad: list<array{nombre: string, dias: float}>}|null
     */
    private function diasEnHacienda(): ?array
    {
        $dias = $this->operaciones->diasEnHaciendaDelMes();

        if ($dias['por_cuadrilla'] === [] && $dias['por_propiedad'] === [] && $dias['en_curso'] === 0) {
            return null;
        }

        $cuadrillas = $this->cuadrillas->porIds(array_keys($dias['por_cuadrilla']));
        $propiedades = $this->propiedades->porIds(array_keys($dias['por_propiedad']));

        $filas = static fn (array $porId, callable $nombre): array => collect($porId)
            ->sortDesc()
            ->map(fn (float $cantidad, int $id): array => ['nombre' => $nombre($id), 'dias' => round($cantidad, 1)])
            ->values()
            ->all();

        return [
            'total_dias' => $dias['total_dias'],
            'en_curso' => $dias['en_curso'],
            'por_cuadrilla' => $filas($dias['por_cuadrilla'], fn (int $id): string => $this->nombreCuadrilla($id, $cuadrillas)),
            'por_propiedad' => $filas($dias['por_propiedad'], fn (int $id): string => isset($propiedades[$id]) ? $propiedades[$id]->etiqueta() : "#{$id}"),
        ];
    }

    /** @return list<array<string, mixed>>|null */
    private function stock(): ?array
    {
        $filas = $this->inventario->stockBajoMinimo(self::STOCK_CRITICO);

        if ($filas === []) {
            return null;
        }

        $this->nombres->precargarBases(array_values(array_filter(
            array_map(fn (StockPanel $fila) => $fila->baseId, $filas),
            fn (?int $id) => $id !== null,
        )));

        return array_map(fn (StockPanel $fila) => [
            'codigo' => $fila->codigo,
            'descripcion' => $fila->descripcion,
            'cantidad' => $fila->cantidad,
            'stockMinimo' => $fila->stockMinimo,
            'base' => $this->nombres->base($fila->baseId),
        ], $filas);
    }

    /** @return list<AvanceClientePanel>|null */
    private function avanceClientes(): ?array
    {
        $avances = $this->comercial->avancePorCliente(self::CONTRATOS_AVANCE);

        return $avances === [] ? null : $avances;
    }

    /**
     * Estado de cuentas de clientes y contratos (tab del dueño, tarea 135):
     * la mitad en plata de {@see avanceClientes()}, que solo da hectáreas.
     *
     * @return list<EstadoCuentaContratoPanel>|null
     */
    private function estadoCuentas(): ?array
    {
        $estados = $this->comercial->estadoDeCuentas(self::ESTADO_CUENTAS);

        return $estados === [] ? null : $estados;
    }

    /** @return list<AlertaPanel>|null */
    private function alertas(): ?array
    {
        $alertas = $this->operaciones->alertasRecientes(self::ALERTAS);

        return $alertas === [] ? null : $alertas;
    }

    /**
     * Resumen de trabajos actuales por equipo (tab del dueño, tarea 135):
     * lo que cada equipo tiene abierto AHORA, con los lotes y el nombre ya
     * resueltos — `Operaciones` solo sabe ids. Del más cargado al menos
     * (hectáreas declaradas), mismo criterio que {@see avanceClientes()} y
     * {@see diasEnHacienda()}: lo que más pesa, primero.
     *
     * @return list<array<string, mixed>>|null
     */
    private function trabajosPorEquipo(): ?array
    {
        $porEquipo = $this->operaciones->trabajosAbiertosPorEquipo();

        if ($porEquipo === []) {
            return null;
        }

        $equipos = $this->cuadrillas->porIds(array_keys($porEquipo));

        $filas = array_map(fn (ResumenEquipoTrabajoPanel $resumen): array => [
            'equipoTrabajoId' => $resumen->equipoTrabajoId,
            'equipo' => $this->nombreCuadrilla($resumen->equipoTrabajoId, $equipos),
            'trabajosAbiertos' => $resumen->trabajosAbiertos,
            'hectareasDeclaradas' => $resumen->hectareasDeclaradas,
            'lotes' => array_map(fn (int $loteId) => $this->nombres->lote($loteId)->codigo ?? "#{$loteId}", $resumen->loteIds),
            'ultimoInicio' => $resumen->ultimoInicio,
        ], array_values($porEquipo));

        usort($filas, fn (array $a, array $b) => (float) $b['hectareasDeclaradas'] <=> (float) $a['hectareasDeclaradas']);

        return $filas;
    }

    /**
     * Progreso en toda la campaña (tab del dueño, tarea 135): el MISMO
     * {@see resumenPorLote()} totalizado en vez de fila por fila —
     * {@see filaLote()} calcula hectáreas totales y aplicadas por lote, acá
     * se suman esas dos columnas y se le aplica la MISMA fórmula de
     * porcentaje, no una segunda.
     *
     * @return array<string, mixed>|null
     */
    private function progresoCampania(): ?array
    {
        $resumen = $this->operaciones->resumenPorLote();

        if ($resumen === []) {
            return null;
        }

        $hectareasTotales = 0.0;
        $hectareasAplicadas = 0.0;
        $sesiones = 0;
        $sesionesValidadas = 0;

        foreach ($resumen as $loteId => $avance) {
            $fila = $this->filaLote($loteId, $avance);

            $hectareasTotales += (float) ($fila['hectareasLote'] ?? 0);
            $hectareasAplicadas += (float) $fila['hectareasAplicadas'];
            $sesiones += (int) $fila['sesiones'];
            $sesionesValidadas += (int) $fila['sesionesValidadas'];
        }

        return [
            'lotes' => count($resumen),
            'hectareasTotales' => number_format($hectareasTotales, 2, '.', ''),
            'hectareasAplicadas' => number_format($hectareasAplicadas, 2, '.', ''),
            'hectareasPendientes' => number_format(max(0.0, $hectareasTotales - $hectareasAplicadas), 2, '.', ''),
            'pctCompletado' => $hectareasTotales > 0.0 ? min(100.0, round($hectareasAplicadas / $hectareasTotales * 100, 1)) : 0.0,
            'sesiones' => $sesiones,
            'sesionesValidadas' => $sesionesValidadas,
        ];
    }

    /**
     * Qué recursos están ocupados AHORA (tab del jefe de campo, tarea 138): las
     * cuadrillas con algún trabajo abierto, con quiénes salen y con qué equipo
     * —dron, vehículo, generador, baterías— y en qué estado está cada pieza.
     * La carga la da `Operaciones`, las personas `Personal` y el equipamiento
     * {@see CatalogoEquipamientoPanel}. Sin ningún trabajo abierto se omite.
     *
     * @return array{cuadrillas: list<array<string, mixed>>, recursos: int, fueraDeServicio: int}|null
     */
    private function recursosEnUso(): ?array
    {
        $porEquipo = $this->operaciones->trabajosAbiertosPorEquipo();

        if ($porEquipo === []) {
            return null;
        }

        $equipoIds = array_keys($porEquipo);
        $equipos = $this->cuadrillas->porIds($equipoIds);
        $this->equipamiento->precargar($equipoIds);
        $hoy = today()->toDateString();

        $cuadrillas = array_map(function (ResumenEquipoTrabajoPanel $resumen) use ($equipos, $hoy): array {
            $equipamiento = $this->equipamiento->deEquipo($resumen->equipoTrabajoId);

            return [
                'equipoTrabajoId' => $resumen->equipoTrabajoId,
                'equipo' => $this->nombreCuadrilla($resumen->equipoTrabajoId, $equipos),
                'trabajosAbiertos' => $resumen->trabajosAbiertos,
                'hectareasDeclaradas' => $resumen->hectareasDeclaradas,
                'integrantes' => array_map(fn (DatosIntegranteEquipo $integrante): array => [
                    'nombre' => $integrante->nombrePersona,
                    'rol' => __('personal.rol_equipo.'.$integrante->rolEquipo),
                ], $this->cuadrillas->integrantesAFecha($resumen->equipoTrabajoId, $hoy)),
                'equipamiento' => $equipamiento,
                'fueraDeServicio' => count(array_filter($equipamiento, fn (array $recurso): bool => ! $recurso['operativo'])),
            ];
        }, array_values($porEquipo));

        usort($cuadrillas, fn (array $a, array $b): int => strnatcasecmp($a['equipo'], $b['equipo']));

        return [
            'cuadrillas' => $cuadrillas,
            'recursos' => array_sum(array_map(fn (array $cuadrilla): int => count($cuadrilla['equipamiento']), $cuadrillas)),
            'fueraDeServicio' => array_sum(array_column($cuadrillas, 'fueraDeServicio')),
        ];
    }

    /**
     * Las Órdenes de Trabajo (tandas) que siguen en marcha, agrupadas por la
     * cuadrilla que las trabaja (tab del jefe de campo, tarea 138). La tanda y
     * su carga las da `Operaciones`; el nombre de la cuadrilla, `Personal`; los
     * lotes, `Comercial` — el mismo cruce de {@see trabajosPorEquipo()}, pero
     * por orden en vez de totalizado por equipo.
     *
     * @return list<array{equipoTrabajoId: int, equipo: string, tandas: list<array<string, mixed>>}>|null
     */
    private function ordenesTrabajoPorCuadrilla(): ?array
    {
        $porEquipo = $this->operaciones->tandasAbiertasPorEquipo();

        if ($porEquipo === []) {
            return null;
        }

        $equipos = $this->cuadrillas->porIds(array_keys($porEquipo));

        $filas = [];

        foreach ($porEquipo as $equipoId => $tandas) {
            $filas[] = [
                'equipoTrabajoId' => $equipoId,
                'equipo' => $this->nombreCuadrilla($equipoId, $equipos),
                'tandas' => array_map(fn (TandaDeEquipoPanel $tanda): array => [
                    'ordenTrabajoId' => $tanda->ordenTrabajoId,
                    'ordenId' => $tanda->ordenId,
                    'nroAplicacion' => $tanda->nroAplicacion,
                    'estado' => $tanda->estadoOrden,
                    'tono' => $tanda->tonoOrden,
                    'lotes' => array_map(fn (int $loteId): string => $this->nombres->lote($loteId)->codigo ?? "#{$loteId}", $tanda->loteIds),
                    'trabajosAbiertos' => $tanda->trabajosAbiertos,
                    'trabajosTotal' => $tanda->trabajosTotal,
                    'hectareasDeclaradas' => $tanda->hectareasDeclaradas,
                ], $tandas),
            ];
        }

        usort($filas, fn (array $a, array $b): int => strnatcasecmp($a['equipo'], $b['equipo']));

        return $filas;
    }

    /**
     * Estado de las órdenes de aplicación con sus haciendas y su equipamiento
     * (tab del jefe de campo, tarea 138). El estado y las cuadrillas de cada
     * orden los da `Operaciones`; las haciendas y el cliente salen de los
     * lotes que la orden copió del contrato (`Comercial`); y el estado de
     * dron, vehículo, generador y baterías de cada cuadrilla, de `Mantenimiento`
     * vía {@see CatalogoEquipamientoPanel}.
     *
     * Solo las órdenes abiertas llevan equipamiento: el estado de una batería
     * HOY no dice nada de una orden que ya se consumió o se canceló.
     *
     * @return list<array<string, mixed>>|null
     */
    private function ordenesConEquipamiento(): ?array
    {
        $ordenes = $this->operaciones->ordenesAplicacionConEquipos(self::ORDENES_TERMINADAS);

        if ($ordenes === []) {
            return null;
        }

        $equipoIds = [];

        foreach ($ordenes as $orden) {
            if ($orden->abierta) {
                array_push($equipoIds, ...$orden->equipoTrabajoIds);
            }
        }

        $equipos = $this->cuadrillas->porIds(array_values(array_unique($equipoIds)));
        $this->equipamiento->precargar($equipoIds);

        return array_map(function (OrdenAplicacionPanel $orden) use ($equipos): array {
            $lotes = array_values(array_filter(array_map(fn (int $loteId): ?LotePanel => $this->nombres->lote($loteId), $orden->loteIds)));

            $hectareas = BigDecimal::zero();

            foreach ($lotes as $lote) {
                $hectareas = $hectareas->plus(BigDecimal::of($lote->hectareas));
            }

            return [
                'id' => $orden->id,
                'nroAplicacion' => $orden->nroAplicacion,
                'estado' => $orden->estado,
                'tono' => $orden->tono,
                'abierta' => $orden->abierta,
                'fechaEmision' => $orden->fechaEmision,
                'clientes' => array_values(array_unique(array_map(fn (LotePanel $lote): string => $lote->clienteNombre, $lotes))),
                'haciendas' => array_values(array_unique(array_map(fn (LotePanel $lote): string => $lote->propiedadNombre, $lotes))),
                'hectareas' => (string) $hectareas->toScale(2, RoundingMode::HalfUp),
                'equiposNecesarios' => $orden->equiposNecesarios,
                'cuadrillasAsignadas' => count($orden->equipoTrabajoIds),
                'cuadrillas' => $orden->abierta
                    ? array_map(fn (int $equipoId): array => $this->cuadrillaConEquipamiento($equipoId, $equipos), $orden->equipoTrabajoIds)
                    : [],
            ];
        }, $ordenes);
    }

    /**
     * Una cuadrilla de una orden con la salud de su equipamiento: cuántas
     * piezas lleva y cuáles NO están operativas (las que sí lo están no
     * necesitan nombrarse fila a fila).
     *
     * @param  array<int, DatosEquipoTrabajo>  $equipos
     * @return array{equipo: string, recursos: int, fuera: list<array{tipoEtiqueta: string, identificador: string, etiquetaEstado: string, tono: string}>}
     */
    private function cuadrillaConEquipamiento(int $equipoTrabajoId, array $equipos): array
    {
        $equipamiento = $this->equipamiento->deEquipo($equipoTrabajoId);

        $fuera = array_values(array_filter($equipamiento, fn (array $recurso): bool => ! $recurso['operativo']));

        return [
            'equipo' => $this->nombreCuadrilla($equipoTrabajoId, $equipos),
            'recursos' => count($equipamiento),
            'fuera' => array_map(fn (array $recurso): array => [
                'tipoEtiqueta' => $recurso['tipoEtiqueta'],
                'identificador' => $recurso['identificador'],
                'etiquetaEstado' => $recurso['etiquetaEstado'],
                'tono' => $recurso['tono'],
            ], $fuera),
        ];
    }

    /** @param  array<int, DatosEquipoTrabajo>  $equipos */
    private function nombreCuadrilla(int $equipoTrabajoId, array $equipos): string
    {
        $equipo = $equipos[$equipoTrabajoId] ?? null;

        return match (true) {
            $equipo === null => "#{$equipoTrabajoId}",
            $equipo->nombre === null || $equipo->nombre === '' => $equipo->codigo,
            default => "{$equipo->codigo} — {$equipo->nombre}",
        };
    }
}
