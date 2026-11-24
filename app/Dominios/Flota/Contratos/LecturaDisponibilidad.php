<?php

namespace App\Dominios\Flota\Contratos;

/**
 * Qué equipos están «en línea» (especificación del marketplace §4.4): el
 * proveedor se declara disponible desde la app para recibir ofertas. La
 * asignación lo lee por este contrato.
 */
interface LecturaDisponibilidad
{
    /**
     * @param  list<int>  $equipoIds
     * @return array<int, DatosDisponibilidad> por `equipo_id`; un equipo sin fila no declaró nada.
     */
    public function porEquipos(array $equipoIds): array;
}
