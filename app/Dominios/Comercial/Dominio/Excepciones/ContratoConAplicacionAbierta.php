<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use DomainException;

/**
 * Un contrato no se puede cancelar ni finalizar mientras tenga una aplicación
 * abierta (`emitida`, `vigente` o `pausada`; pedido del dueño, 18/9/2026, ADR
 * 0022): primero se cierra o se cancela esa aplicación. Así no queda una orden
 * vigente circulando por la app de campo sobre un contrato que ya no existe. Con
 * la aplicación cancelada, el contrato SÍ se puede cancelar o finalizar aunque
 * queden aplicaciones pendientes — el dueño decide, p. ej. por falta de pago.
 *
 * La verifica `Aplicacion/MaquinaEstados/MaquinaEstadosContrato`, leyendo las
 * órdenes por el contrato de `Operaciones` (`LecturaResumenOrdenesContrato`).
 */
final class ContratoConAplicacionAbierta extends DomainException
{
    public static function paraContrato(int $contratoId): self
    {
        return new self(Texto::de('comercial.errores.contrato_con_aplicacion_abierta', ['id' => $contratoId]));
    }
}
