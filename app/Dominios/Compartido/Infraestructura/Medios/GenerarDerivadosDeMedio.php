<?php

namespace App\Dominios\Compartido\Infraestructura\Medios;

use App\Dominios\Compartido\Aplicacion\Medios\GeneradorDeDerivados;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Genera los derivados (miniatura y tamaño medio) de una imagen recién subida, en cola para no bloquear la
 * petición de subida. Idempotente: si ya existen, no hace nada. Un fallo se reporta y no se reintenta sin fin:
 * el original siempre sirve y `php artisan medios:regenerar` completa lo que falte.
 */
final class GenerarDerivadosDeMedio implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly string $ruta, public readonly ?string $disco = null)
    {
        $this->onQueue((string) config('medios.derivados.cola', 'medios'));
    }

    public function handle(GeneradorDeDerivados $generador): void
    {
        $generador->generar($this->ruta, $this->disco);
    }
}
