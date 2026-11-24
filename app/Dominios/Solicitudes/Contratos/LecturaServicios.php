<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Lo que calificaciones y disputas leen de una solicitud sin importar sus
 * modelos (ADR 0003, regla 2). Toda escritura sobre la solicitud pasa por la
 * máquina de estados: ver {@see ResolucionDeServicio}.
 */
interface LecturaServicios
{
    public function datos(int $solicitudId): ?DatosServicio;
}
