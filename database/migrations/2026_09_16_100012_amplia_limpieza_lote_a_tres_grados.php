<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo Comercial — amplía el CHECK de `com_lotes.limpieza` de 2 a 3 grados
 * de obstáculos (16/9/2026): el formulario pasa de un select plano a un
 * switch "¿está limpio?" + un grado (poco/medio/mucho) cuando no lo está.
 * Puramente aditivo — `pocos_obstaculos` es el único valor nuevo,
 * `algunos_obstaculos`/`muchos_obstaculos` no cambian de significado
 * (quedan como "medio"/"mucho"), así que ninguna fila existente puede
 * violar el CHECK nuevo. Mismo molde que
 * `2026_09_16_100011_amplia_paleta_color_a_23_a_com_propiedades_table.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_lotes
            DROP CONSTRAINT {$prefijo}com_lotes_limpieza_chk
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE {$prefijo}com_lotes
            ADD CONSTRAINT {$prefijo}com_lotes_limpieza_chk
                CHECK (limpieza IS NULL OR limpieza IN ('limpio', 'pocos_obstaculos', 'algunos_obstaculos', 'muchos_obstaculos'))
        SQL);
    }

    public function down(): void
    {
        // Sin vuelta atrás significativa, mismo criterio que la migración de
        // ampliación de color: si algún lote ya usa `pocos_obstaculos`,
        // restaurar el CHECK viejo rompería esa fila.
    }
};
