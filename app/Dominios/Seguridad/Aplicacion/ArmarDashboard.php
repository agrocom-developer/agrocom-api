<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Comercial\Contratos\AvanceClientePanel;
use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use App\Dominios\Finanzas\Contratos\LecturaPanelFinanzas;
use App\Dominios\Inventario\Contratos\LecturaPanelInventario;
use App\Dominios\Inventario\Contratos\StockPanel;
use App\Dominios\Operaciones\Contratos\AlertaPanel;
use App\Dominios\Operaciones\Contratos\EquipoPersonaPanel;
use App\Dominios\Operaciones\Contratos\EvidenciaPanel;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Operaciones\Contratos\ResumenLotePanel;
use App\Dominios\Operaciones\Contratos\SesionPanel;
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

    public function __construct(
        private readonly LecturaPanelOperaciones $operaciones,
        private readonly LecturaPanelComercial $comercial,
        private readonly LecturaPanelInventario $inventario,
        private readonly LecturaPanelFinanzas $finanzas,
        private readonly CatalogoNombresPanel $nombres,
        private readonly ArmarMapaOperativo $mapa,
    ) {}

    /**
     * @return array{secciones: array<string, mixed>, visibles: list<string>}
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
            SeccionDashboard::Stock => $this->stock(),
            SeccionDashboard::AvanceClientes => $this->avanceClientes(),
            SeccionDashboard::Alertas => $this->alertas(),
            SeccionDashboard::MisSesiones => $this->misSesiones($personaId),
            SeccionDashboard::MisEquipos => $this->misEquipos($personaId),
            SeccionDashboard::MiLiquidacion => $this->miLiquidacion($personaId),
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
                'campo' => $lote?->campoNombre,
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
            'campo' => $lote?->campoNombre,
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

    /** @return list<AlertaPanel>|null */
    private function alertas(): ?array
    {
        $alertas = $this->operaciones->alertasRecientes(self::ALERTAS);

        return $alertas === [] ? null : $alertas;
    }
}
