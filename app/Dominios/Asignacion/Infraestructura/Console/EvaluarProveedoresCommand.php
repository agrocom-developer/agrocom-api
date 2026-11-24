<?php

namespace App\Dominios\Asignacion\Infraestructura\Console;

use App\Dominios\Asignacion\Aplicacion\EvaluarProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan asignacion:evaluar-proveedores`: una vez al día recalcula las
 * tasas y aplica la política de sanciones a cada proveedor aprobado. Cubre lo
 * que no pasa por una oferta: la calificación, que escribe otro módulo, y las
 * ventanas que se cierran con el paso de los días. Idempotente.
 */
final class EvaluarProveedoresCommand extends Command
{
    protected $signature = 'asignacion:evaluar-proveedores';

    protected $description = 'Recalcula las tasas y aplica las sanciones de la política a los proveedores aprobados';

    public function handle(EvaluarProveedor $evaluar, LecturaProveedores $proveedores): int
    {
        $sanciones = 0;
        $evaluados = 0;

        foreach (['propio', 'tercero'] as $grupo) {
            foreach ($proveedores->aprobadosDelGrupo($grupo) as $proveedor) {
                try {
                    $sanciones += count($evaluar->ejecutar($proveedor->id));
                    $evaluados++;
                } catch (Throwable $error) {
                    report($error);
                }
            }
        }

        $this->info(sprintf('Proveedores evaluados: %d. Sanciones aplicadas: %d.', $evaluados, $sanciones));

        return self::SUCCESS;
    }
}
