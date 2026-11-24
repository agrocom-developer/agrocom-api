<?php

namespace App\Dominios\Asignacion\Infraestructura\Console;

use App\Dominios\Asignacion\Aplicacion\IniciarAsignacion;
use App\Dominios\Asignacion\Aplicacion\VencerOfertas;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan asignacion:procesar`: el proceso programado del motor (ADR 0030,
 * «hace falta un proceso programado»). Cada minuto toma las solicitudes
 * publicadas que el oyente no alcanzó a tomar, vence las ofertas sin respuesta
 * y abre la ronda siguiente. Idempotente: correrlo dos veces seguidas no repite
 * ofertas ni avisos.
 */
final class ProcesarAsignacionesCommand extends Command
{
    protected $signature = 'asignacion:procesar';

    protected $description = 'Toma las solicitudes publicadas, vence las ofertas sin respuesta y abre la ronda siguiente';

    public function handle(IniciarAsignacion $iniciar, VencerOfertas $vencer, LecturaSolicitudesParaAsignar $solicitudes): int
    {
        $tomadas = 0;

        foreach ($solicitudes->publicadasSinTomar(100) as $solicitudId) {
            try {
                $tomadas += $iniciar->ejecutar($solicitudId) ? 1 : 0;
            } catch (Throwable $error) {
                report($error);
            }
        }

        $vencidas = $vencer->ejecutar();

        $this->info(sprintf('Solicitudes tomadas: %d. Ofertas vencidas: %d.', $tomadas, $vencidas));

        return self::SUCCESS;
    }
}
