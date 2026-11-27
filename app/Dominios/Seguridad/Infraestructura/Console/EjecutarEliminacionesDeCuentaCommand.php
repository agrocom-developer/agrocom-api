<?php

namespace App\Dominios\Seguridad\Infraestructura\Console;

use App\Dominios\Seguridad\Aplicacion\EjecutarEliminacionesDeCuenta;
use Illuminate\Console\Command;

/**
 * `php artisan cuentas:ejecutar-eliminaciones`: una vez al día ejecuta las eliminaciones de cuenta cuya gracia venció
 * (ADR 0047, punto 5). Idempotente.
 */
final class EjecutarEliminacionesDeCuentaCommand extends Command
{
    protected $signature = 'cuentas:ejecutar-eliminaciones';

    protected $description = 'Anonimiza las cuentas cuya eliminación pasó el periodo de gracia';

    public function handle(EjecutarEliminacionesDeCuenta $ejecutar): int
    {
        $resultado = $ejecutar->ejecutar();

        $this->info(sprintf('Eliminaciones ejecutadas: %d. Pospuestas: %d.', $resultado['ejecutadas'], $resultado['pospuestas']));

        return self::SUCCESS;
    }
}
