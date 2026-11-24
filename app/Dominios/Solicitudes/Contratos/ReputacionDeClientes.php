<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Escritura de la reputación de un cliente (especificación §4.1): su calificación
 * promedio la calcula el módulo de calificaciones y `Solicitudes` la guarda en la
 * ficha, aplica la política (aviso y limitación de la publicación) y lo avisa.
 */
interface ReputacionDeClientes
{
    /**
     * @param  string  $promedio  decimal entre 0 y 5, dos decimales.
     * @param  int  $conteo  calificaciones visibles que entraron al promedio.
     */
    public function actualizar(int $clienteId, string $promedio, int $conteo): void;
}
