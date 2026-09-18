<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia Comercial (ADR 0003, regla 2): por
 * cada contrato, qué lotes de ESE contrato específico ya tienen una orden de
 * aplicación registrada — nunca las tablas `ope_orden_lotes`/
 * `ope_ordenes_aplicacion` ni sus modelos Eloquent cruzando la frontera.
 *
 * Distinto de `VerificadorHistorialLote` (Comercial): ese resuelve "¿este
 * lote tiene historial en algún lado?" (global, para bloquear la baja del
 * lote del catálogo). Esto responde una pregunta más puntual — "¿este
 * contrato puntual ya tiene una orden sobre este lote?" — necesaria para
 * decidir si un lote se puede quitar de UN contrato en particular cuando el
 * mismo lote terminó, sin guarda que lo impida, en más de un contrato de la
 * misma campaña.
 */
interface LecturaLotesConOrdenPorContrato
{
    /**
     * @param  list<int>  $contratoIds
     * @return array<int, list<int>> contrato_id => lote_ids con al menos una
     *                               orden de aplicación registrada en ESE contrato.
     */
    public function loteIds(array $contratoIds): array;
}
