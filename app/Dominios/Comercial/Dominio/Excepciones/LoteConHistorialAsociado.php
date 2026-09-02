<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * HU-24 (tarea 35): decisión de negocio de esta tarea, no una regla que ya
 * estuviera escrita en la especificación — ver el docblock de
 * `Aplicacion/ActualizarCampo::tieneHistorialAsociado()` para el porqué.
 *
 * Un lote que ya tiene órdenes de aplicación u trabajos ejecutados en
 * `Operaciones` no se puede quitar del formulario del campo: el soft delete
 * (invariante 8) dejaría el lote inaccesible desde el panel con historial de
 * negocio real colgando de un padre que ya no se puede ver ni editar. Se
 * rechaza con un mensaje claro en vez de dejarlo pasar en silencio.
 */
final class LoteConHistorialAsociado extends RuntimeException
{
    public static function paraLote(string $codigo): self
    {
        return new self("El lote '{$codigo}' tiene órdenes de aplicación o trabajos asociados y no se puede eliminar.");
    }
}
