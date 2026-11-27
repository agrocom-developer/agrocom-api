<?php

namespace App\Dominios\Negociacion\Infraestructura\Console;

use App\Dominios\Negociacion\Aplicacion\ProcesarNegociacion;
use Illuminate\Console\Command;

/** `php artisan negociacion:procesar`: vence ofertas y reevalúa las solicitudes que se quedaron sin ofertas vigentes. Cada minuto; idempotente. */
final class ProcesarNegociacionCommand extends Command
{
    protected $signature = 'negociacion:procesar';

    protected $description = 'Vence las ofertas sin respuesta y reevalúa las solicitudes que se quedaron sin ofertas vigentes';

    public function handle(ProcesarNegociacion $procesar): int
    {
        $resultado = $procesar->ejecutar();

        $this->info(sprintf('Ofertas vencidas: %d.', $resultado['ofertas_vencidas']));

        return self::SUCCESS;
    }
}
