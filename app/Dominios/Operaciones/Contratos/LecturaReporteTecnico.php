<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia otros módulos (ADR 0003, regla
 * 2): `Portal` (HU-41, tarea 55) necesita, de los reportes técnicos de un
 * contrato, sus datos primitivos sin importar `ReporteTecnico`, `Trabajo` ni
 * `OrdenAplicacion`. Resuelve la cadena `ReporteTecnico.trabajo_id →
 * Trabajo.orden_id/lote_id → OrdenAplicacion.contrato_id` puertas adentro de
 * este módulo — mismo molde exacto que {@see LecturaActaConformada}.
 */
interface LecturaReporteTecnico
{
    /** @return list<DatosReporteTecnico> reportes técnicos del contrato dado */
    public function listarPorContrato(int $contratoId): array;
}
