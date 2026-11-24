<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Solicitudes` (especificación del marketplace §4.1 y §4.2,
 * ADR 0027 y 0036). Por ahora aloja al cliente y las zonas; la solicitud de
 * servicio y sus terrenos llegan en la tarea de solicitudes.
 */
final class SolicitudesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El registro del cliente usa el contrato `RegistroCuentas` que
        // Seguridad enlaza; acá solo se ofrece la lectura de zonas.
        $this->app->bind(LecturaZonas::class, LecturaZonasEloquent::class);
    }

    public function boot(): void
    {
        // Sin vistas ni oyentes todavía.
    }
}
