<?php

namespace App\Dominios\Mezclas\Contratos;

/**
 * Frontera de lectura de `Mezclas` hacia otros módulos (ADR 0003, regla 2).
 * Único consumidor hoy: `Operaciones\Aplicacion\ArmarContenidoReporteTecnico`
 * (espec §7, HU-78, tarea 94), para listar en el reporte técnico los
 * productos cargados en TODAS las mezclas del trabajo — puede haber más de
 * un evento `mezcla` por trabajo, mismo criterio que `ope_recepciones_caldo`.
 */
interface LecturaMezclas
{
    /** @return list<ProductoCargado> */
    public function listarPorTrabajoId(int $trabajoId): array;
}
