<?php

namespace App\Dominios\Solicitudes\Infraestructura\Console;

use App\Dominios\Solicitudes\Contratos\ReputacionDeClientes;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan solicitudes:evaluar-clientes`: una vez al día vuelve a aplicar la
 * política de calificación a los clientes que tienen un aviso vigente, para que
 * pasado el plazo sin recuperarse se limite su publicación (política de
 * calificación). Idempotente.
 */
final class EvaluarClientesCommand extends Command
{
    protected $signature = 'solicitudes:evaluar-clientes';

    protected $description = 'Aplica la política de calificación a los clientes con aviso vigente';

    public function handle(ReputacionDeClientes $reputacion): int
    {
        $evaluados = 0;

        foreach (Cliente::query()->whereNotNull('aviso_calificacion_en')->whereNull('publicacion_limitada_en')->get() as $cliente) {
            try {
                $reputacion->actualizar($cliente->id, (string) $cliente->calificacion_promedio, $cliente->calificaciones_conteo);
                $evaluados++;
            } catch (Throwable $error) {
                report($error);
            }
        }

        $this->info(sprintf('Clientes evaluados: %d.', $evaluados));

        return self::SUCCESS;
    }
}
