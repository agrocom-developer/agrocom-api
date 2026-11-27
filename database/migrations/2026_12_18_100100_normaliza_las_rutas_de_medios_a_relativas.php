<?php

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los archivos multimedia se guardan por su ruta RELATIVA dentro del disco, nunca con la URL ni la ruta
 * absoluta del servidor (que puede cambiar). Hoy las columnas ya traen rutas relativas; esta migración
 * deja así cualquier valor que haya quedado con una URL completa, una ruta del servidor o un prefijo
 * `storage/`. Solo reescribe los valores que cambian y es idempotente.
 *
 * `down()` no restaura nada: el valor anterior (una URL o ruta del servidor) no se conserva y volver a él
 * no tiene sentido. Una ruta relativa sigue siendo válida para el código anterior.
 */
return new class extends Migration
{
    /** @var array<string, string> tabla => columna con la ruta */
    private const COLUMNAS = [
        'prv_documentos' => 'archivo_ref',
        'flo_dron_fotos' => 'archivo',
        'sol_terreno_fotos' => 'archivo',
        'neg_evidencias' => 'archivo',
        'neg_mensajes' => 'archivo',
        'sec_user' => 'foto_perfil_ref',
        'ope_evidencias' => 'archivo_url',
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $tabla => $columna) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
                continue;
            }

            DB::table($tabla)->whereNotNull($columna)->where($columna, '!=', '')->orderBy('id')
                ->chunkById(500, function ($filas) use ($tabla, $columna): void {
                    foreach ($filas as $fila) {
                        $actual = (string) $fila->{$columna};
                        $relativa = AlmacenDeMedios::normalizar($actual);

                        if ($relativa !== null && $relativa !== $actual) {
                            DB::table($tabla)->where('id', $fila->id)->update([$columna => $relativa]);
                        }
                    }
                });
        }
    }

    public function down(): void {}
};
