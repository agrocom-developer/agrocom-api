<?php

namespace App\Dominios\Operaciones\Contratos;

use Carbon\CarbonImmutable;

/**
 * Forma de dato primitiva de un reporte técnico para quien lo necesita sin
 * importar el modelo Eloquent `ReporteTecnico` (ADR 0003, regla 2): hoy, el
 * caso de uso `Aplicacion/ListarReportesTecnicos` de este mismo módulo (HU-43,
 * tarea 57), que compone esta lista con `Comercial\Contratos\LecturaContrato`
 * para resolver cliente. `contratoId` viaja ya resuelto (subiendo
 * `Trabajo.orden_id → OrdenAplicacion.contrato_id`) para que el consumidor no
 * tenga que conocer esa cadena.
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
