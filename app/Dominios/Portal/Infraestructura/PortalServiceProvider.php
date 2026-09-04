<?php

namespace App\Dominios\Portal\Infraestructura;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Registra el namespace de vista `portal::` (HU-41, tarea 55; mismo patrón
 * que `OperacionesServiceProvider`): las páginas Blade del módulo viven bajo
 * `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 *
 * Sin `register()`: `Portal` no tiene tablas propias (ADR 0011, solo
 * lectura) ni contratos propios que otro módulo consuma — reusa los de
 * `Seguridad`/`Comercial`/`Operaciones` tal cual, así que no hay ningún bind
 * que declarar acá.
 */
final class PortalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('portal', app_path('Dominios/Portal/Infraestructura/Http/Views'));
    }
}
