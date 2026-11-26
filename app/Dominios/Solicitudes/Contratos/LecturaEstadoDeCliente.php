<?php

namespace App\Dominios\Solicitudes\Contratos;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;

/**
 * El estado de aprobación de la ficha de cliente de una cuenta (ADR 0046, punto 12), para la compuerta
 * de la negociación: aceptar y contraofertar son del cliente `aprobado`. Sin importar el modelo `Cliente`.
 */
interface LecturaEstadoDeCliente
{
    /** El estado de la ficha de cliente de la cuenta, o `null` si la cuenta no tiene ficha. */
    public function deLaCuenta(int $usuarioId): ?EstadoAprobacion;
}
