<?php

namespace App\Dominios\Solicitudes\Infraestructura\Console;

use App\Dominios\Solicitudes\Aplicacion\CompletarSolicitudesVencidas;
use Illuminate\Console\Command;

/**
 * `php artisan solicitudes:completar-vencidas`: da por completadas, por
 * conformidad tácita, las solicitudes que llevan más del plazo en `ejecutada`
 * sin conformidad ni disputa (especificación §5.1). Idempotente.
 */
final class CompletarSolicitudesVencidasCommand extends Command
{
    protected $signature = 'solicitudes:completar-vencidas';

    protected $description = 'Completa por conformidad tácita las solicitudes ejecutadas cuyo plazo venció';

    public function handle(CompletarSolicitudesVencidas $completar): int
    {
        $this->info(sprintf('Solicitudes completadas por conformidad tácita: %d.', $completar->ejecutar()));

        return self::SUCCESS;
    }
}
