<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Contratos\AvanceClientePanel;
use App\Dominios\Comercial\Contratos\EstadoCuentaContratoPanel;
use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use App\Dominios\Comercial\Contratos\LecturaPropiedades;
use App\Dominios\Finanzas\Contratos\LecturaPanelFinanzas;
use App\Dominios\Inventario\Contratos\LecturaPanelInventario;
use App\Dominios\Inventario\Contratos\StockPanel;
use App\Dominios\Operaciones\Contratos\AlertaPanel;
use App\Dominios\Operaciones\Contratos\EquipoPersonaPanel;
use App\Dominios\Operaciones\Contratos\EvidenciaPanel;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Operaciones\Contratos\ResumenEquipoTrabajoPanel;
use App\Dominios\Operaciones\Contratos\ResumenLotePanel;
use App\Dominios\Operaciones\Contratos\SesionPanel;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Dominio\SeccionDashboard;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

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
     * todavía no tenga el suyo propio (136 a 139 lo agregan cada uno por su
     * cuenta).
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
        $serie = $this->operaciones->hectareasPorDia(self::DIAS_SERIE_HECTAREAS);

        $huboVuelo = array_filter($serie, fn (array $dia) => $dia['hectareas'] !== '0.00');

        if ($huboVuelo === []) {
            return null;
        }

        return [
            'fechas' => array_column($serie, 'fecha'),
            'valores' => array_column($serie, 'hectareas'),
        ];
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
