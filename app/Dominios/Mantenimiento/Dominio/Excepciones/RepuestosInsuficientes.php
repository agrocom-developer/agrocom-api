<?php

namespace App\Dominios\Mantenimiento\Dominio\Excepciones;

use RuntimeException;

/**
 * La guarda de negocio de `MaquinaEstadosOrdenMantenimiento::cerrar()`: no
 * se puede cerrar una orden si el stock de algún repuesto de la lista no
 * alcanza (HU-37, tarea 53). Envuelve la
 * `App\Dominios\Inventario\Dominio\Excepciones\StockInsuficiente` que lanza
 * el contrato `EscrituraConsumoStock` de `Inventario` — esa excepción del
 * otro módulo no debe escapar tal cual hasta el controller (ADR 0003 regla
 * 3: un módulo no conoce las excepciones internas de otro más allá del
 * contrato).
 */
final class RepuestosInsuficientes extends RuntimeException
{
    public static function paraRepuesto(int $repuestoId, string $mensajeOriginal): self
    {
        return new self(
            "No se puede cerrar la orden: stock insuficiente para el repuesto #{$repuestoId} ({$mensajeOriginal}).",
        );
    }
}
