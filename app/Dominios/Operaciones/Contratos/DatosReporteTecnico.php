<?php

namespace App\Dominios\Operaciones\Contratos;

use Carbon\CarbonImmutable;

/**
 * Forma de dato primitiva de un reporte técnico para quien lo necesita sin
 * importar el modelo Eloquent `ReporteTecnico` (ADR 0003, regla 2): el caso de
 * uso `Aplicacion/ListarReportesTecnicos` de este mismo módulo (HU-43, tarea
 * 57), que compone esta lista con `Solicitudes\Contratos\LecturaContextoSolicitudes`
 * para resolver terreno y cliente. `solicitudId` viaja ya resuelto (el
 * `trabajo.solicitud_id`) para que el consumidor no tenga que conocer esa
 * relación.
 */
final readonly class DatosReporteTecnico
{
    public function __construct(
        public int $reporteId,
        public int $trabajoId,
        public int $solicitudId,
        public ?CarbonImmutable $horaInicio,
        public ?CarbonImmutable $horaFin,
        public ?string $pdfPath,
        public CarbonImmutable $generadoEn,
    ) {}
}
