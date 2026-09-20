<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Frontera de lectura de Finanzas hacia otros módulos (ADR 0003, regla 2): la
 * ficha de un vehículo o de un generador muestra, en su resumen relacionado,
 * cuántas cargas de combustible se le imputaron y cuántos litros, sin importar
 * el modelo `Combustible` ni la tabla `fin_combustibles`.
 *
 * `fin_combustibles` guarda `recurso_tipo` + `recurso_id` sin FK (mismo
 * criterio que `per_equipo_recursos`), así que el contrato recibe los dos
 * datos y no un id suelto.
 */
interface LecturaCombustiblePorRecurso
{
    public const TIPO_VEHICULO = 'vehiculo';

    public const TIPO_GENERADOR = 'generador';

    /**
     * Cargas de combustible (no dadas de baja) imputadas al recurso. Sin
     * cargas, cantidad 0 y litros `0.00`.
     *
     * @param  self::TIPO_*  $recursoTipo
     */
    public function deRecurso(string $recursoTipo, int $recursoId): DatosCombustibleRecurso;
}
