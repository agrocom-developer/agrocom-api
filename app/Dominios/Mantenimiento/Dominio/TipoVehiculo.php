<?php

namespace App\Dominios\Mantenimiento\Dominio;

/**
 * Clasificación de un vehículo de la flota (HU-90, tarea 105;
 * `man_vehiculos.tipo`, CHECK en la migración). Catálogo cerrado, mismo
 * patrón que `TipoCombustibleVehiculo`: backed enum validado en el Request
 * con `Rule::enum()`. `Chata` es el valor que pidió el dueño; los otros tres
 * salen de los prefijos ya usados en el seeder demo (`CAM-`, `MOT-`) — ver
 * el docblock de la migración para el porqué completo.
 */
enum TipoVehiculo: string
{
    case Camioneta = 'camioneta';
    case Camion = 'camion';
    case Moto = 'moto';
    case Chata = 'chata';
}
