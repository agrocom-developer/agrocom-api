<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosReporteTecnico;
use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
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
 * Ambos métodos suben la cadena `ReporteTecnico.trabajo_id → Trabajo.orden_id
 * / lote_id → OrdenAplicacion.contrato_id`, sin JOIN cross-tabla optimizado
 * a propósito (mismo criterio que `LecturaActaConformadaEloquent`: el
 * volumen no lo justifica y las tres tablas son propias del módulo). La
 * diferencia está en dónde nace la consulta:
 *
 * - `listarTodos()` parte de los reportes y resuelve el contrato de cada uno.
 * - `listarPorContrato()` parte del contrato (invariante 5 de CLAUDE.md):
 *   primero los `trabajo_id` que cuelgan de una orden de ESE contrato, recién
 *   ahí los reportes de esos trabajos — nunca "todos y después filtrar".
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

    private function mapear(ReporteTecnico $reporte): DatosReporteTecnico
    {
        $trabajo = Trabajo::query()->findOrFail($reporte->trabajo_id);
        $orden = OrdenAplicacion::query()->findOrFail($trabajo->orden_id);

        return new DatosReporteTecnico(
            reporteId: $reporte->id,
            trabajoId: $trabajo->id,
            contratoId: $orden->contrato_id,
            loteId: $trabajo->lote_id,
            horaInicio: $reporte->hora_inicio,
            horaFin: $reporte->hora_fin,
            pdfPath: $reporte->pdf_path,
            generadoEn: $reporte->generado_en,
        );
    }
}
