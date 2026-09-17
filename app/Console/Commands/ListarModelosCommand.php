<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Herramienta aditiva acordada en la revisión de ADR 0003 (27/8/2026): resuelve
 * "ver todos los modelos Eloquent de un vistazo" sin migrar la estructura de
 * carpetas feature-first a layer-first. Descubre los modelos recorriendo
 * `app/Dominios/*\/Infraestructura/Eloquent`, el mismo patrón que ya usa
 * `tests/Unit/ArquitecturaModulosTest.php` para descubrir módulos.
 */
class ListarModelosCommand extends Command
{
    protected $signature = 'modelos:listar';

    protected $description = 'Lista todos los modelos Eloquent del proyecto, agrupados por módulo de dominio';

    public function handle(): int
    {
        $rutaDominios = app_path('Dominios');

        if (! is_dir($rutaDominios)) {
            $this->error(__('ui.consola.listar_modelos_ruta_inexistente', ['ruta' => $rutaDominios]));

            return self::FAILURE;
        }

        $filas = [];

        foreach (glob($rutaDominios.'/*', GLOB_ONLYDIR) as $rutaModulo) {
            $modulo = basename($rutaModulo);
            $rutaEloquent = $rutaModulo.'/Infraestructura/Eloquent';

            if (! is_dir($rutaEloquent)) {
                continue;
            }

            foreach (glob($rutaEloquent.'/*.php') as $archivo) {
                $clase = basename($archivo, '.php');
                $fqcn = "App\\Dominios\\{$modulo}\\Infraestructura\\Eloquent\\{$clase}";

                if (! class_exists($fqcn) || ! is_subclass_of($fqcn, Model::class)) {
                    continue;
                }

                if ((new \ReflectionClass($fqcn))->isAbstract()) {
                    continue;
                }

                $tabla = (new $fqcn)->getTable();

                $filas[] = [$modulo, $clase, $tabla, $fqcn];
            }
        }

        if ($filas === []) {
            $this->warn(__('ui.consola.listar_modelos_vacio'));

            return self::SUCCESS;
        }

        usort($filas, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $this->table(__('ui.consola.listar_modelos_columnas'), $filas);
        $this->info(__('ui.consola.listar_modelos_resumen', [
            'cantidad' => count($filas),
            'modulos' => count(array_unique(array_column($filas, 0))),
        ]));

        return self::SUCCESS;
    }
}
