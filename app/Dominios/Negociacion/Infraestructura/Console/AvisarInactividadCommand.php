<?php

namespace App\Dominios\Negociacion\Infraestructura\Console;

use App\Dominios\Negociacion\Aplicacion\ParametrosDeNegociacion;
use App\Dominios\Negociacion\Aplicacion\ProcesarNegociacion;
use Illuminate\Console\Command;

/** `php artisan seguimiento:avisar-inactividad`: avisa a las partes de una solicitud en ejecución sin avance. Cada hora; idempotente por día. */
final class AvisarInactividadCommand extends Command
{
    protected $signature = 'seguimiento:avisar-inactividad';

    protected $description = 'Avisa a las partes si una solicitud en ejecución no tiene avance reciente';

    public function handle(ProcesarNegociacion $procesar, ParametrosDeNegociacion $parametros): int
    {
        $this->info(sprintf('Solicitudes avisadas: %d.', $procesar->avisarInactividad($parametros)));

        return self::SUCCESS;
    }
}
