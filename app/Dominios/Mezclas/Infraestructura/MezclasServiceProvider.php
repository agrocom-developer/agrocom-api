<?php

namespace App\Dominios\Mezclas\Infraestructura;

use App\Dominios\Mezclas\Contratos\EscrituraMezclas;
use App\Dominios\Mezclas\Contratos\LecturaMezclas;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos del módulo a su implementación Eloquent (ADR 0003,
 * regla 2). Mismo patrón que `OperacionesServiceProvider`.
 */
final class MezclasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EscrituraMezclas::class, EscrituraMezclasEloquent::class);
        $this->app->bind(LecturaMezclas::class, LecturaMezclasEloquent::class);
    }
}
