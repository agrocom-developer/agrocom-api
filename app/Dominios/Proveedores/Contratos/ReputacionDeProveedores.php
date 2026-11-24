<?php

namespace App\Dominios\Proveedores\Contratos;

/**
 * Escritura de las tasas de aceptación y de cancelación de un proveedor
 * (especificación §4.3). Las calcula la asignación a partir de sus ofertas y
 * cancelaciones; `Proveedores` solo las guarda en su ficha, porque cada módulo
 * escribe únicamente sus tablas (ADR 0003).
 */
interface ReputacionDeProveedores
{
    /**
     * @param  string  $tasaAceptacion  decimal entre 0 y 1, cuatro decimales.
     * @param  string  $tasaCancelacion  decimal entre 0 y 1, cuatro decimales.
     */
    public function actualizarTasas(int $proveedorId, string $tasaAceptacion, string $tasaCancelacion): void;
}
