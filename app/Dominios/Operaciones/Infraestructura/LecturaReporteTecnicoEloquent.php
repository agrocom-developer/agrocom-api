<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosReporteTecnico;
use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;

/**
 * Implementación Eloquent del contrato de lectura de reporte técnico por
 * contrato. Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo
 * criterio que {@see LecturaActaConformadaEloquent}: esta clase no es un
 * modelo, es el adaptador que el `ServiceProvider` del módulo liga a
 * {@see LecturaReporteTecnico}.
 *
 * Resuelve `contrato_id`/`lote_id` subiendo la cadena `ReporteTecnico.trabajo_id
 * → Trabajo.orden_id/lote_id → OrdenAplicacion.contrato_id`, la consulta
 * nace del contrato (invariante 5 de CLAUDE.md): primero los `trabajo_id`
 * que cuelgan de una orden de ESE contrato, recién ahí los reportes de esos
 * trabajos — sin JOIN cross-tabla optimizado a propósito, mismo criterio que
 * `LecturaActaConformadaEloquent` (el volumen no lo justifica).
 */
final class LecturaReporteTecnicoEloquent implements LecturaReporteTecnico
{
    public function listarPorContrato(int $contratoId): array
    {
        $ordenIds = OrdenAplicacion::query()->where('contrato_id', $contratoId)->pluck('id');
        $trabajos = Trabajo::query()->whereIn('orden_id', $ordenIds)->get(['id', 'lote_id']);

        return ReporteTecnico::query()
            ->whereIn('trabajo_id', $trabajos->pluck('id'))
            ->orderByDesc('generado_en')
            ->get()
            ->map(fn (ReporteTecnico $reporte): DatosReporteTecnico => new DatosReporteTecnico(
                reporteId: $reporte->id,
                trabajoId: $reporte->trabajo_id,
                contratoId: $contratoId,
                loteId: $trabajos->firstWhere('id', $reporte->trabajo_id)->lote_id,
                horaInicio: $reporte->hora_inicio,
                horaFin: $reporte->hora_fin,
                pdfPath: $reporte->pdf_path,
                generadoEn: $reporte->generado_en,
            ))
            ->all();
    }
}
