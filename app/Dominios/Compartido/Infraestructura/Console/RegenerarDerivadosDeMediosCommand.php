<?php

namespace App\Dominios\Compartido\Infraestructura\Console;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\GeneradorDeDerivados;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regenera la miniatura y el tamaño medio de las imágenes que ya están guardadas (las anteriores a los derivados, o
 * las que se perdieron). Nunca toca el original. Idempotente: sin `--forzar` solo genera lo que falta.
 */
final class RegenerarDerivadosDeMediosCommand extends Command
{
    protected $signature = 'medios:regenerar
        {--dominio= : Solo uno: documentos, dron_fotos, terreno_fotos, avance o chat}
        {--forzar : Vuelve a generar aunque ya existan}
        {--en-cola : Encola la generación en vez de hacerla aquí}
        {--limite=0 : Máximo de archivos a revisar (0 = todos)}';

    protected $description = 'Genera las miniaturas y los tamaños medios que falten de los archivos multimedia (el original no se toca)';

    /** @var array<string, array{tabla: string, columna: string, filtro?: array<string, string>}> */
    private const FUENTES = [
        'documentos' => ['tabla' => 'prv_documentos', 'columna' => 'archivo_ref'],
        'dron_fotos' => ['tabla' => 'flo_dron_fotos', 'columna' => 'archivo'],
        'terreno_fotos' => ['tabla' => 'sol_terreno_fotos', 'columna' => 'archivo'],
        'avance' => ['tabla' => 'neg_evidencias', 'columna' => 'archivo'],
        'chat' => ['tabla' => 'neg_mensajes', 'columna' => 'archivo', 'filtro' => ['tipo' => 'foto']],
    ];

    public function handle(GeneradorDeDerivados $generador, AlmacenDeMedios $almacen): int
    {
        $dominio = $this->option('dominio');

        if ($dominio !== null && ! array_key_exists((string) $dominio, self::FUENTES)) {
            $this->error('Dominio desconocido. Usa: '.implode(', ', array_keys(self::FUENTES)).'.');

            return self::INVALID;
        }

        $limite = max(0, (int) $this->option('limite'));
        $revisados = 0;
        $generados = 0;
        $faltantes = 0;

        foreach (self::FUENTES as $nombre => $fuente) {
            if (($dominio !== null && $dominio !== $nombre) || ! Schema::hasTable($fuente['tabla'])) {
                continue;
            }

            $disco = $nombre === 'documentos' ? (string) config('proveedores.disco_documentos', 'local') : null;

            $consulta = DB::table($fuente['tabla'])->whereNotNull($fuente['columna'])->where($fuente['columna'], '!=', '');

            foreach ($fuente['filtro'] ?? [] as $columna => $valor) {
                $consulta->where($columna, $valor);
            }

            if (Schema::hasColumn($fuente['tabla'], 'deleted_at')) {
                $consulta->whereNull('deleted_at');
            }

            foreach ($consulta->orderBy('id')->pluck($fuente['columna']) as $ruta) {
                if ($limite > 0 && $revisados >= $limite) {
                    break 2;
                }

                $revisados++;

                if (! $almacen->existe((string) $ruta, $disco)) {
                    $faltantes++;

                    continue;
                }

                if ($this->option('en-cola')) {
                    $almacen->encolarDerivados((string) $ruta, $disco);

                    continue;
                }

                $generados += count($generador->generar((string) $ruta, $disco, (bool) $this->option('forzar')));
            }
        }

        $this->info("Archivos revisados: {$revisados}. Derivados escritos: {$generados}. Originales que ya no están en el disco: {$faltantes}.");

        return self::SUCCESS;
    }
}
