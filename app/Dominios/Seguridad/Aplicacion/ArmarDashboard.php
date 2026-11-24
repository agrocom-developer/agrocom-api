<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Distribucion\Contratos\LecturaVersionesApk;
use App\Dominios\Distribucion\Contratos\VersionApkPanel;
use App\Dominios\Operaciones\Contratos\AlertaPanel;
use App\Dominios\Operaciones\Contratos\EquipoPersonaPanel;
use App\Dominios\Operaciones\Contratos\EvidenciaPanel;
use App\Dominios\Operaciones\Contratos\GranularidadVuelos;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Operaciones\Contratos\SesionPanel;
use App\Dominios\Seguridad\Dominio\SeccionDashboard;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Caso de uso: armar el dashboard del ROL ACTIVO (tarea 67).
 *
 * Su única responsabilidad es DECIDIR y COMPONER: qué secciones puede ver
 * este rol ({@see SeccionDashboard}) y pedirle a cada módulo dueño su parte
 * por contrato. No consulta ninguna tabla — ni siquiera las de `Seguridad`.
 * Los nombres que cruzan módulos los resuelve {@see CatalogoNombresPanel}.
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

    private const ALERTAS = 5;

    private const SEMANAS_RESUMEN_VUELOS = 8;

    private const MESES_RESUMEN_VUELOS = 6;

    /** Dispositivos y filas de bitácora que muestra el tablero del administrador (tarea 139). */
    private const DISPOSITIVOS = 5;

    private const BITACORA = 8;

    public function __construct(
        private readonly LecturaPanelOperaciones $operaciones,
        private readonly CatalogoNombresPanel $nombres,
        private readonly LecturaContextoSolicitudes $solicitudes,
        private readonly ResumirUsuariosPorRol $resumenUsuarios,
        private readonly ListarDispositivosRegistrados $dispositivos,
        private readonly ListarBitacora $bitacora,
        private readonly LecturaVersionesApk $versiones,
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

            $contenido = $this->contenido($seccion, $usuario);

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

    private function contenido(SeccionDashboard $seccion, SecUser $usuario): mixed
    {
        $personaId = $usuario->persona_id;

        return match ($seccion) {
            SeccionDashboard::SolicitudesPorEstado => $this->solicitudesPorEstado(),
            SeccionDashboard::DistribucionSesiones => $this->distribucion(),
            SeccionDashboard::HectareasPorDia => $this->hectareasPorDia(),
            SeccionDashboard::ColaValidacion => $this->sesiones($this->operaciones->colaValidacion(self::COLA_VALIDACION)),
            SeccionDashboard::Multimedia => $this->multimedia(),
            SeccionDashboard::Pausas => $this->pausas(),
            SeccionDashboard::Alertas => $this->alertas(),
            SeccionDashboard::MisSesiones => $this->misSesiones($personaId),
            SeccionDashboard::MisEquipos => $this->misEquipos($personaId),
            SeccionDashboard::ResumenVuelos => $this->resumenVuelos(),
            SeccionDashboard::UsuariosPorRol => $this->usuariosPorRol(),
            SeccionDashboard::DispositivosConSesion => $this->dispositivosConSesion(),
            SeccionDashboard::VersionesApk => $this->versionesApk(),
            SeccionDashboard::BitacoraReciente => $this->bitacoraReciente($usuario),
            // Un acceso directo no lleva contenido: el permiso ya decidió que
            // se muestra, y a dónde lleva lo sabe la vista.
            SeccionDashboard::AccesoConfiguracion, SeccionDashboard::AccesoOrganizacion => true,
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
     * no tenga el suyo propio — los seis del catálogo base ya lo tienen, así
     * que hoy solo lo recibe un rol creado desde el panel.
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
                    'id' => 'solicitudes',
                    'label' => __('seguridad.dashboard.tab_solicitudes'),
                    'claves' => [SeccionDashboard::SolicitudesPorEstado->value],
                ],
                [
                    'id' => 'resumen-vuelos',
                    'label' => __('seguridad.dashboard.tab_resumen_vuelos'),
                    'claves' => [SeccionDashboard::ResumenVuelos->value],
                ],
            ],
            'encargado_operaciones' => [
                [
                    'id' => 'solicitudes',
                    'label' => __('seguridad.dashboard.tab_solicitudes'),
                    'claves' => [SeccionDashboard::SolicitudesPorEstado->value],
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
                    'id' => 'mis-trabajos',
                    'label' => __('seguridad.dashboard.tab_mis_trabajos'),
                    'claves' => [SeccionDashboard::MisSesiones->value, SeccionDashboard::MisEquipos->value],
                ],
            ],
            // Lo que el jefe de campo coordina: la cola de validación y las
            // pausas del mes. Es solo lectura: las acciones viven en sus pantallas.
            'jefe_campo' => [
                [
                    'id' => 'proceso-trabajos',
                    'label' => __('seguridad.dashboard.tab_proceso_trabajos'),
                    'claves' => [SeccionDashboard::ColaValidacion->value, SeccionDashboard::Pausas->value],
                ],
            ],
            // El administrador de plataforma es un rol técnico, no del negocio
            // del cliente (tarea 139): solo ve lo que administra el sistema en
            // sí — cuentas y accesos, bitácora y configuración. Tiene todos los
            // permisos del catálogo, así que lo operativo queda fuera por lo que
            // esta tabla NO nombra, no por falta de permiso.
            'admin_plataforma' => [
                [
                    'id' => 'usuarios-accesos',
                    'label' => __('seguridad.dashboard.tab_usuarios_accesos'),
                    'claves' => [
                        SeccionDashboard::UsuariosPorRol->value, SeccionDashboard::DispositivosConSesion->value,
                        SeccionDashboard::VersionesApk->value,
                    ],
                ],
                [
                    'id' => 'bitacora-configuracion',
                    'label' => __('seguridad.dashboard.tab_bitacora_configuracion'),
                    'claves' => [
                        SeccionDashboard::BitacoraReciente->value, SeccionDashboard::AccesoConfiguracion->value,
                        SeccionDashboard::AccesoOrganizacion->value,
                    ],
                ],
            ],
            default => [
                [
                    'id' => 'resumen',
                    'label' => __('seguridad.dashboard.tab_resumen'),
                    'claves' => [
                        SeccionDashboard::SolicitudesPorEstado->value, SeccionDashboard::Alertas->value,
                        SeccionDashboard::DistribucionSesiones->value, SeccionDashboard::HectareasPorDia->value,
                        SeccionDashboard::ColaValidacion->value, SeccionDashboard::MisSesiones->value,
                        SeccionDashboard::MisEquipos->value, SeccionDashboard::Pausas->value,
                    ],
                ],
                ['id' => 'multimedia', 'label' => __('seguridad.dashboard.tab_multimedia'), 'claves' => [SeccionDashboard::Multimedia->value]],
            ],
        };
    }

    /**
     * Si el tablero del rol es técnico y no del negocio (tarea 139): solo el
     * administrador de plataforma. Su encabezado no habla de operación de hoy
     * ni del día — copy pensado para los roles que sí la operan.
     */
    public function esTecnico(string $rolClave): bool
    {
        return $rolClave === 'admin_plataforma';
    }

    /**
     * Solicitudes por estado, en el orden del ciclo (especificación, sección
     * 5.1): cuántas hay en cada paso, para ver dónde se acumula el trabajo.
     *
     * @return list<array{estado: string, cantidad: int}>|null
     */
    private function solicitudesPorEstado(): ?array
    {
        $conteo = $this->solicitudes->conteoPorEstado();

        if ($conteo === []) {
            return null;
        }

        return array_map(
            fn (string $estado, int $cantidad): array => ['estado' => $estado, 'cantidad' => $cantidad],
            array_keys($conteo),
            array_values($conteo),
        );
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
        $contexto = $this->solicitudes->porIds(array_map(fn (SesionPanel $s): int => $s->solicitudId, $sesiones));

        return array_map(function (SesionPanel $sesion) use ($contexto): array {
            $solicitud = $contexto[$sesion->solicitudId] ?? null;

            return [
                'id' => $sesion->id,
                'trabajoId' => $sesion->trabajoId,
                'estado' => $sesion->estado,
                'tono' => $sesion->tono,
                'hectareas' => $sesion->hectareas,
                'inicio' => $sesion->inicio,
                'fin' => $sesion->fin,
                'solicitud' => $solicitud?->etiqueta() ?? "#{$sesion->solicitudId}",
                'cliente' => $solicitud?->clienteNombre,
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
        $contexto = $this->solicitudes->porIds(array_map(fn (array $fila): int => $fila['sesion']->solicitudId, $agrupadas));

        return array_map(function (array $fila) use ($contexto): array {
            $sesion = $fila['sesion'];
            $solicitud = $contexto[$sesion->solicitudId] ?? null;

            return [
                'sesionId' => $sesion->id,
                'fecha' => $sesion->inicio,
                'piloto' => $this->nombres->persona($sesion->pilotoId),
                'solicitud' => $solicitud?->etiqueta() ?? "#{$sesion->solicitudId}",
                'cliente' => $solicitud?->clienteNombre,
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

    /** @return array{total_minutos: int, por_causa: array<string, int>}|null */
    private function pausas(): ?array
    {
        $pausas = $this->operaciones->pausasPorCausaDelMes();

        return $pausas['total_minutos'] > 0 ? $pausas : null;
    }

    /** @return list<AlertaPanel>|null */
    private function alertas(): ?array
    {
        $alertas = $this->operaciones->alertasRecientes(self::ALERTAS);

        return $alertas === [] ? null : $alertas;
    }

    /**
     * Cuentas internas activas por rol del catálogo (tab «Usuarios y accesos»
     * del administrador, tarea 139). Sin ninguna cuenta la sección se omite.
     *
     * @return array{total: int, portal: int, roles: list<array{rol: SecRole, usuarios: int}>}|null
     */
    private function usuariosPorRol(): ?array
    {
        $resumen = $this->resumenUsuarios->ejecutar();

        return $resumen['total'] > 0 ? $resumen : null;
    }

    /**
     * Dispositivos de campo con sesión abierta: cuántos son y los primeros del
     * mismo listado que `/panel/dispositivos` (misma consulta, no una copia).
     *
     * @return array{total: int, dispositivos: list<SecTokenDispositivo>}|null
     */
    private function dispositivosConSesion(): ?array
    {
        $vivos = $this->dispositivos->ejecutar(porPagina: self::DISPOSITIVOS, pagina: 1);

        return $vivos->total() > 0
            ? ['total' => $vivos->total(), 'dispositivos' => array_values($vivos->items())]
            : null;
    }

    /**
     * La versión del APK autorizada hoy y las que esperan autorización. Sin
     * ninguna de las dos (ni una versión registrada) la sección se omite.
     *
     * @return array{vigente: VersionApkPanel|null, pendientes: list<VersionApkPanel>}|null
     */
    private function versionesApk(): ?array
    {
        $vigente = $this->versiones->vigente();
        $pendientes = $this->versiones->pendientesDeAutorizar();

        return $vigente === null && $pendientes === [] ? null : ['vigente' => $vigente, 'pendientes' => $pendientes];
    }

    /**
     * Lo último que quedó en la bitácora de auditoría, con las fechas en la
     * zona de quien mira (misma resolución que `/panel/bitacora`).
     *
     * @return list<FilaBitacora>|null
     */
    private function bitacoraReciente(SecUser $usuario): ?array
    {
        $zona = (string) ($usuario->preferencia->zona_horaria ?? config('app.timezone'));

        $filas = $this->bitacora->recientes($zona, self::BITACORA);

        return $filas === [] ? null : $filas;
    }
}
