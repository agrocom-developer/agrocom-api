<?php

namespace App\Dominios\Compartido\Infraestructura;

use App\Dominios\Compartido\Contratos\LecturaConfiguracion;
use Illuminate\Support\ServiceProvider;

/**
 * Primer `ServiceProvider` de `Compartido` (tarea 78, HU-55): hasta acá el
 * módulo se resolvía entero por convención (Eloquent/autoload), sin nada que
 * un `ServiceProvider` necesitara exponer al framework.
 */
final class CompartidoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Frontera de Compartido hacia el resto de los módulos (ADR 0003,
        // regla 2): un consumidor pide una clave de configuración sin conocer
        // `Configuracion` ni el cifrado que hay detrás.
        $this->app->bind(LecturaConfiguracion::class, ResolutorConfiguracion::class);
    }
}
