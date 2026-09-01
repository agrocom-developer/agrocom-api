<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use Illuminate\Support\ServiceProvider;

/**
 * Liga el contrato de lectura del módulo a su implementación Eloquent (ADR
 * 0003, regla 2). Mismo patrón que `SeguridadServiceProvider` — cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención (una interfaz no se autoresuelve sola).
 */
final class ComercialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaLotes::class, LecturaLotesEloquent::class);
    }
}
