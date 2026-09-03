<?php

namespace App\Dominios\Mantenimiento\Dominio;

/**
 * Estados de `man_ordenes_mantenimiento.estado` (HU-37, tarea 53; CHECK en
 * la migración). A diferencia de `EstadoVehiculo`/`EstadoBateria`, esta SÍ
 * es una máquina de estados de negocio (CLAUDE.md invariante 7 aplica): la
 * transición `Abierta → Cerrada` tiene una guarda real (repuestos
 * disponibles) y un efecto de dominio (consume stock, genera gasto) — ver
 * `Aplicacion/MaquinaEstados/MaquinaEstadosOrdenMantenimiento`.
 */
enum EstadoOrdenMantenimiento: string
{
    case Abierta = 'abierta';
    case Cerrada = 'cerrada';
}
