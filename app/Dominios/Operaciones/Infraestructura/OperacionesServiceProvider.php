<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\LecturaOrdenesVigentes;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos del módulo a su implementación Eloquent (ADR 0003,
 * regla 2). Mismo patrón que `SeguridadServiceProvider` — cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención (una interfaz no se autoresuelve sola).
 */
final class OperacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaOrdenesVigentes::class, LecturaOrdenesVigentesEloquent::class);
        $this->app->bind(EscrituraSincronizacion::class, EscrituraSincronizacionEloquent::class);
    }
}
