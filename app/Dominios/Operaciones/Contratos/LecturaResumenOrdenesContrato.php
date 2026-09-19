<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia Comercial (ADR 0003, regla 2):
 * el resumen de Órdenes de Aplicación de los contratos de un cliente, para
 * el aside de `ClientesController::resumenRelacionado()` — nunca la tabla
 * `ope_ordenes_aplicacion` ni el modelo Eloquent `OrdenAplicacion` cruzando
 * la frontera hacia Comercial.
 */
interface LecturaResumenOrdenesContrato
{
    /**
     * `abiertas`: órdenes cuya aplicación sigue en curso (`emitida`, `vigente`
     * o `pausada`, ADR 0022) — un contrato con alguna no se puede cancelar ni
     * finalizar, y no admite emitir otra.
     *
     * @param  list<int>  $contratoIds
     * @return array{total: int, vigentes: int, abiertas: int}
     */
    public function resumen(array $contratoIds): array;

    /**
     * Número de la próxima aplicación que admite el contrato, o `null` si hoy no
     * admite una orden nueva (tiene una aplicación abierta o ya agotó sus
     * `aplicaciones_previstas`) — la misma regla que aplica el servidor al
     * emitir la orden (ADR 0022), para que Comercial habilite o deshabilite
     * "Nueva orden" sin duplicarla. No mira el estado del contrato: eso es de
     * Comercial.
     */
    public function siguienteAplicacion(int $contratoId, int $aplicacionesPrevistas): ?int;
}
