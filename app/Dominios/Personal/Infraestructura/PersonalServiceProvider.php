<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\LecturaDatosPersonales;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaFichaPersona;
use App\Dominios\Personal\Contratos\LecturaPersonas;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos de lectura del módulo a su implementación Eloquent (ADR
 * 0003, regla 2): las personas y los equipos de trabajo que la sincronización, el
 * perfil de cada cuenta y los avisos consultan. No tiene pantallas en el panel.
 */
final class PersonalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaPersonas::class, LecturaPersonasEloquent::class);
        $this->app->bind(LecturaFichaPersona::class, LecturaFichaPersonaEloquent::class);
        $this->app->bind(LecturaDatosPersonales::class, LecturaDatosPersonalesEloquent::class);
        $this->app->bind(LecturaEquipoTrabajo::class, LecturaEquipoTrabajoEloquent::class);
    }
}
