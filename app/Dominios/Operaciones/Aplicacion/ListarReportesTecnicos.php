<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use Carbon\CarbonImmutable;

/**
 * Listado de reportes técnicos del panel (HU-43, tarea 57): "como
 * encargado, quiero listar y descargar los reportes técnicos generados,
 * para reenviarlos al agrónomo" (`plan_sprints.md` Sprint 12, §252). Solo
 * lectura, filtrable por cliente y por período de generación.
 *
 * Compone dos fuentes sin JOIN cross-módulo (ADR 0003, regla 2):
 * `LecturaReporteTecnico` (este módulo) trae el universo de reportes con su
 * `solicitudId` ya resuelto; `LecturaContextoSolicitudes` (Solicitudes)
 * resuelve terreno y cliente de todas las solicitudes en una sola consulta.
 *
 * El filtro por cliente y por período se aplica acá, en PHP, no en la
 * consulta SQL de `ReporteTecnico`: el `cliente_id` no existe en esa tabla
 * (vive en `Solicitudes`), así que no hay forma de empujarlo a la base sin
 * romper la frontera modular. El volumen de reportes no lo justifica (mismo
 * criterio que `LecturaActaConformadaEloquent`).
 */
final class ListarReportesTecnicos
{
    public function __construct(
        private readonly LecturaReporteTecnico $lecturaReporte,
        private readonly LecturaContextoSolicitudes $solicitudes,
    ) {}

    /**
     * @return list<array{
     *     reporteId: int,
     *     trabajoId: int,
     *     solicitud: string,
     *     clienteId: int,
     *     clienteNombre: string,
     *     generadoEn: CarbonImmutable,
     * }>
     */
    public function ejecutar(?int $clienteId = null, ?string $desde = null, ?string $hasta = null): array
    {
        $reportes = $this->lecturaReporte->listarTodos();

        if ($reportes === []) {
            return [];
        }

        $contexto = $this->solicitudes->porIds(array_map(fn ($reporte): int => $reporte->solicitudId, $reportes));
        $filas = [];

        foreach ($reportes as $reporte) {
            if ($desde !== null && $reporte->generadoEn->toDateString() < $desde) {
                continue;
            }

            if ($hasta !== null && $reporte->generadoEn->toDateString() > $hasta) {
                continue;
            }

            $solicitud = $contexto[$reporte->solicitudId] ?? null;

            if ($solicitud === null) {
                continue;
            }

            if ($clienteId !== null && $solicitud->clienteId !== $clienteId) {
                continue;
            }

            $filas[] = [
                'reporteId' => $reporte->reporteId,
                'trabajoId' => $reporte->trabajoId,
                'solicitud' => $solicitud->etiqueta(),
                'clienteId' => $solicitud->clienteId,
                'clienteNombre' => $solicitud->clienteNombre,
                'generadoEn' => $reporte->generadoEn,
            ];
        }

        return $filas;
    }
}
