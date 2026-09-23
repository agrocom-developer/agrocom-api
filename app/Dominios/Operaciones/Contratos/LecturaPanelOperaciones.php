<?php

namespace App\Dominios\Operaciones\Contratos;

use App\Dominios\Operaciones\Aplicacion\AgregarPausasPorCausa;

/**
 * Frontera de lectura de Operaciones hacia el dashboard del panel (ADR 0003,
 * regla 2; tarea 67). Hermano de {@see LecturaContadoresPanel}, que resuelve
 * los badges del menú: esto resuelve el CONTENIDO de las secciones.
 *
 * Todo lo que devuelve son DTOs primitivos o arrays de escalares — `Seguridad`
 * nunca ve `Sesion`, `Trabajo`, `Evidencia` ni `Alerta`. Las hectáreas viajan
 * como string decimal (invariante 6).
 */
interface LecturaPanelOperaciones
{
    /**
     * Sesiones más recientes por fecha de inicio descendente. `$pilotoId`
     * acota a las de una persona (dashboard del piloto y del auxiliar);
     * `null` trae las de toda la operación.
     *
     * @return list<SesionPanel>
     */
    public function sesionesRecientes(int $limite, ?int $pilotoId = null): array;

    /**
     * Sesiones pendientes de validar (`cerrado`, sin anular), las más viejas
     * primero: son las que le queman la cola al jefe de campo.
     *
     * @return list<SesionPanel>
     */
    public function colaValidacion(int $limite): array;

    /**
     * Conteo de sesiones por estado, para el donut de distribución. Devuelve
     * SIEMPRE los tres estados del catálogo, incluidos los que están en cero
     * — mismo criterio que {@see AgregarPausasPorCausa}.
     *
     * @return list<array{estado: string, tono: string, valor: int}>
     */
    public function distribucionPorEstado(?int $pilotoId = null): array;

    /**
     * Conteo de órdenes de aplicación por estado, para el donut del
     * encargado (tarea 136). Devuelve SIEMPRE los seis estados de
     * `EstadoOrdenAplicacion`, incluidos los que están en cero, en el
     * orden del ciclo de vida. `tono` es el mismo que pinta el badge del
     * listado de órdenes: un estado, un color en toda la app.
     *
     * No es la {@see distribucionPorEstado()} de las sesiones: las órdenes
     * tienen su propia máquina de estados (ADR 0022) y son otra tabla.
     *
     * @return list<array{estado: string, tono: string, valor: int}>
     */
    public function distribucionOrdenesPorEstado(): array;

    /**
     * Hectáreas validadas por período: los últimos `$periodos` períodos de la
     * granularidad pedida (el actual incluido, aunque venga a medias), con
     * los períodos sin vuelo en `'0.00'`, en orden cronológico — el área del
     * gráfico no puede saltarse uno o la curva miente. La misma consulta
     * sirve a las tres granularidades: solo cambia a qué período se atribuye
     * cada sesión.
     *
     * `fecha` es el primer día del período (`Y-m-d`): el propio día, el lunes
     * de la semana o el día 1 del mes.
     *
     * @return list<array{fecha: string, hectareas: string}>
     */
    public function hectareasPorPeriodo(GranularidadVuelos $granularidad, int $periodos, ?int $pilotoId = null): array;

    /**
     * Avance operativo por lote, indexado por `loteId` — el consumidor lo
     * cruza con los lotes de `Comercial` (que es quien conoce el nombre y la
     * geometría) sin que este módulo tenga que leer `com_lotes`.
     *
     * @return array<int, ResumenLotePanel>
     */
    public function resumenPorLote(): array;

    /**
     * Últimas sesiones que dejaron alguna evidencia gráfica, con esas
     * evidencias adjuntas — la galería multimedia agrupa POR SESIÓN, no una
     * grilla suelta de archivos: una captura de RC sin la sesión que la
     * produjo no dice nada.
     *
     * Solo evidencia gráfica de la sesión: la captura del control remoto y
     * las fotos de incidencia. La firma del acta queda fuera (es del
     * trabajo, no de la sesión, y es un documento legal — no material de
     * galería).
     *
     * @return list<array{sesion: SesionPanel, capturas: list<EvidenciaPanel>}>
     */
    public function sesionesConCapturas(int $limite): array;

    /**
     * Alertas por excepción más recientes (HU-19), pendientes primero.
     *
     * @return list<AlertaPanel>
     */
    public function alertasRecientes(int $limite): array;

    /**
     * Minutos de pausa agregados por causa del mes en curso, con TODAS las
     * causas del catálogo aunque estén en cero.
     *
     * @return array{total_minutos: int, por_causa: array<string, int>}
     */
    public function pausasPorCausaDelMes(): array;

    /**
     * Días efectivos en hacienda del mes en curso, por cuadrilla y por
     * propiedad, con las MISMAS cuentas que el listado de estadías (estadías
     * ya finalizadas cuya entrada cae en el mes), más cuántas siguen en curso.
     * Las claves son ids de `per_equipos_trabajo` y de `com_propiedades`: los
     * nombres los resuelve quien llama, por el contrato de cada módulo.
     *
     * @return array{total_dias: float, en_curso: int, por_cuadrilla: array<int, float>, por_propiedad: array<int, float>}
     */
    public function diasEnHaciendaDelMes(): array;

    /**
     * Drones que la persona operó en el mes en curso, del más usado al
     * menos: "de qué equipos respondo" resuelto desde las sesiones, que es
     * el único registro que liga persona y dron.
     *
     * @return list<EquipoPersonaPanel>
     */
    public function equiposDePersonaDelMes(int $personaId): array;

    /**
     * Totales del mes en curso de una persona: cuántas sesiones voló y
     * cuántas hectáreas suman. Es el encabezado del dashboard del piloto.
     *
     * @return array{sesiones: int, hectareas: string, sesionesValidadas: int}
     */
    public function totalesDelMesPorPersona(int $personaId): array;

    /**
     * Trabajos abiertos ahora mismo, agrupados por el equipo al que el jefe
     * de campo se los asignó (`equipo_trabajo_id`). Los trabajos sin equipo
     * asignado (nacidos por sync sin pasar por `AsignarEquiposOrden`) quedan
     * fuera: no hay "equipo sin nombre" que mostrar.
     *
     * @return array<int, ResumenEquipoTrabajoPanel> indexado por equipoTrabajoId
     */
    public function trabajosAbiertosPorEquipo(): array;
}
