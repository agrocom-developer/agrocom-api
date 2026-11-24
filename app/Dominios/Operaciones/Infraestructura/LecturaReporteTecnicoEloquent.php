<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosReporteTecnico;
use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;

/**
 * Implementación Eloquent del contrato de lectura de reporte técnico. Vive
 * fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * {@see LecturaActaConformadaEloquent}: esa subcarpeta está reservada a
 * modelos que extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php`
 * lo exige) — esta clase no es un modelo, es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaReporteTecnico}.
 *
 * Ambos métodos suben `ReporteTecnico.trabajo_id → Trabajo.solicitud_id`,
 * sin JOIN cross-tabla optimizado a propósito (mismo criterio que
 * `LecturaActaConformadaEloquent`: el volumen no lo justifica y las dos tablas
 * son propias del módulo). La diferencia está en dónde nace la consulta:
 *
 * - `listarTodos()` parte de los reportes y resuelve la solicitud de cada uno.
 * - `listarPorSolicitud()` parte de la solicitud (invariante 5 de CLAUDE.md):
 *   primero los `trabajo_id` de ESA solicitud, recién ahí los reportes de esos
 *   trabajos — nunca «todos y después filtrar».
 */
final class LecturaReporteTecnicoEloquent implements LecturaReporteTecnico
{
    public function listarTodos(): array
    {
        return ReporteTecnico::query()
            ->orderByDesc('generado_en')
            ->get()
            ->map($this->mapear(...))
            ->all();
    }

    public function listarPorSolicitud(int $solicitudId): array
    {
        $trabajos = Trabajo::query()->where('solicitud_id', $solicitudId)->get(['id', 'solicitud_id']);

        return ReporteTecnico::query()
            ->whereIn('trabajo_id', $trabajos->pluck('id'))
            ->orderByDesc('generado_en')
            ->get()
            ->map(fn (ReporteTecnico $reporte): DatosReporteTecnico => new DatosReporteTecnico(
                reporteId: $reporte->id,
                trabajoId: $reporte->trabajo_id,
                solicitudId: $solicitudId,
                horaInicio: $reporte->hora_inicio,
                horaFin: $reporte->hora_fin,
                pdfPath: $reporte->pdf_path,
                generadoEn: $reporte->generado_en,
            ))
            ->all();
    }

    private function mapear(ReporteTecnico $reporte): DatosReporteTecnico
    {
        $trabajo = Trabajo::query()->findOrFail($reporte->trabajo_id);

        return new DatosReporteTecnico(
            reporteId: $reporte->id,
            trabajoId: $trabajo->id,
            solicitudId: (int) $trabajo->solicitud_id,
            horaInicio: $reporte->hora_inicio,
            horaFin: $reporte->hora_fin,
            pdfPath: $reporte->pdf_path,
            generadoEn: $reporte->generado_en,
        );
    }
}
