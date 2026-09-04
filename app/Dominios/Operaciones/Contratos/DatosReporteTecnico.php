<?php

namespace App\Dominios\Operaciones\Contratos;

use Carbon\CarbonImmutable;

/**
 * Forma de dato primitiva de un reporte técnico para quien lo necesita sin
 * importar el modelo Eloquent `ReporteTecnico` (ADR 0003, regla 2): hoy,
 * `Portal` (HU-41, tarea 55) para listar los reportes del contrato del
 * cliente autenticado. Mismo criterio "solo primitivos" que
 * `DatosActaConformada`/`DatosSesionValidada`.
 */
final readonly class DatosReporteTecnico
{
    public function __construct(
        public int $reporteId,
        public int $trabajoId,
        public int $contratoId,
        public int $loteId,
        public ?CarbonImmutable $horaInicio,
        public ?CarbonImmutable $horaFin,
        public ?string $pdfPath,
        public CarbonImmutable $generadoEn,
    ) {}
}
