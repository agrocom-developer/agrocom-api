<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Carbon\CarbonImmutable;

/**
 * Listado de reportes técnicos del panel (HU-43, tarea 57): "como
 * encargado, quiero listar y descargar los reportes técnicos generados,
 * para reenviarlos al agrónomo" (`plan_sprints.md` Sprint 12, §252). Solo
 * lectura, filtrable por cliente y por período de generación.
 *
 * Compone tres fuentes sin JOIN cross-módulo (ADR 0003, regla 2):
 * `LecturaReporteTecnico` (este módulo) trae el universo de reportes con su
 * `contratoId` ya resuelto; `LecturaContrato` (Comercial, contrato inverso)
 * resuelve cliente por contrato — memoizado por `contratoId` porque varios
 * reportes comparten contrato; `LecturaLotes` (Comercial) resuelve el código
 * de lote para mostrar en la tabla, mismo contrato que ya usa
 * `ArmarContenidoReporteTecnico`. `nro_aplicacion` sale directo de
 * `Trabajo` (mismo módulo, sin cruce) con una única consulta `whereIn`.
 *
 * El filtro por cliente y por período se aplica acá, en PHP, no en la
 * consulta SQL de `ReporteTecnico`: el `cliente_id` no existe en esa tabla
 * (vive en `Comercial`), así que no hay forma de empujarlo a la base sin
 * romper la frontera modular. El volumen de reportes no lo justifica (mismo
 * criterio que `LecturaActaConformadaEloquent`).
 */
final class ListarReportesTecnicos
{
    public function __construct(
        private readonly LecturaReporteTecnico $lecturaReporte,
        private readonly LecturaContrato $lecturaContrato,
        private readonly LecturaLotes $lecturaLotes,
    ) {}

    /**
     * @return list<array{
     *     reporteId: int,
     *     trabajoId: int,
     *     loteCodigo: string,
     *     nroAplicacion: int,
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

        $trabajosPorId = Trabajo::query()
            ->whereIn('id', array_unique(array_map(fn ($reporte) => $reporte->trabajoId, $reportes)))
            ->get()
            ->keyBy('id');

        $resumenesContratoPorId = [];
        $lotesPorId = [];
        $filas = [];

        foreach ($reportes as $reporte) {
            if ($desde !== null && $reporte->generadoEn->toDateString() < $desde) {
                continue;
            }

            if ($hasta !== null && $reporte->generadoEn->toDateString() > $hasta) {
                continue;
            }

            $resumenesContratoPorId[$reporte->contratoId] ??= $this->lecturaContrato->obtenerResumen($reporte->contratoId);
            $resumenContrato = $resumenesContratoPorId[$reporte->contratoId];

            if ($resumenContrato === null) {
                continue;
            }

            if ($clienteId !== null && $resumenContrato->clienteId !== $clienteId) {
                continue;
            }

            $lotesPorId[$reporte->loteId] ??= $this->lecturaLotes->obtenerPorId($reporte->loteId);
            $lote = $lotesPorId[$reporte->loteId];
            $trabajo = $trabajosPorId->get($reporte->trabajoId);

            $filas[] = [
                'reporteId' => $reporte->reporteId,
                'trabajoId' => $reporte->trabajoId,
                'loteCodigo' => $lote === null ? '—' : $lote->codigo,
                'nroAplicacion' => $trabajo === null ? 0 : $trabajo->nro_aplicacion,
                'clienteId' => $resumenContrato->clienteId,
                'clienteNombre' => $resumenContrato->clienteNombre,
                'generadoEn' => $reporte->generadoEn,
            ];
        }

        return $filas;
    }
}
