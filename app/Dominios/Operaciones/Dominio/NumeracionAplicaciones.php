<?php

namespace App\Dominios\Operaciones\Dominio;

use App\Dominios\Operaciones\Dominio\Excepciones\AplicacionesCompletas;
use App\Dominios\Operaciones\Dominio\Excepciones\ContratoConOrdenAbierta;

/**
 * Regla pura de numeración de las aplicaciones de un contrato (pedido del
 * dueño, 18/9/2026, ADR 0022). El número lo calcula el servidor, nunca se
 * elige: la primera orden de un contrato es la aplicación 1, y cada nueva
 * lleva el que sigue.
 *
 * - Solo puede haber UNA aplicación abierta (`emitida`, `vigente`, `pausada`):
 *   mientras exista, no se numera otra.
 * - Una aplicación cancelada por `fuerza_mayor` NO consume su número (se rehace
 *   con el mismo); una cancelada por causa del `cliente` SÍ lo consume, igual
 *   que cualquier orden cerrada — ver {@see CausaCancelacionOrden::consumeNumero()}.
 * - El siguiente número no puede pasar de las `aplicaciones_previstas` del
 *   contrato.
 *
 * Sin Eloquent ni base de datos: recibe las órdenes ya leídas. La atomicidad
 * frente a dos altas simultáneas la da la base (índices únicos parciales),
 * no esta clase.
 */
final class NumeracionAplicaciones
{
    /**
     * @param  list<array{nro: int, estado: EstadoOrdenAplicacion, causa: ?CausaCancelacionOrden}>  $ordenes  todas las órdenes NO borradas del contrato
     *
     * @throws ContratoConOrdenAbierta si alguna orden sigue abierta.
     * @throws AplicacionesCompletas si el contrato ya agotó sus aplicaciones previstas.
     */
    public static function siguiente(array $ordenes, int $aplicacionesPrevistas, int $contratoId): int
    {
        $mayorNumeroConsumido = 0;

        foreach ($ordenes as $orden) {
            if ($orden['estado']->estaAbierta()) {
                throw ContratoConOrdenAbierta::paraContrato($contratoId, $orden['nro']);
            }

            $liberaSuNumero = $orden['estado'] === EstadoOrdenAplicacion::Cancelada
                && $orden['causa'] !== null
                && ! $orden['causa']->consumeNumero();

            if (! $liberaSuNumero) {
                $mayorNumeroConsumido = max($mayorNumeroConsumido, $orden['nro']);
            }
        }

        $siguiente = $mayorNumeroConsumido + 1;

        if ($siguiente > $aplicacionesPrevistas) {
            throw AplicacionesCompletas::paraContrato($contratoId, $aplicacionesPrevistas);
        }

        return $siguiente;
    }
}
