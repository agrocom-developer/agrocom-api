<?php

namespace App\Dominios\Negociacion\Infraestructura\Console;

use App\Dominios\Negociacion\Aplicacion\ProcesarNegociacion;
use Illuminate\Console\Command;

/** `php artisan negociacion:procesar`: vence ofertas, reevalúa solicitudes y expira las vencidas. Cada minuto; idempotente. */
final class ProcesarNegociacionCommand extends Command
{
    protected $signature = 'negociacion:procesar';

    protected $description = 'Vence las ofertas sin respuesta y expira las solicitudes cuya vigencia terminó';

    public function handle(ProcesarNegociacion $procesar): int
    {
        $resultado = $procesar->ejecutar();

        $this->info(sprintf('Ofertas vencidas: %d. Solicitudes expiradas: %d.', $resultado['ofertas_vencidas'], $resultado['solicitudes_expiradas']));

        return self::SUCCESS;
    }
}
