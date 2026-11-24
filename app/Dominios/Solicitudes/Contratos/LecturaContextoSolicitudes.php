<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Frontera de lectura de `Solicitudes` hacia las pantallas y reportes de otros
 * módulos (ADR 0003, regla 2): dado un conjunto de ids de solicitud, devuelve
 * el contexto que se muestra junto a sus trabajos y sesiones. Una sola consulta
 * por conjunto, nunca una por fila.
 */
interface LecturaContextoSolicitudes
{
    /**
     * @param  list<int>  $ids
     * @return array<int, DatosContextoSolicitud> indexado por id; las que no existen no figuran.
     */
    public function porIds(array $ids): array;

    /**
     * Solicitudes por estado, para los contadores del tablero, en el orden del
     * ciclo (especificación §5.1).
     *
     * @return array<string, int> estado → cantidad (solo los estados con al menos una).
     */
    public function conteoPorEstado(): array;
}
