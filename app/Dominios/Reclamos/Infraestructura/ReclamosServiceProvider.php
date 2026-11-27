<?php

namespace App\Dominios\Reclamos\Infraestructura;

use App\Dominios\Reclamos\Aplicacion\EliminacionDeReclamos;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Reclamos` (especificación del marketplace §4.8, ADR 0033 y
 * 0039): el reclamo de un servicio, su mediación y sus reembolsos. Solo registra el
 * namespace de vistas `reclamos::`: lee y mueve la solicitud por los contratos de
 * `Solicitudes`, que ese módulo enlaza.
 */
final class ReclamosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Eliminación de cuenta (ADR 0047): un reclamo abierto la impide; Seguridad lo recibe por tag.
        $this->app->tag(EliminacionDeReclamos::class, 'eliminacion.participantes');
    }

    public function boot(): void
    {
        View::addNamespace('reclamos', app_path('Dominios/Reclamos/Infraestructura/Http/Views'));
    }
}
