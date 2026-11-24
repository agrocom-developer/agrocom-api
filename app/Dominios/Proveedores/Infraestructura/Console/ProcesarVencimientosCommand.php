<?php

namespace App\Dominios\Proveedores\Infraestructura\Console;

use App\Dominios\Proveedores\Aplicacion\ProcesarVencimientos;
use Illuminate\Console\Command;

/**
 * `php artisan proveedores:vencimientos`: el proceso diario de documentos por
 * vencer y vencidos (ADR 0032, punto 4). Se programa una vez al día en
 * `routes/console.php`; es idempotente, correrlo a mano no repite avisos.
 */
final class ProcesarVencimientosCommand extends Command
{
    protected $signature = 'proveedores:vencimientos';

    protected $description = 'Avisa los documentos por vencer, marca los vencidos y suspende a sus sujetos';

    public function handle(ProcesarVencimientos $procesar): int
    {
        $resumen = $procesar->ejecutar();

        $this->info(sprintf(
            'Avisos a 30 días: %d. Avisos a 7 días: %d. Documentos vencidos: %d. Sujetos suspendidos: %d.',
            $resumen['avisos_30'],
            $resumen['avisos_7'],
            $resumen['vencidos'],
            $resumen['suspendidos'],
        ));

        return self::SUCCESS;
    }
}
