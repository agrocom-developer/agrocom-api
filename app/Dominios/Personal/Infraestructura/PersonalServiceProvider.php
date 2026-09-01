<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Personal\Contratos\LecturaTarifaPersona;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos de lectura del módulo a su implementación Eloquent (ADR
 * 0003, regla 2). Mismo patrón que `SeguridadServiceProvider` — cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención (una interfaz no se autoresuelve sola).
 */
final class PersonalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaPersonas::class, LecturaPersonasEloquent::class);
        $this->app->bind(LecturaTarifaPersona::class, LecturaTarifaPersonaEloquent::class);
    }
}
