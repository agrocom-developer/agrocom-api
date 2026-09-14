<?php

namespace App\Dominios\Mantenimiento\Dominio;

/**
 * Combustible de un vehículo de la flota (HU-84, tarea 99;
 * `man_vehiculos.combustible`, CHECK en la migración). Catálogo cerrado,
 * mismo patrón que `EstadoVehiculo`/`EstadoGenerador`: backed enum validado
 * en el Request con `Rule::enum()`, sin escala de valores intermedios.
 */
enum TipoCombustibleVehiculo: string
{
    case Gasolina = 'gasolina';
    case Diesel = 'diesel';
}
