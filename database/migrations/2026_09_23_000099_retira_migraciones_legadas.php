<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cierre de la consolidación (ADR 0024, 2026_09_23): en una base que pasó por
 * las migraciones legadas, la tabla `migrations` conserva sus 150 nombres,
 * que ya no existen como archivos. Esta migración —la última de la tanda—
 * borra de `migrations` toda fila cuyo archivo no esté en `database/migrations`,
 * para que `migrate:status` muestre exactamente lo que hay en el repo. En
 * una base recién creada no hay nada que borrar.
 *
 * Solo toca la tabla de control de Laravel, ninguna tabla de dominio.
 */
return new class extends Migration
{
    public function up(): void
    {
        $actuales = array_map(
            fn (string $ruta): string => basename($ruta, '.php'),
            glob(__DIR__.'/*.php') ?: [],
        );

        DB::table('migrations')->whereNotIn('migration', $actuales)->delete();
    }

    public function down(): void
    {
        // Las filas legadas no se recuperan: sus archivos ya no existen.
    }
};
