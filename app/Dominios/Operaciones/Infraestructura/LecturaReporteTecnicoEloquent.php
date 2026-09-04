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
 * `LecturaActaConformadaEloquent`: esa subcarpeta está reservada a modelos
 * que extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige) — esta clase no es un modelo, es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaReporteTecnico}.
 *
 * Resuelve `contrato_id` subiendo la cadena `ReporteTecnico.trabajo_id →
 * Trabajo.orden_id → OrdenAplicacion.contrato_id` con tres consultas por
 * reporte, sin JOIN cross-tabla optimizado a propósito: mismo criterio que
 * `LecturaActaConformadaEloquent` — el volumen de reportes no lo justifica, y
 * las tres tablas son propias del módulo, así que no hay costo de
 * acoplamiento por mantenerlo simple.
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
