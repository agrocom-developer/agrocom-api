<?php

namespace App\Dominios\Comercial\Contratos;

/**
 * Frontera de lectura de Comercial hacia otros módulos (ADR 0003, regla 2),
 * en el sentido inverso a `Operaciones\Contratos\LecturaActaConformada`: acá
 * es `Operaciones` quien necesita, de un `contrato_id` propio (resuelto vía
 * su propia cadena `Trabajo → OrdenAplicacion`), el nombre del cliente al que
 * pertenece — sin importar `Contrato` ni `Cliente` directo (HU-43, tarea 57,
 * `Operaciones\Aplicacion\ListarReportesTecnicos`).
 */
interface LecturaContrato
{
    /** `null` si el contrato no existe o está borrado (soft delete). */
    public function obtenerResumen(int $contratoId): ?DatosResumenContrato;
}
